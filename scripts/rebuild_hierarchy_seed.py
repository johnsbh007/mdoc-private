import os
EDOC_DB_PASS=os.environ.get('EDOC_DB_PASS','CHANGE_ME')
SSH_PASS=os.environ.get('EDOC_SSH_PASS','CHANGE_ME')
#!/usr/bin/env python3
"""
REGENERATE + RE-SEED the official Lao administrative hierarchy into edoc_tasaeng.

Single source of truth: docs/official_hierarchy.json (extracted from tax_ets on 2026-08-12).

WHAT IT DOES:
  1. Generates docs/seed_hierarchy_full.sql (provinces + districts + tasengs + villages)
     with `SET NAMES utf8mb4` baked in (CRITICAL for Lao UTF-8 — see lao-mojibake skill).
  2. Optionally pipes it to the server (--apply) to re-seed edoc_province/district/taseng/village.

USAGE:
  python scripts/rebuild_hierarchy_seed.py            # generate SQL only
  python scripts/rebuild_hierarchy_seed.py --apply    # generate + apply on apis.com.la

Coding scheme (ncode):
  province: 2 digits (e.g. 01)
  district: 4 digits = prov(2) + dist(2)
  taseng:   6 digits = prov(2) + dist(2) + taseng(2)  (derived from village-code grouping)
  village:  8 digits = prov(2) + dist(2) + taseng(2) + village(2)
  (vil_code / tas_code keep the ORIGINAL codes from tax_ets / village-code derivation)
"""

import sys
from collections import defaultdict

JSON_PATH = 'D:/m-doc-taseng/docs/official_hierarchy.json'
OUT_PATH = 'D:/m-doc-taseng/docs/seed_hierarchy_full.sql'

PLINK = [
    'C:\\Program Files\\PuTTY\\plink.exe', '-ssh', 'apis@apis.com.la',
    '-pw', SSH_PASS, '-hostkey',
    'SHA256:JfYLE2aSz/xr0F7+GQN3g7I+hCQk9kKdT/4m9YcJ/ec', '-batch',
]


def main():
    import json, subprocess
    with open(JSON_PATH, encoding='utf-8') as f:
        data = json.load(f)

    provinces = data['provinces']
    districts = data['districts']
    villages = data['villages']

    # Group villages into tasengs by district_code + 2-digit taseng slot from vil_code
    tas_map = defaultdict(lambda: defaultdict(list))
    for v in villages:
        dc = v['district_code']
        tc = v['vil_code'][4:6] if len(v['vil_code']) >= 6 else '01'
        tas_map[dc][tc].append(v)

    sql = []
    sql.append('-- Official Lao hierarchy seed for edoc_tasaeng (generated 2026-08-12)')
    sql.append('-- Sources: tax_ets.provinces/districts/villages + taseng grouping derived from village codes')
    sql.append('SET NAMES utf8mb4;')
    sql.append('SET FOREIGN_KEY_CHECKS = 0;')
    sql.append('TRUNCATE TABLE `edoc_village`;')
    sql.append('TRUNCATE TABLE `edoc_taseng`;')
    sql.append('TRUNCATE TABLE `edoc_district`;')
    sql.append('TRUNCATE TABLE `edoc_province`;')
    sql.append('SET FOREIGN_KEY_CHECKS = 1;')
    sql.append('')

    # Provinces
    sql.append('-- 18 provinces')
    for p in provinces:
        sql.append(f"INSERT INTO `edoc_province` (`prov_code`, `name_lo`, `name_en`, `off_id`) VALUES "
                   f"('{p['prov_code']}', '{p['name_lo'].replace(chr(39), chr(39)*2)}', "
                   f"'{p['name_en'].replace(chr(39), chr(39)*2)}', '{p['prov_code']}');")
    sql.append('')

    # Districts
    sql.append('-- %d districts' % len(districts))
    for d in districts:
        sql.append(f"INSERT INTO `edoc_district` (`province_id`, `dist_code`, `name_lo`, `name_en`, `off_id`) VALUES "
                   f"((SELECT id FROM edoc_province WHERE prov_code='{d['province_code']}'), "
                   f"'{d['dist_code']}', '{d['name_lo'].replace(chr(39), chr(39)*2)}', "
                   f"'{d['name_en'].replace(chr(39), chr(39)*2)}', '{d['dist_code']}');")
    sql.append('')

    # Tasengs
    tas_count = sum(len(t) for t in tas_map.values())
    sql.append('-- %d tasengs (ncode = 6 digits)' % tas_count)
    for dc in sorted(tas_map):
        for tc in sorted(tas_map[dc]):
            vs = tas_map[dc][tc]
            ncode = f"{dc[:2]}{dc[2:4]}{tc}"
            first = vs[0]
            name_lo = 'ຕາແສງ ' + first['name_lo'][:30]
            name_en = f"Taseng {tc} ({dc})"
            sql.append(f"INSERT INTO `edoc_taseng` (`district_id`, `tas_code`, `ncode`, `name_lo`, `name_en`, `off_id`) VALUES "
                       f"((SELECT id FROM edoc_district WHERE dist_code='{dc}'), '{tc}', '{ncode}', "
                       f"'{name_lo.replace(chr(39), chr(39)*2)}', '{name_en}', '{ncode}');")
    sql.append('')

    # Villages
    sql.append('-- %d villages (ncode = 8 digits)' % len(villages))
    for i, v in enumerate(villages, 1):
        dc = v['district_code']
        vc = v['vil_code']
        tc = vc[4:6] if len(vc) >= 6 else '01'
        suf = vc[6:8] if len(vc) >= 8 else f'{i:02d}'
        ncode = f"{dc[:2]}{dc[2:4]}{tc}{suf}"
        sql.append(f"INSERT INTO `edoc_village` (`tasseng_id`, `vil_code`, `ncode`, `name_lo`, `name_en`, `off_id`) VALUES "
                   f"((SELECT id FROM edoc_taseng WHERE ncode='{dc[:2]}{dc[2:4]}{tc}'), '{vc}', '{ncode}', "
                   f"'{v['name_lo'].replace(chr(39), chr(39)*2)}', '{v['name_en'].replace(chr(39), chr(39)*2)}', '{ncode}');")

    with open(OUT_PATH, 'w', encoding='utf-8') as f:
        f.write('\n'.join(sql))
    print(f'Generated {OUT_PATH}: {len(sql)} lines')

    if '--apply' in sys.argv:
        import subprocess as sp
        print('Applying on apis.com.la ...')
        p = sp.run(PLINK + ['mysql', '-uadmin', EDOC_DB_PASS, 'edoc_tasaeng'],
                   input='\n'.join(sql), capture_output=True, text=True, timeout=600)
        print(p.stdout[-2000:] if p.stdout else '(no stdout)')
        print(p.stderr[-2000:] if p.stderr else '(no stderr)')
        print('Applied. Verify with: SELECT COUNT(*) FROM each hierarchy table;')


if __name__ == '__main__':
    main()