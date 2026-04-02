import xml.etree.ElementTree as ET
from pathlib import Path

root = ET.parse(Path('coverage.xml')).getroot()

total_elems = total_cov = 0
packages = []
by_namespace = {
    "Domain": {"elems": 0, "cov": 0},
    "Chat": {"elems": 0, "cov": 0},
    "Billing": {"elems": 0, "cov": 0},
    "Auth": {"elems": 0, "cov": 0},
    "CRM": {"elems": 0, "cov": 0},
}

for pkg in root.iter('package'):
    pkg_elems = pkg_cov = 0
    for file_node in pkg.iter('file'):
        filename = (file_node.get('name') or '').replace('\\', '/')
        metrics = file_node.find('metrics')
        if metrics is None:
            continue
        elems = int(metrics.attrib.get('elements', '0'))
        cov = int(metrics.attrib.get('coveredelements', '0'))
        pkg_elems += elems
        pkg_cov += cov

        if '/src/Domain/' in filename:
            by_namespace["Domain"]["elems"] += elems
            by_namespace["Domain"]["cov"] += cov
        if '/src/Domain/Chat/' in filename:
            by_namespace["Chat"]["elems"] += elems
            by_namespace["Chat"]["cov"] += cov
        if '/src/Domain/Billing/' in filename:
            by_namespace["Billing"]["elems"] += elems
            by_namespace["Billing"]["cov"] += cov
        if '/src/Domain/Auth/' in filename:
            by_namespace["Auth"]["elems"] += elems
            by_namespace["Auth"]["cov"] += cov
        if '/src/Domain/CRM/' in filename:
            by_namespace["CRM"]["elems"] += elems
            by_namespace["CRM"]["cov"] += cov

    total_elems += pkg_elems
    total_cov += pkg_cov
    rate = 0 if pkg_elems == 0 else pkg_cov / pkg_elems
    packages.append((rate, pkg.get('name'), pkg_cov, pkg_elems))

pkg_sorted = sorted(packages, key=lambda x: x[0])
total_rate = total_cov / total_elems if total_elems else 0

print(f"Overall coverage: {total_rate*100:.1f}% ({total_cov}/{total_elems})")

print("\nBy namespace:")
for name, data in by_namespace.items():
    elems = data["elems"]
    cov = data["cov"]
    rate = cov / elems * 100 if elems else 0
    print(f"- {name}: {rate:.1f}% ({cov}/{elems})")

print("\nLowest packages:")
for rate, name, cov, elems in pkg_sorted[:10]:
    print(f"{rate*100:5.1f}% ({cov}/{elems}) {name}")
