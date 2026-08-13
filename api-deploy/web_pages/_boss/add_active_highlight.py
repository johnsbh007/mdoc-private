#!/usr/bin/env python3
# Add status-aware 'active' highlight to the 6 wired Document Service links.
import io

path = "layout.php"
with io.open(path, encoding="utf-8") as f:
    s = f.read()

def set_class_for(label, expr):
    """Locate the <a ...>LABEL</a>, set its class attribute to the given PHP expr."""
    idx = s.find(label)
    assert idx != -1, "label not found: %r" % label
    a_start = s.rfind("<a ", 0, idx)
    assert a_start != -1, "no <a> before %r" % label
    # find end of opening tag
    tag_end = s.find(">", a_start)
    assert tag_end != -1, "no '>' in opening tag for %r" % label
    open_tag = s[a_start:tag_end+1]
    # build new class attr
    new_class = ' class="<?= %s ? \'active\' : \'\' ?>"' % expr
    # remove any existing class="..." inside open_tag
    import re
    open_tag_no_class = re.sub(r'\s+class="[^"]*"', '', open_tag, count=1)
    new_open = open_tag_no_class[:-1] + new_class + ">"
    return s[:a_start] + new_open + s[tag_end+1:]

mapping = [
    ("ລາຍການ ສະເໜີຂໍເອກະສານ</a>",
     "$_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'SUBMITTED'"),
    ("ລາຍການ ເອກະສານລໍຖ້າອະນຸມັດອະນຸມັດ</a>",
     "$_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'PENDING_APPROVAL'"),
    ("ລາຍການ ເອກະສານລໍຖ້າຊຳລະຄ່າທຳນຽມ</a>",
     "$_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'PENDING_PAYMENT'"),
    ("ລາຍການ ເອກະສານລໍຖ້າສົ່ງມອບ</a>",
     "$_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'HANDOVER'"),
    ("ລາຍການເອກະສານ ບໍລິການເພີ້່ມ</a>",
     "$_current === 'list_edoc_document.php' && !isset($_GET['status'])"),
    ("ລາຍການ ຮັບເງິນ</a>",
     "$_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'PAID'"),
]
for label, expr in mapping:
    s = set_class_for(label, expr)

with io.open(path, "w", encoding="utf-8") as f:
    f.write(s)
print("OK: status-aware active highlight applied to 6 DS links")
