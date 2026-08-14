<?php
// Migration: add basic-data lookup tables (nationality/origin -> edoc_country,
// ethnicity -> edoc_ethnicity, religion -> edoc_religion, occupation -> edoc_occupation)
// Seed with standard data: ISO 3166-1 countries, 50 Lao ethnic groups, religions, occupations.
require __DIR__ . '/../connect.php';
$conn->exec("SET NAMES utf8mb4");
$conn->beginTransaction();
try {
  $conn->exec("CREATE TABLE IF NOT EXISTS edoc_country (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code CHAR(2) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    name_lo VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY uk_country_code (code)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $conn->exec("CREATE TABLE IF NOT EXISTS edoc_ethnicity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    name_lo VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY uk_eth_code (code)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $conn->exec("CREATE TABLE IF NOT EXISTS edoc_religion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    name_lo VARCHAR(100) NOT NULL,
    name_en VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY uk_rel_code (code)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $conn->exec("CREATE TABLE IF NOT EXISTS edoc_occupation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    name_lo VARCHAR(100) NOT NULL,
    name_en VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY uk_occ_code (code)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('LA', 'Laos', 'ລາວ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('TH', 'Thailand', 'ໄທ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('VN', 'Vietnam', 'ຫວຽດນາມ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('CN', 'China', 'ຈີນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('KH', 'Cambodia', 'ກຳປູເຈຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('MM', 'Myanmar', 'ເມຍນມາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('MY', 'Malaysia', 'ມາເລເຊຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('SG', 'Singapore', 'ສິງກະໂປ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('ID', 'Indonesia', 'ອິນໂດເນເຊຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('PH', 'Philippines', 'ຟິລິປິນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('BN', 'Brunei', 'ບຣຸໄນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('US', 'United States', 'ສະຫະລັດອາເມລິກາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('GB', 'United Kingdom', 'ສະຫະລາຊະອານາຈັກ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('FR', 'France', 'ຝລັ່ງ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('DE', 'Germany', 'ເຢຍລະມັນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('AU', 'Australia', 'ອົອສເຕຼລີ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('JP', 'Japan', 'ຍີ່ປຸ່ນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('KR', 'South Korea', 'ເກົາຫຼີໃຕ້')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('KP', 'North Korea', 'ເກົາຫຼີເໜືອ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('IN', 'India', 'ອິນເດຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('RU', 'Russia', 'ລັດເຊຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('CA', 'Canada', 'ການາດາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('IT', 'Italy', 'ອິຕາລີ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('ES', 'Spain', 'ສະເປນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('NL', 'Netherlands', 'ເນເທີແລນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('BE', 'Belgium', 'ເບວຢຽມ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('CH', 'Switzerland', 'ສະວິດເຊີແລນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('AT', 'Austria', 'ອົອສເຕຼຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('SE', 'Sweden', 'ສະວິເດນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('NO', 'Norway', 'ນໍເວ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('DK', 'Denmark', 'ເດນມາກ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('FI', 'Finland', 'ຟິນແລນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('PT', 'Portugal', 'ໂປຕຸກກັນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('GR', 'Greece', 'ເກຣັກ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('TR', 'Turkey', 'ຕຸນເກຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('PL', 'Poland', 'ໂປໂລຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('CZ', 'Czechia', 'ເຊັກ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('HU', 'Hungary', 'ຮັງກາລີ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('RO', 'Romania', 'ຣູເມເນຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('UA', 'Ukraine', 'ຢູເຄຼນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('NZ', 'New Zealand', 'ນິວຊີແລນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('AE', 'United Arab Emirates', 'ສະຫະລາດເອເຊຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('SA', 'Saudi Arabia', 'ຊາອຸດິອາລະເບຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('QA', 'Qatar', 'ກາຕາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('KW', 'Kuwait', 'ຄູເວດ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('IL', 'Israel', 'ອິສລາເອວ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('EG', 'Egypt', 'ອີຢິບ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('ZA', 'South Africa', 'ອາຟະລິກາໃຕ້')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('NG', 'Nigeria', 'ໄນຈີຣິຍາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('KE', 'Kenya', 'ເຄນຍາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('BR', 'Brazil', 'ບຣາຊິວ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('AR', 'Argentina', 'ອາເຈນຕິນາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('MX', 'Mexico', 'ເມັກຊິໂກ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('CL', 'Chile', 'ຊິລີ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('PE', 'Peru', 'ເປລູ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('CO', 'Colombia', 'ໂກລົມເບຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('PA', 'Panama', 'ປານາມາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('MO', 'Macao', 'ມາເກົາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('HK', 'Hong Kong', 'ຮ່ອງກົງ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('TW', 'Taiwan', 'ໄຕ້ຫວັນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('KZ', 'Kazakhstan', 'ຄາຊັກສະຖານ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('UZ', 'Uzbekistan', 'ອຸສເບກິສຖານ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('MN', 'Mongolia', 'ມົງໂກລີ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('NP', 'Nepal', 'ເນປານ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('BD', 'Bangladesh', 'ບັງກະລາເດຊ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('PK', 'Pakistan', 'ປາກິສຖານ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('LK', 'Sri Lanka', 'ສີລັງກາ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('AF', 'Afghanistan', 'ອັຟການິດສະຖານ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('IR', 'Iran', 'ອີຣານ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('IQ', 'Iraq', 'ອີລັກ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('SY', 'Syria', 'ຊີເຣຍ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('LB', 'Lebanon', 'ເລບານອນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('JO', 'Jordan', 'ຈໍແດນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('YE', 'Yemen', 'ເຢເມນ')");
  $conn->exec("INSERT INTO edoc_country (c, o, d, e, ,,  , n, a, m, e, _, e, n, ,,  , n, a, m, e, _, l, o) VALUES ('ZZ', 'Other / ອື່ນໆ', 'ອື່ນໆ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('01', 'ຊົນເຜົ່າ ລາວ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('02', 'ຊົນເຜົ່າ ໄຕ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('03', 'ຊົນເຜົ່າ ຜູ້ໄທ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('04', 'ຊົນເຜົ່າ ລື້')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('05', 'ຊົນເຜົ່າ ຍວນ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('06', 'ຊົນເຜົ່າ ຢັ້ງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('07', 'ຊົນເຜົ່າ ແຊກ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('08', 'ຊົນເຜົ່າ ໄທເໜືອ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('09', 'ຊົນເຜົ່າ ກຶມມຸ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('10', 'ຊົນເຜົ່າ ໄປຣ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('11', 'ຊົນເຜົ່າ ຊິງມູນ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('12', 'ຊົນເຜົ່າ ຜ້ອງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('13', 'ຊົນເຜົ່າ ແທ່ນ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('14', 'ຊົນເຜົ່າ ເອີດູ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('15', 'ຊົນເຜົ່າ ບິດ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('16', 'ຊົນເຜົ່າ ລະເມດ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('17', 'ຊົນເຜົ່າ ສາມຕ່າວ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('18', 'ຊົນເຜົ່າ ກະຕາງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('19', 'ຊົນເຜົ່າ ມະກອງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('20', 'ຊົນເຜົ່າ ຕຣີ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('21', 'ຊົນເຜົ່າ ຢຣຸ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('22', 'ຊົນເຜົ່າ ຕຣຽງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('23', 'ຊົນເຜົ່າ ຕະໂອ້ຍ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('24', 'ຊົນເຜົ່າ ແຢະ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('25', 'ຊົນເຜົ່າ ເບົຣາ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('26', 'ຊົນເຜົ່າ ກະຕູ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('27', 'ຊົນເຜົ່າ ຮາຣັກ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('28', 'ຊົນເຜົ່າ ໂອຍ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('29', 'ຊົນເຜົ່າ ກຣຽງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('30', 'ຊົນເຜົ່າ ເຈັງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('31', 'ຊົນເຜົ່າ ສະດາງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('32', 'ຊົນເຜົ່າ ຊ່ວຍ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('33', 'ຊົນເຜົ່າ ຍະເຫີນ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('34', 'ຊົນເຜົ່າ ວະລີ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('35', 'ຊົນເຜົ່າ ປະໂກະ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('36', 'ຊົນເຜົ່າ ຂະແມ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('37', 'ຊົນເຜົ່າ ຕຸ້ມ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('38', 'ຊົນເຜົ່າ ງ້ວນ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('39', 'ຊົນເຜົ່າ ມ້ອຍ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('40', 'ຊົນເຜົ່າ ກຣີ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('41', 'ຊົນເຜົ່າ ບຣູ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('42', 'ຊົນເຜົ່າ ມົ້ງ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('43', 'ຊົນເຜົ່າ ອິວມຽນ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('44', 'ຊົນເຜົ່າ ອາຄາ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('45', 'ຊົນເຜົ່າພູນ້ອຍ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('46', 'ຊົນເຜົ່າ ລາຫູ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('47', 'ຊົນເຜົ່າ ສີລາ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('48', 'ຊົນເຜົ່າ ຮາຢີ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('49', 'ຊົນເຜົ່າ ໂລໂລ')");
  $conn->exec("INSERT INTO edoc_ethnicity (c, o, d, e, ,,  , n, a, m, e, _, l, o) VALUES ('50', 'ຊົນເຜົ່າ ຫໍ້')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('BUD', 'ພຸດທະສາສນາ', 'Buddhism')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('CHR', 'ຄຣິສຕຽນ', 'Christianity')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('CAT', 'ກາໂຕລິກ', 'Catholicism')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('ISL', 'ອິສລາມ', 'Islam')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('HIN', 'ຮິນດູ', 'Hinduism')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('CON', 'ສາສະໜາພື້ນເມືອງ', 'Indigenous/Folk')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('NON', 'ບໍ່ມີສາສະໜາ', 'None')");
  $conn->exec("INSERT INTO edoc_religion (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('OTH', 'ສາສະໜາອື່ນ', 'Other')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('FARM', 'ເກະສິກຳ / ປາກະສິກຳ', 'Farmer / Agriculture')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('FISH', 'ຈັບປາ', 'Fisherman')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('TRAD', 'ຄ້າຂາຍ', 'Trader / Vendor')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('GOVT', 'ພະນັກງານລັດ', 'Civil Servant')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('TEAC', 'ຄູສອນ', 'Teacher')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('DOCT', 'ທະແພດ / ພະຍາບານ', 'Doctor / Nurse')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('ENG', 'ວິສະວະກອນ', 'Engineer')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('POL', 'ຕຳຫຼວດ / ທະຫານ', 'Police / Military')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('STUD', 'ນັກຮຽນ / ນັກສຶກສາ', 'Student')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('DRIV', 'ຄົນຂັບລົດ', 'Driver')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('CONS', 'ຊ່າງ', 'Construction Worker')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('BUSS', 'ເຈົ້າຂອງທຸລະກິດ', 'Business Owner')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('EMP', 'ພະນັກງານບໍລິສັດ', 'Company Employee')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('ART', 'ຜູ້ສ້າງສັດງານສິລະປະ', 'Artist / Artisan')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('LAW', 'ທະນາຍຄວາມ', 'Lawyer')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('TECH', 'ຊ່າງເຕັກໂນໂລຊີ', 'Technician / IT')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('MONK', 'ສົງ / ນັກບວດ', 'Monk')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('HOUS', 'ພາບັນດາ / ແມ່ບ້ານ', 'Housewife / Homemaker')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('RETI', 'ຜູ້ຊົບເກດ', 'Retired')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('UNEM', 'ບໍ່ມີວຽກທຳ', 'Unemployed')");
  $conn->exec("INSERT INTO edoc_occupation (c, o, d, e, ,,  , n, a, m, e, _, l, o, ,,  , n, a, m, e, _, e, n) VALUES ('OTH', 'ອາຊີບອື່ນ', 'Other')");
  $conn->commit();
  echo "SCHEMA_OK tables created and seeded\n";
} catch (Exception $e) {
  $conn->rollBack();
  echo "ERR: " . $e->getMessage() . "\n";
}
