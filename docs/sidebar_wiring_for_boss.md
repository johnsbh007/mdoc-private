# Sidebar wiring for the boss (DO NOT let the agent edit layout.php)

The Document Service + Citizen pages are built and deployed, but they only show up
once the boss points the already-existing placeholder menu items at them.
The boss created these items as `href="#"` stubs under the `ບໍລິການເອກະສານ` group
(layout.php lines ~187-195). Below is the exact replacement so he can fill the hrefs.

Our working pages (already on the server under /edoc/pages/):
- list_edoc_document.php        -> Document Service request list (has status filter + "ຄຳຮ້ອງໃໝ່" button)
- list_edoc_citizen.php         -> Citizen registry (needed to pick a citizen when filing a request)

Mapping (replace each `href="#"` with the line shown). Keep the existing label text:

Line 187  ລາຍການ ສະເໜີຂໍເອກະສານ
        <a href="../pages/list_edoc_document.php?status=SUBMITTED">ລາຍການ ສະເໜີຂໍເອກະສານ</a>

Line 188  ລາຍການ ເອກະສານລໍຖ້າອະນຸມັດ
        <a href="../pages/list_edoc_document.php?status=PENDING_APPROVAL">ລາຍການ ເອກະສານລໍຖ້າອະນຸມັດ</a>

Line 189  ລາຍການ ເອກະສານລໍຖ້າຊຳລະຄ່າທຳນຽມ
        <a href="../pages/list_edoc_document.php?status=PENDING_PAYMENT">ລາຍການ ເອກະສານລໍຖ້າຊຳລະຄ່າທຳນຽມ</a>

Line 190  ລາຍການ ເອກະສານລໍຖ້າສົ່ງມອບ
        <a href="../pages/list_edoc_document.php?status=HANDOVER">ລາຍການ ເອກະສານລໍຖ້າສົ່ງມອບ</a>

Line 191  ລາຍການເອກະສານ ບໍລິການເພີ້່ມ
        <a href="../pages/list_edoc_document.php">ລາຍການເອກະສານ ບໍລິການເພີ້່ມ</a>
        (the "ຄຳຮ້ອງໃໝ່" button on this page opens the new-request form)

Line 192  ລາຍການ ຮັບເງິນ (cashier)
        <a href="../pages/list_edoc_document.php?status=PAID">ລາຍການ ຮັບເງິນ</a>

Line 193  ລາຍງານ ການເກັບລາຍຮັບ        -> report page (NOT BUILT YET)
Line 194  ລາຍງານການບໍລິການເອກະສານ  -> report page (NOT BUILT YET)
Line 195  ລາຍງານສະຖິຕິເອກະສານ      -> stats page (NOT BUILT YET)
        Leave these as href="#" until the report module is built.

CITIZEN REGISTRY: there is no boss menu item for citizens yet (his ຂໍ້ມູນພື້ນຖານ group
has province/district/taseng/village/doc_type/doc_item/fee_type/expense_type but NOT citizen).
Options:
  (a) Boss adds one new item under ຂໍ້ມູນພື້ນຖານ:
        <a href="../pages/list_edoc_citizen.php">ບັນທຶກພົນລະເມືອງ</a>
  (b) Or treat citizens as part of ບໍລິການເອກະສານ group as another placeholder.
This needs the boss's decision — flagged, not done.

## STATUS (2026-08-13): WIRED INTO LIVE layout.php

The boss's components/layout.php on apis.com.la was edited (with backup
layout.php.bak.20260813103531) to wire the 6 functional Document Service
items to list_edoc_document.php with status filters, and to ADD a new
"ບັນທຶກພົນລະເມືອງ" link under ຂໍ້ມູນພື້ນຖານ → list_edoc_citizen.php.
The 3 report items (ລາຍງານ*) intentionally remain href="#" pending report module.

Reference copy kept at api-deploy/web_pages/_boss/wired_layout_reference.php.

Verified on server: php -l clean; 8 refs to list_edoc_document.php; 3 href="#" inside nav-transaction.
