#!/usr/bin/env python3
# One-shot wiring of eDoc Document Service + Citizen into boss sidebar (ADD-only).
# Uses position-based slicing (robust to whitespace/char differences in the block).
import io

path = "layout.php"
with io.open(path, encoding="utf-8") as f:
    s = f.read()

# 1) Add list_edoc_citizen.php to the two master-data in_array literals
old = "list_was.php']"
new = "list_was.php', 'list_edoc_citizen.php']"
assert s.count(old) == 2, "expected 2 master arrays, got %d" % s.count(old)
s = s.replace(old, new)

# 2) Add citizen registry link right after the expense_type link
anchor = "ປະເພດລາຍຈ່າຍ</a>\n"
i = s.find(anchor)
assert i != -1, "expense_type anchor missing"
pos = i + len(anchor)
link = ('          <a href="../pages/list_edoc_citizen.php"      '
        "class=\"<?= in_array($_current, ['list_edoc_citizen.php']) ? 'active' : '' ?>\">"
        "ບັນທຶກພົນລະເມືອງ</a>\n")
s = s[:pos] + link + s[pos:]

# 3) Replace the entire nav-transaction group (Document Service) by position.
#    start = the <a ...> that opens the group (just before href="#nav-transaction")
#    end   = the </div> that closes the group (just before <!-- 5. ...)
nt_parent = s.rfind('<a ', 0, s.find('href="#nav-transaction"'))
marker5 = s.find('<!-- 5.')
assert nt_parent != -1 and marker5 != -1, "nav-transaction group markers not found"
end = s.rfind('</div>', 0, marker5) + len('</div>')

new_block = (
'      <a class="sidebar-link has-children <?= in_array($_current, [\'list_drv.php\', \'list_edoc_document.php\']) ? \'\' : \'collapsed\' ?>" data-bs-toggle="collapse" href="#nav-transaction" role="button">\n'
'        <i class="fas fa-exchange-alt"></i>\n'
'        <span>ບໍລິການເອກະສານ</span>\n'
'        <i class="fas fa-chevron-right sidebar-arrow"></i>\n'
'      </a>\n'
'      <div class="collapse <?= in_array($_current, [\'list_drv.php\', \'list_edoc_document.php\']) ? \'show\' : \'\' ?>" id="nav-transaction">\n'
'        <div class="sidebar-sub">\n'
'          <a href="../pages/list_edoc_document.php?status=SUBMITTED" class="<?= $_current === \'list_edoc_document.php\' && ($_GET[\'status\'] ?? \'\') === \'SUBMITTED\' ? \'active\' : \'\' ?>">ລາຍການ ສະເໜີຂໍເອກະສານ</a>\n'
'          <a href="../pages/list_edoc_document.php?status=PENDING_APPROVAL" class="<?= $_current === \'list_edoc_document.php\' && ($_GET[\'status\'] ?? \'\') === \'PENDING_APPROVAL\' ? \'active\' : \'\' ?>">ລາຍການ ເອກະສານລໍຖ້າອະນຸມັດ</a>\n'
'          <a href="../pages/list_edoc_document.php?status=PENDING_PAYMENT" class="<?= $_current === \'list_edoc_document.php\' && ($_GET[\'status\'] ?? \'\') === \'PENDING_PAYMENT\' ? \'active\' : \'\' ?>">ລາຍການ ເອກະສານລໍຖ້າຊຳລະຄ່າທຳນຽມ</a>\n'
'          <a href="../pages/list_edoc_document.php?status=HANDOVER" class="<?= $_current === \'list_edoc_document.php\' && ($_GET[\'status\'] ?? \'\') === \'HANDOVER\' ? \'active\' : \'\' ?>">ລາຍການ ເອກະສານລໍຖ້າສົ່ງມອບ</a>\n'
'          <a href="../pages/list_edoc_document.php" class="<?= $_current === \'list_edoc_document.php\' && !isset($_GET[\'status\']) ? \'active\' : \'\' ?>">ລາຍການເອກະສານ ບໍລິການເພີ້່ມ</a>\n'
'          <a href="../pages/list_edoc_document.php?status=PAID" class="<?= $_current === \'list_edoc_document.php\' && ($_GET[\'status\'] ?? \'\') === \'PAID\' ? \'active\' : \'\' ?>">ລາຍການ ຮັບເງິນ</a>\n'
'          <a href="#">ລາຍງານ ການເກັບລາຍຮັບ</a>\n'
'          <a href="#">ລາຍງານການບໍລິການເອກະສານ</a>\n'
'          <a href="#">ລາຍງານສະຖິຕິເອກະສານ</a>\n'
'        </div>\n'
'      </div>\n'
)
s = s[:nt_parent] + new_block + s[end:]

with io.open(path, "w", encoding="utf-8") as f:
    f.write(s)
print("OK: citizen link + DS group wired (position-based)")
