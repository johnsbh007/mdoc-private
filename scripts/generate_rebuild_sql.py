#!/usr/bin/env python3
"""
Generate complete migration SQL to rebuild edoc hierarchy from tax_ets official data.
- Renames edoc_tasseng -> edoc_taseng
- Uses correct Lao UTF-8 from tax_ets
- Adds ncode fields for new hierarchical coding
"""

import json

with open('D:/m-doc-taseng/docs/official_hierarchy.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

provinces = data['provinces']
districts = data['districts']
villages = data['villages']

print(f"Loaded: {len(provinces)} provinces, {len(districts)} districts, {len(villages)} villages")

# Build district -> taseng mapping from village codes
# Village code format: PPDDVVV (P=province 2, D=district 2, V=village 3)
# We'll derive tasengs from village codes: first 6 digits = PPDDTT (T=taseng 2)

# Actually, tax_ets doesn't have tasengs - we need to create them.
# For now, we'll create 1 taseng per district (or use a reasonable grouping)
# The user mentioned they don't have official taseng list yet.

# Let's group villages by first 6 digits of village_code to create tasengs
from collections import defaultdict

district_to_tasengs = defaultdict(dict)  # district_code -> {taseng_code: [villages]}

for v in villages:
    vc = v['vil_code']
    dc = v['district_code']
    if len(vc) >= 6:
        # Extract taseng code from village code (positions 4-5, 0-indexed)
        taseng_code = vc[4:6]
        if taseng_code not in district_to_tasengs[dc]:
            district_to_tasengs[dc][taseng_code] = []
        district_to_tasengs[dc][taseng_code].append(v)

# Print summary
for dc, tasengs in district_to_tasengs.items():
    print(f"District {dc}: {len(tasengs)} tasengs")
    for tc, vs in list(tasengs.items())[:3]:
        print(f"  Taseng {tc}: {len(vs)} villages")

sql_parts = []

# ============================================================
# 1. RENAME edoc_tasseng -> edoc_taseng
# ============================================================
sql_parts.append("-- ============================================================")
sql_parts.append("-- 1. RENAME edoc_tasseng -> edoc_taseng")
sql_parts.append("-- ============================================================")
sql_parts.append("RENAME TABLE `edoc_tasseng` TO `edoc_taseng`;")
sql_parts.append("")

# ============================================================
# 2. UPDATE TABLE DEFINITIONS
# ============================================================
sql_parts.append("-- ============================================================")
sql_parts.append("-- 2. ALTER TABLES - fix charset, add ncode, fix constraints")
sql_parts.append("-- ============================================================")

# edoc_province - already correct, just ensure charset
sql_parts.append("ALTER TABLE `edoc_province` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")

# edoc_district - already correct, just ensure charset
sql_parts.append("ALTER TABLE `edoc_district` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")

# edoc_taseng (renamed) - change tas_code unique to composite, add ncode
sql_parts.append("ALTER TABLE `edoc_taseng` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
sql_parts.append("ALTER TABLE `edoc_taseng` DROP INDEX `idx_oc_tas_dist_code`;")
sql_parts.append("ALTER TABLE `edoc_taseng` ADD UNIQUE KEY `idx_oc_tas_dist_code` (`district_id`, `tas_code`);")
sql_parts.append("ALTER TABLE `edoc_taseng` ADD COLUMN `ncode` VARCHAR(10) NULL COMMENT 'New code: prov(2)+dist(2)+taseng(2)' AFTER `tas_code`, ADD UNIQUE KEY `idx_oc_tas_ncode` (`ncode`);")

# edoc_village - add ncode
sql_parts.append("ALTER TABLE `edoc_village` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
sql_parts.append("ALTER TABLE `edoc_village` ADD COLUMN `ncode` VARCHAR(10) NULL COMMENT 'New code: prov(2)+dist(2)+taseng(2)+village(2)' AFTER `vil_code`, ADD UNIQUE KEY `idx_oc_vil_ncode` (`ncode`);")

sql_parts.append("")

# ============================================================
# 3. CLEAR EXISTING DATA (mangled mojibake)
# ============================================================
sql_parts.append("-- ============================================================")
sql_parts.append("-- 3. CLEAR MANGLED DATA")
sql_parts.append("-- ============================================================")
sql_parts.append("SET FOREIGN_KEY_CHECKS = 0;")
sql_parts.append("TRUNCATE TABLE `edoc_village`;")
sql_parts.append("TRUNCATE TABLE `edoc_taseng`;")
sql_parts.append("TRUNCATE TABLE `edoc_district`;")
sql_parts.append("TRUNCATE TABLE `edoc_province`;")
sql_parts.append("SET FOREIGN_KEY_CHECKS = 1;")
sql_parts.append("")

# ============================================================
# 4. SEED PROVINCES (18 official)
# ============================================================
sql_parts.append("-- ============================================================")
sql_parts.append("-- 4. SEED PROVINCES")
sql_parts.append("-- ============================================================")
for p in provinces:
    name_lo = p['name_lo'].replace("'", "''")
    name_en = p['name_en'].replace("'", "''")
    sql_parts.append(f"INSERT INTO `edoc_province` (`prov_code`, `name_lo`, `name_en`, `off_id`) VALUES ('{p['prov_code']}', '{name_lo}', '{name_en}', '{p['prov_code']}');")

sql_parts.append("")

# ============================================================
# 5. SEED DISTRICTS (148 official)
# ============================================================
sql_parts.append("-- ============================================================")
sql_parts.append("-- 5. SEED DISTRICTS")
sql_parts.append("-- ============================================================")
for d in districts:
    name_lo = d['name_lo'].replace("'", "''")
    name_en = d['name_en'].replace("'", "''")
    sql_parts.append(f"INSERT INTO `edoc_district` (`province_id`, `dist_code`, `name_lo`, `name_en`, `off_id`) VALUES ((SELECT id FROM edoc_province WHERE prov_code='{d['province_code']}'), '{d['dist_code']}', '{name_lo}', '{name_en}', '{d['dist_code']}');")

sql_parts.append("")

# ============================================================
# 6. CREATE TASENGS (from village groupings)
# ============================================================
sql_parts.append("-- ============================================================")
sql_parts.append("-- 6. CREATE TASENGS (derived from village codes)")
sql_parts.append("-- ============================================================")

taseng_counter = 0
for dc, tasengs in sorted(district_to_tasengs.items()):
    for tc, vs in sorted(tasengs.items()):
        taseng_counter += 1
        # ncode = prov(2) + dist(2) + taseng(2)
        prov_code = dc[:2]
        ncode = f"{prov_code}{dc[2:4]}{tc}"
        # Create a reasonable Lao name based on the first village or district
        first_village = vs[0]
        taseng_name_lo = f"ຕາແສງ {first_village['name_lo'][:20]}"  # placeholder
        taseng_name_en = f"Taseng {tc} of {dc}"
        
        sql_parts.append(f"INSERT INTO `edoc_taseng` (`district_id`, `tas_code`, `ncode`, `name_lo`, `name_en`, `off_id`) VALUES ((SELECT id FROM edoc_district WHERE dist_code='{dc}'), '{tc}', '{ncode}', '{taseng_name_lo}', '{taseng_name_en}', '{ncode}');")

sql_parts.append("")

# ============================================================
# 7. SEED VILLAGES (8828 official) - link to correct taseng
# ============================================================
sql_parts.append("-- ============================================================")
sql_parts.append("-- 7. SEED VILLAGES (8828 official)")
sql_parts.append("-- ============================================================")

vil_count = 0
for v in villages:
    vil_count += 1
    if vil_count % 1000 == 0:
        print(f"  Processing village {vil_count}...")
    
    vc = v['vil_code']
    dc = v['district_code']
    prov_code = dc[:2]
    dist_code = dc[2:4]
    
    # Extract taseng code from village code (positions 4-5)
    taseng_code = vc[4:6] if len(vc) >= 6 else '01'
    
    # ncode for village: prov(2)+dist(2)+taseng(2)+village(2)
    # village part: last 2 digits of village_code (or running number)
    village_suffix = vc[6:8] if len(vc) >= 8 else f"{vil_count:02d}"
    ncode = f"{prov_code}{dist_code}{taseng_code}{village_suffix}"
    
    name_lo = v['name_lo'].replace("'", "''")
    name_en = v['name_en'].replace("'", "''")
    
    sql_parts.append(f"INSERT INTO `edoc_village` (`tasseng_id`, `vil_code`, `ncode`, `name_lo`, `name_en`, `off_id`) VALUES ((SELECT id FROM edoc_taseng WHERE ncode='{prov_code}{dist_code}{taseng_code}'), '{vc}', '{ncode}', '{name_lo}', '{name_en}', '{ncode}');")

print(f"Total villages: {vil_count}")

# Write SQL file
with open('D:/m-doc-taseng/docs/rebuild_hierarchy_official.sql', 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_parts))

print("Migration SQL written to D:/m-doc-taseng/docs/rebuild_hierarchy_official.sql")
print(f"Total SQL lines: {len(sql_parts)}")