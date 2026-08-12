<?php
declare(strict_types=1);

namespace App\Services;

use App\Api\ApiException;
use PDO;

/**
 * DocumentService — single source of truth for mDoc business logic.
 *
 * IMPLEMENTED: catalog (doc-types, doc-items, fields), hierarchy, auth (login/register/me).
 * STUBBED (throw ApiException): submitApplication, listMyDocuments, getDocumentDetail,
 *   getPaymentQr, processPaymentCallback, downloadDocument, getReceipt.
 *   These need the workflow rules agreed with the edoc admin app — implement step by step.
 */
class DocumentService
{
    private PDO $db;
    private string $jwtSecret;
    private int $tokenTtlHours;

    public function __construct()
    {
        $config = require __DIR__ . '/../config.php';
        $db = $config['db'];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
        $this->db = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->jwtSecret = $config['jwt_secret'];
        $this->tokenTtlHours = $config['token_ttl_hours'];
    }

    // =========================================================================
    // CATALOG
    // =========================================================================

    public function getDocTypes(array $query = []): array
    {
        $sql = "SELECT id, doc_type_code, name_lo, name_en, is_active
                FROM edoc_doc_type
                WHERE is_active = 1
                ORDER BY doc_type_code";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getDocItems(array $query = []): array
    {
        $sql = "SELECT id, doc_type_id, doc_item_code, name_lo, name_en, unit, price, is_active
                FROM edoc_doc_item
                WHERE is_active = 1";
        $params = [];
        if (!empty($query['doc_type_id'])) {
            $sql .= " AND doc_type_id = :doc_type_id";
            $params['doc_type_id'] = (int)$query['doc_type_id'];
        }
        $sql .= " ORDER BY doc_type_id, id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDocItem(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, doc_type_id, doc_item_code, name_lo, name_en, unit, price, is_active
             FROM edoc_doc_item WHERE id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new ApiException('NOT_FOUND', 'Document item not found', 404);
        }
        $item['fields'] = $this->getDocItemFields($id);
        return $item;
    }

    public function getDocItemFields(int $docItemId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, doc_type_id, field_name, field_label, field_type, is_active, sort_order
             FROM edoc_doc_field
             WHERE doc_type_id = (
                 SELECT doc_type_id FROM edoc_doc_item WHERE id = :item_id
             )
             AND is_active = 1
             ORDER BY sort_order, id"
        );
        $stmt->execute(['item_id' => $docItemId]);
        return $stmt->fetchAll();
    }

    // =========================================================================
    // HIERARCHY — Province → District → Taseng → Village
    // (tables currently empty until the official Taseng list is available)
    // =========================================================================

    public function getHierarchy(array $query = []): array
    {
        $provinces = $this->db->query(
            "SELECT id, prov_code, name_lo, name_en, off_id FROM edoc_province ORDER BY name_lo"
        )->fetchAll();
        $districts = $this->db->query(
            "SELECT id, province_id, dist_code, name_lo, name_en, off_id FROM edoc_district ORDER BY name_lo"
        )->fetchAll();
        $tassengs = $this->db->query(
            "SELECT id, district_id, tas_code, ncode, name_lo, name_en, off_id FROM edoc_taseng ORDER BY name_lo"
        )->fetchAll();
        $villages = $this->db->query(
            "SELECT id, tasseng_id, vil_code, ncode, name_lo, name_en, off_id FROM edoc_village ORDER BY name_lo"
        )->fetchAll();

        return [
            'provinces' => $provinces,
            'districts' => $districts,
            'tassengs' => $tassengs,
            'villages' => $villages,
        ];
    }

    // =========================================================================
    // AUTH — citizen login/register/me
    // =========================================================================

    public function login(?array $body): array
    {
        $phone = trim((string)($body['phone'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($phone === '' || $password === '') {
            throw new ApiException('VALIDATION_ERROR', 'Phone and password are required', 400);
        }

        $stmt = $this->db->prepare(
            "SELECT id, phone, email, password_hash, full_name, id_card, village_id, dob, gender, is_active
             FROM edoc_citizen WHERE phone = :phone AND is_active = 1"
        );
        $stmt->execute(['phone' => $phone]);
        $citizen = $stmt->fetch();

        if (!$citizen || !password_verify($password, $citizen['password_hash'])) {
            throw new ApiException('INVALID_CREDENTIALS', 'Invalid phone or password', 401);
        }

        unset($citizen['password_hash']);
        return [
            'token' => $this->issueToken($citizen['id'], $citizen['phone']),
            'citizen' => $this->withLocationNames($citizen),
        ];
    }

    public function register(?array $body): array
    {
        $phone = trim((string)($body['phone'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $fullName = trim((string)($body['full_name'] ?? ''));
        $villageId = isset($body['village_id']) ? (int)$body['village_id'] : null;

        if ($phone === '' || $password === '' || $fullName === '') {
            throw new ApiException('VALIDATION_ERROR', 'phone, password, full_name are required', 400);
        }
        if (strlen($password) < 8) {
            throw new ApiException('VALIDATION_ERROR', 'Password must be at least 8 characters', 400);
        }
        if (!preg_match('/^(\+856|0)20\d{8}$/', $phone)) {
            throw new ApiException('VALIDATION_ERROR', 'Invalid Lao phone number (expected 020XXXXXXXX or +85620XXXXXXXX)', 400);
        }

        // Phone uniqueness
        $stmt = $this->db->prepare("SELECT id FROM edoc_citizen WHERE phone = :phone");
        $stmt->execute(['phone' => $phone]);
        if ($stmt->fetch()) {
            throw new ApiException('PHONE_TAKEN', 'This phone number is already registered', 409);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO edoc_citizen (phone, email, password_hash, full_name, id_card, village_id, dob, gender, is_active)
             VALUES (:phone, :email, :hash, :full_name, :id_card, :village_id, :dob, :gender, 1)"
        );
        $stmt->execute([
            'phone' => $phone,
            'email' => $body['email'] ?? null,
            'hash' => password_hash($password, PASSWORD_BCRYPT),
            'full_name' => $fullName,
            'id_card' => $body['id_card'] ?? null,
            'village_id' => $villageId,
            'dob' => $body['dob'] ?? null,
            'gender' => $body['gender'] ?? null,
        ]);
        $citizenId = (int)$this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "SELECT id, phone, email, full_name, id_card, village_id, dob, gender, is_active
             FROM edoc_citizen WHERE id = :id"
        );
        $stmt->execute(['id' => $citizenId]);
        $citizen = $stmt->fetch();

        return [
            'token' => $this->issueToken($citizenId, $phone),
            'citizen' => $this->withLocationNames($citizen),
        ];
    }

    public function me(array $headers = []): array
    {
        $citizen = $this->requireCitizen($headers);
        return $this->withLocationNames($citizen);
    }

    // =========================================================================
    // STUBS — workflow/payment endpoints (implement step by step)
    // =========================================================================

    public function submitApplication(?array $body, array $headers = []): array
    {
        $this->requireCitizen($headers);
        throw new ApiException('NOT_IMPLEMENTED', 'Document submission workflow not yet implemented', 501);
    }

    public function listMyDocuments(array $query = [], array $headers = []): array
    {
        $this->requireCitizen($headers);
        throw new ApiException('NOT_IMPLEMENTED', 'Document list not yet implemented', 501);
    }

    public function getDocumentDetail(int $id, array $headers = []): array
    {
        $this->requireCitizen($headers);
        throw new ApiException('NOT_IMPLEMENTED', 'Document detail not yet implemented', 501);
    }

    public function getPaymentQr(int $id, array $headers = []): array
    {
        $this->requireCitizen($headers);
        throw new ApiException('NOT_IMPLEMENTED', 'QR payment not yet implemented', 501);
    }

    public function processPaymentCallback(?array $body): array
    {
        throw new ApiException('NOT_IMPLEMENTED', 'Payment callback not yet implemented', 501);
    }

    public function downloadDocument(int $id, array $headers = []): array
    {
        $this->requireCitizen($headers);
        throw new ApiException('NOT_IMPLEMENTED', 'Document download not yet implemented', 501);
    }

    public function getReceipt(int $id, array $headers = []): array
    {
        $this->requireCitizen($headers);
        throw new ApiException('NOT_IMPLEMENTED', 'Receipt not yet implemented', 501);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Minimal HS256 JWT (no external dependency). For v1 this is fine;
     * swap to a library (firebase/php-jwt) when the PWA goes public.
     */
    private function issueToken(int $citizenId, string $phone): string
    {
        $now = time();
        $header = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64url(json_encode([
            'sub' => $citizenId,
            'phone' => $phone,
            'iat' => $now,
            'exp' => $now + ($this->tokenTtlHours * 3600),
        ]));
        $signature = hash_hmac('sha256', "$header.$payload", $this->jwtSecret, true);
        return "$header.$payload." . base64url($signature);
    }

    private function requireCitizen(array $headers = []): array
    {
        $auth = $headers['Authorization'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            throw new ApiException('UNAUTHORIZED', 'Missing Bearer token', 401);
        }
        $parts = explode('.', $m[1]);
        if (count($parts) !== 3) {
            throw new ApiException('UNAUTHORIZED', 'Malformed token', 401);
        }
        [$h, $p, $sig] = $parts;
        $expected = base64url(hash_hmac('sha256', "$h.$p", $this->jwtSecret, true));
        if (!hash_equals($expected, $sig)) {
            throw new ApiException('UNAUTHORIZED', 'Invalid token signature', 401);
        }
        $payload = json_decode(base64url_decode($p), true);
        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            throw new ApiException('UNAUTHORIZED', 'Token expired', 401);
        }

        $stmt = $this->db->prepare(
            "SELECT id, phone, email, full_name, id_card, village_id, dob, gender, is_active
             FROM edoc_citizen WHERE id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => (int)$payload['sub']]);
        $citizen = $stmt->fetch();
        if (!$citizen) {
            throw new ApiException('UNAUTHORIZED', 'Citizen not found', 401);
        }
        return $citizen;
    }

    /**
     * Enrich citizen with village/tasseng/district/province names for display.
     */
    private function withLocationNames(array $citizen): array
    {
        if (!empty($citizen['village_id'])) {
            $stmt = $this->db->prepare(
                "SELECT v.name_lo AS village_name, t.name_lo AS tasseng_name,
                        d.name_lo AS district_name, p.name_lo AS province_name
                 FROM edoc_village v
                 LEFT JOIN edoc_taseng t ON t.id = v.tasseng_id
                 LEFT JOIN edoc_district d ON d.id = t.district_id
                 LEFT JOIN edoc_province p ON p.id = d.province_id
                 WHERE v.id = :vid"
            );
            $stmt->execute(['vid' => $citizen['village_id']]);
            $names = $stmt->fetch() ?: [];
            $citizen = array_merge($citizen, $names);
        }
        return $citizen;
    }
}

/** base64url helpers (global functions, used by DocumentService) */
if (!function_exists('base64url')) {
    function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
