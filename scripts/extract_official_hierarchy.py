#!/usr/bin/env python3
"""
Extract official Lao hierarchy from tax_ets database and rebuild edoc_tasaeng tables correctly.
The tax_ets database has correct UTF-8 Lao text; edoc_tasaeng currently has double-encoded mojibake.
"""

import subprocess
import json

def run_query(db, query):
    """Run a MySQL query and return output as string."""
    cmd = [
        "C:\\Program Files\\PuTTY\\plink.exe", "-ssh", "apis@apis.com.la",
        "-pw", "apis@#2024",
        "-hostkey", "SHA256:JfYLE2aSz/xr0F7+GQN3g7I+hCQk9kKdT/4m9YcJ/ec",
        "-batch",
        f"mysql -uadmin -p'Sql_admin@#2024' {db} -e \"{query}\" 2>/dev/null"
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
    return result.stdout

def parse_tsv_output(output):
    """Parse tab-separated MySQL output into list of dicts."""
    lines = output.strip().split('\n')
    if not lines or len(lines) < 2:
        return []
    headers = lines[0].split('\t')
    rows = []
    for line in lines[1:]:
        values = line.split('\t')
        if len(values) == len(headers):
            rows.append(dict(zip(headers, values)))
    return rows

# 1. Get provinces from tax_ets
print("Fetching provinces...")
prov_output = run_query("tax_ets", "SELECT province_code, HEX(province_name_lao), province_name FROM provinces WHERE active=1 ORDER BY province_code;")
provinces = parse_tsv_output(prov_output)
for p in provinces:
    p['name_lo'] = bytes.fromhex(p['HEX(province_name_lao)']).decode('utf-8')
    p['name_en'] = p['province_name']
    p['prov_code'] = p['province_code']
    print(f"  {p['prov_code']}: {p['name_lo']} / {p['name_en']}")

# 2. Get districts from tax_ets
print("\nFetching districts...")
dist_output = run_query("tax_ets", """SELECT d.district_code, HEX(d.district_name_lao), d.district_name, p.province_code
FROM districts d
JOIN provinces p ON p.id = d.province_id
WHERE d.active=1 AND p.active=1
ORDER BY d.district_code;""")
districts = parse_tsv_output(dist_output)
for d in districts:
    d['name_lo'] = bytes.fromhex(d['HEX(d.district_name_lao)']).decode('utf-8')
    d['name_en'] = d['district_name']
    d['dist_code'] = d['district_code']
    d['province_code'] = d['province_code']
    print(f"  {d['dist_code']} (prov={d['province_code']}): {d['name_lo']} / {d['name_en']}")

# 3. Get villages from tax_ets
print("\nFetching villages...")
vill_output = run_query("tax_ets", """SELECT v.village_code, HEX(v.village_name_lao), v.village_name, v.district_code
FROM villages v
WHERE v.active=1
ORDER BY v.village_code;""")
villages = parse_tsv_output(vill_output)
for v in villages:
    v['name_lo'] = bytes.fromhex(v['HEX(v.village_name_lao)']).decode('utf-8')
    v['name_en'] = v['village_name']
    v['vil_code'] = v['village_code']
    v['district_code'] = v['district_code']
    print(f"  {v['vil_code']} (dist={v['district_code']}): {v['name_lo']} / {v['name_en']}")

print(f"\nTotal: {len(provinces)} provinces, {len(districts)} districts, {len(villages)} villages")

# Save to JSON for next step
import json
data = {
    'provinces': provinces,
    'districts': districts,
    'villages': villages
}
with open('D:/m-doc-taseng/docs/official_hierarchy.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=2)
print("\nSaved to D:/m-doc-taseng/docs/official_hierarchy.json")