#!/usr/bin/env bash
# Document Service end-to-end workflow test on apis.com.la (WEB-ONLY, no DB CLI)
# Uses known-good citizen_id=8 and doc_item_id=1; captures new doc_id from the
# insert's JSON response (insert returns JSON when called via X-Requested-With).
# No DB password needed. Reads every status from the app's own JSON.
set +e
BASE="https://edoc.apis.com.la"
CURL="curl -k -s -L"
JAR="/tmp/ds_cookie.txt"
rm -f "$JAR"
XH="-H X-Requested-With:XMLHttpRequest"
CID=8; DID=1

echo "=== 1. LOGIN (a/a) ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/pages/login.php" \
  -d "username=a&password=a&off_id=00" -H "Content-Type: application/x-www-form-urlencoded" \
  -w "\nHTTP %{http_code}\n"

echo "=== 3. CREATE REQUEST (SUBMITTED) ==="
CREATE=$($CURL -c "$JAR" -b "$JAR" $XH -X POST "$BASE/insert/insert_edoc_document.php" \
  --data-urlencode "citizen_id=$CID" --data-urlencode "doc_item_id=$DID" \
  --data-urlencode "village_id=1" --data-urlencode "amount=5000" \
  --data-urlencode "remark=test flow")
echo "$CREATE"
DOCID=$(echo "$CREATE" | sed -n 's/.*"doc_id":"\{0,1\}\([0-9]*\)"\{0,1\}.*/\1/p' | head -1)
echo "new doc_id=$DOCID"

get_status() { $CURL -c "$JAR" -b "$JAR" -X POST "$BASE/ajax/search/get_edoc_document.php" -d "id=$1" | sed -n 's/.*"doc":{[^}]*"status":"\([A-Z]*\)".*/\1/p' | head -1; }

echo "=== 4. REVIEW fetch ==="
echo "review status=$(get_status "$DOCID") (expect SUBMITTED)"

echo "=== 5. APPROVE ==="
$CURL -c "$JAR" -b "$JAR" $XH -X POST "$BASE/ajax/action/process_edoc_document.php" \
  --data-urlencode "id=$DOCID" --data-urlencode "action=approve" --data-urlencode "remark=approved by test" >/dev/null
echo "status=$(get_status "$DOCID") (expect ISSUED)"

echo "=== 6. PAY (cash) ==="
$CURL -c "$JAR" -b "$JAR" $XH -X POST "$BASE/ajax/action/process_edoc_document.php" \
  --data-urlencode "id=$DOCID" --data-urlencode "action=pay" --data-urlencode "method=CASH" --data-urlencode "remark=paid cash" >/dev/null
echo "status=$(get_status "$DOCID") (expect PAID)"

echo "=== 7. ISSUE / HANDOVER ==="
$CURL -c "$JAR" -b "$JAR" $XH -X POST "$BASE/ajax/action/process_edoc_document.php" \
  --data-urlencode "id=$DOCID" --data-urlencode "action=issue" --data-urlencode "remark=handed over" >/dev/null
echo "status=$(get_status "$DOCID") (expect HANDOVER)"

echo "=== 8. STATUS HISTORY (via review JSON) ==="
$CURL -c "$JAR" -b "$JAR" -X POST "$BASE/ajax/search/get_edoc_document.php" -d "id=$DOCID" | tr ',' '\n' | grep -E '"status"|"remark"' | head -12

echo "=== 9. CLEANUP test doc ==="
$CURL -c "$JAR" -b "$JAR" $XH -X POST "$BASE/ajax/del/delete_edoc_document.php" --data-urlencode "id=$DOCID" -w " | HTTP %{http_code}\n"
echo "cleanup done"
