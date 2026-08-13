#!/usr/bin/env python3
# Wire eDoc Document Service + Citizen pages into boss sidebar (ADD-only).
# Robust: locate each label, patch the href="#" inside its own <a> tag.
import io, re

path = "layout.php"
with io.open(path, encoding="utf-8") as f:
    s = f.read()

def patch_href_in_anchor(label, url):
    """Find <a ...>LABEL</a>, replace that tag's href='#' with the url. Returns new string."""
    idx = s.find(label)
    assert idx != -1, "label not found: %r" % label
    a_start = s.rfind("<a ", 0, idx)
    assert a_start != -1, "no <a> before label %r" % label
    tag_end = s.find("</a>", idx)
    assert tag_end != -1, "no </a> after label %r" % label
    seg = s[a_start:tag_end]
    assert 'href="#"' in seg, "no placeholder href in tag for %r" % label
    new_seg = seg.replace('href="#"', 'href="%s"' % url, 1)
    return s[:a_start] + new_seg + s[tag_end:]

# 1) Add list_edoc_citizen.php to the two master-data in_array literals
old_arr = "list_was.php']"
new_arr = "list_was.php', 'list_edoc_citizen.php']"
assert s.count(old_arr) == 2, "expected 2 master array literals, found %d" % s.count(old_arr)
s = s.replace(old_arr, new_arr)

# 2) Add citizen registry link right after the expense_type link, inside sidebar-sub.
anchor = "ປະເພດລາຍຈ່າຍ</a>\n"
ai = s.find(anchor)
assert ai != -1, "expense_type anchor not found"
ins_pos = ai + len(anchor)
citizen_link = ("          <a href=\"../pages/list_edoc_citizen.php\"      class=\"<?= in_array($_current, ['list_edoc_citizen.php']) ? 'active' : '' ?>\">ບັນທຶກພົນລະເມືອງ</a>\n")
s = s[:ins_pos] + citizen_link + s[ins_pos:]

# 3) Wire the Document Service group (6 functional items).
mapping = [
    ("ລາຍການ ສະເໜີຂໍເອກະສານ</a>", "../pages/list_edoc_document.php?status=SUBMITTED"),
    ("ລາຍການ ເອກະສານລໍຖ້າອະນຸມັດອະນຸມັດ</a>", "../pages/list_edoc_document.php?status=PENDING_APPROVAL"),
    ("ລາຍການ ເອກະສານລໍຖ້າຊຳລະຄ່າທຳນຽມ</a>", "../pages/list_edoc_document.php?status=PENDING_PAYMENT"),
    ("ລາຍການ ເອກະສານລໍຖ້າສົ່ງມອບ</a>", "../pages/list_edoc_document.php?status=HANDOVER"),
    ("ລາຍການເອກະສານ ບໍລິການເພີ້່ມ</a>", "../pages/list_edoc_document.php"),
    ("ລາຍການ ຮັບເງິນ</a>", "../pages/list_edoc_document.php?status=PAID"),
]
for label, url in mapping:
    s = patch_href_in_anchor(label, url)

# 4) Make the Document Service parent expand when on a DS page.
s = s.replace("in_array($_current, ['list_drv.php'])", "in_array($_current, ['list_drv.php', 'list_edoc_document.php'])")

with io.open(path, "w", encoding="utf-8") as f:
    f.write(s)

print("OK: citizen added to 2 arrays + 1 link; 6 DS items wired; parent expands on DS page")
