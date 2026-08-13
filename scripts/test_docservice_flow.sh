#!/usr/bin/env bash
# End-to-end Document Service workflow test on apis.com.la (run via plink)
# DB password is read from env EDOC_DB_PASS (never hardcode).
set +e
BASE="https://edoc.apis.com.la"
CURL="curl -k -s -L"
JAR="/tmp/ds_cookie.txt"
rm -f "$JAR"

DBPASS="${EDOC_DB_PASS:-CHANGE_ME}"
MYSQL="mysql -uadmin -p'$DBPASS' edoc_tasaeng -N"

echo "=== 1. LOGIN (a/a) ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/pages/login.php" \
  -d "username=a&password=a&off_id=00" -H "Content-Type: application/x-www-form-urlencoded" \
  -w "\nHTTP %{http_code}\n"

echo "=== 2. Pick citizen_id + doc_item_id ==="
CID=$($MYSQL -e "SELECT id FROM edoc_citizen ORDER BY id DESC LIMIT 1;")
DID=$($MYSQL -e "SELECT id FROM edoc_doc_item WHERE is_active=1 ORDER BY id ASC LIMIT 1;")
echo "citizen_id=$CID  doc_item_id=$DID"

echo "=== 3. CREATE REQUEST (SUBMITTED) ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/insert/insert_edoc_document.php" \
  --data-urlencode "citizen_id=$CID" --data-urlencode "doc_item_id=$DID" \
  --data-urlencode "village_id=1" --data-urlencode "amount=5000" \
  --data-urlencode "remark=test flow" -o /dev/null -w "HTTP %{http_code}\n"
DOCID=$($MYSQL -e "SELECT id FROM edoc_document ORDER BY id DESC LIMIT 1;")
echo "new doc_id=$DOCID  status=$($MYSQL -e "SELECT status FROM edoc_document WHERE id=$DOCID;")"

echo "=== 4. REVIEW fetch ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/ajax/search/get_edoc_document.php" -d "id=$DOCID" -w "\nHTTP %{http_code}\n" | head -c 400
echo

echo "=== 5. APPROVE ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/ajax/action/process_edoc_document.php" \
  --data-urlencode "id=$DOCID" --data-urlencode "action=approve" --data-urlencode "remark=approved by test"
echo "  status=$($MYSQL -e "SELECT status FROM edoc_document WHERE id=$DOCID;")"

echo "=== 6. PAY (cash) ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/ajax/action/process_edoc_document.php" \
  --data-urlencode "id=$DOCID" --data-urlencode "action=pay" --data-urlencode "method=CASH" --data-urlencode "remark=paid cash"
echo "  status=$($MYSQL -e "SELECT status FROM edoc_document WHERE id=$DOCID;")"
echo "  invoice=$($MYSQL -e "SELECT status FROM edoc_invoice WHERE doc_id=$DOCID;")"
echo "  payment=$($MYSQL -e "SELECT COUNT(*) FROM edoc_payment WHERE invoice_id=(SELECT id FROM edoc_invoice WHERE doc_id=$DOCID);")"
echo "  receipt=$($MYSQL -e "SELECT COUNT(*) FROM edoc_receipt r JOIN edoc_payment p ON r.payment_id=p.id WHERE p.invoice_id=(SELECT id FROM edoc_invoice WHERE doc_id=$DOCID);")"

echo "=== 7. ISSUE / HANDOVER ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/ajax/action/process_edoc_document.php" \
  --data-urlencode "id=$DOCID" --data-urlencode "action=issue" --data-urlencode "remark=handed over"
echo "  status=$($MYSQL -e "SELECT status FROM edoc_document WHERE id=$DOCID;")"

echo "=== 8. STATUS HISTORY ==="
$MYSQL -e "SELECT status, remark, changed_by FROM edoc_document_status WHERE doc_id=$DOCID ORDER BY id ASC;"

echo "=== 9. CLEANUP test doc ==="
$MYSQL -e "DELETE FROM edoc_document_status WHERE doc_id=$DOCID; DELETE FROM edoc_form_data WHERE doc_id=$DOCID; DELETE FROM edoc_invoice WHERE doc_id=$DOCID; DELETE FROM edoc_document WHERE id=$DOCID;"
echo "cleanup done"
