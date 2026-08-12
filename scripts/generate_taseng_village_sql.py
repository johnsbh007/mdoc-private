#!/usr/bin/env python3
"""
Generate and execute INSERT statements for tasengs and villages.
Tasengs are derived from village code groupings (first 6 digits: PPDDTT).
"""

import json
import subprocess

with open('D:/m-doc-taseng/docs/official_hierarchy.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

districts = data['districts']
villages = data['villages']

# Build district -> taseng mapping from village codes
from collections import defaultdict

district_to_tasengs = defaultdict(lambda: defaultdict(list))
for v in villages:
    vc = v['vil_code']
    dc = v['district_code']
    if len(vc) >= 6:
        taseng_code = vc[4:6]  # positions 4-5 (0-indexed) = taseng 2 digits
        district_to_tasengs[dc][taseng_code].append(v)

print(f"Tasengs to create: {sum(len(t) for t in district_to_tasengs.values())}")

# Generate taseng INSERT statements
taseng_inserts = []
taseng_count = 0
for dc, tasengs in sorted(district_to_tasengs.items()):
    for tc, vs in sorted(tasengs.items()):
        taseng_count += 1
        prov_code = dc[:2]
        ncode = f"{prov_code}{dc[2:4]}{tc}"
        # Create Lao name: use first village as reference
        first_v = vs[0]
        taseng_name_lo = f"ຕາແສງ {first_v['name_lo'][:30]}"
        taseng_name_en = f"Taseng {tc} ({dc})"
        
        taseng_inserts.append(f"INSERT INTO `edoc_taseng` (`district_id`, `tas_code`, `ncode`, `name_lo`, `name_en`, `off_id`) VALUES ((SELECT id FROM edoc_district WHERE dist_code='{dc}'), '{tc}', '{ncode}', '{taseng_name_lo}', '{taseng_name_en}', '{ncode}');")

print(f"Generated {taseng_count} taseng INSERTs")

# Write taseng SQL
with open('D:/m-doc-taseng/docs/seed_tasengs.sql', 'w', encoding='utf-8') as f:
    f.write('\n'.join(taseng_inserts))

# Now generate village INSERTs in batches
vil_inserts = []
vil_count = 0
for v in villages:
    vil_count += 1
    vc = v['vil_code']
    dc = v['district_code']
    prov_code = dc[:2]
    dist_code = dc[2:4]
    
    taseng_code = vc[4:6] if len(vc) >= 6 else '01'
    village_suffix = vc[6:8] if len(vc) >= 8 else f"{vil_count:02d}"
    ncode = f"{prov_code}{dist_code}{taseng_code}{village_suffix}"
    
    name_lo = v['name_lo'].replace("'", "''")
    name_en = v['name_en'].replace("'", "''")
    
    vil_inserts.append(f"INSERT INTO `edoc_village` (`tasseng_id`, `vil_code`, `ncode`, `name_lo`, `name_en`, `off_id`) VALUES ((SELECT id FROM edoc_taseng WHERE ncode='{prov_code}{dist_code}{taseng_code}'), '{vc}', '{ncode}', '{name_lo}', '{name_en}', '{ncode}');")
    
    if vil_count % 1000 == 0:
        print(f"  Generated {vil_count} village INSERTs...")

print(f"Total villages: {vil_count}")

# Write village SQL in chunks (too large for single file execution)
chunk_size = 2000
for i in range(0, len(vil_inserts), chunk_size):
    chunk = vil_inserts[i:i+chunk_size]
    chunk_num = i // chunk_size + 1
    with open(f'D:/m-doc-taseng/docs/seed_villages_{chunk_num}.sql', 'w', encoding='utf-8') as f:
        f.write('\n'.join(chunk))
    print(f"Written chunk {chunk_num}: {len(chunk)} villages")

print("All SQL files generated")