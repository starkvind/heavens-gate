from pathlib import Path
import re

ROOT = Path('.')
TEXT_EXTS = {'.css', '.php', '.html', '.js', '.md'}
TARGETS = ('renglonMenu', 'marcoFotoBio', 'bioSheetPower', 'adm-admin-tile', 'hg-avatar-link')

files = [p for p in ROOT.rglob('*') if p.is_file() and p.suffix.lower() in TEXT_EXTS and '.git' not in p.parts]

print('=== EXACT LINK COLOR / TOKEN REFERENCES ===')
for p in files:
    text = p.read_text(encoding='utf-8', errors='ignore')
    for i, line in enumerate(text.splitlines(), 1):
        if '#44aaee' in line.lower() or 'var(--hg-link)' in line.lower():
            print(f'{p}:{i}: {line.strip()}')

print('\n=== TARGET SELECTOR / MARKUP CONTEXTS ===')
for p in files:
    text = p.read_text(encoding='utf-8', errors='ignore')
    lines = text.splitlines()
    hits = [i for i, line in enumerate(lines) if any(t in line for t in TARGETS)]
    if not hits:
        continue
    print(f'--- {p} ---')
    shown = set()
    for idx in hits:
        lo = max(0, idx - 3)
        hi = min(len(lines), idx + 5)
        key = (lo, hi)
        if key in shown:
            continue
        shown.add(key)
        for j in range(lo, hi):
            print(f'{j+1:5}: {lines[j]}')
        print()

print('\n=== SUSPICIOUS BORDER/OUTLINE DECLARATIONS ===')
border_re = re.compile(r'(?i)\b(?:border(?:-(?:top|right|bottom|left))?|outline)\s*:\s*([^;}{]+)')
for p in files:
    text = p.read_text(encoding='utf-8', errors='ignore')
    lines = text.splitlines()
    for i, line in enumerate(lines, 1):
        low = line.lower()
        m = border_re.search(line)
        if not m:
            continue
        val = m.group(1).strip().lower()
        suspicious = (
            'currentcolor' in val or 'inherit' in val or 'unset' in val or 'initial' in val or 'revert' in val
            or re.fullmatch(r'(?:\d+(?:\.\d+)?(?:px|em|rem)|thin|medium|thick)\s+(?:solid|dotted|dashed|double|groove|ridge|inset|outset)', val) is not None
        )
        if suspicious:
            print(f'{p}:{i}: {line.strip()}')

print('\n=== GENERIC ANCHOR RULES WITH BORDER/OUTLINE ===')
# Naive but useful: find CSS-ish rule blocks whose selector contains a bare anchor selector.
rule_re = re.compile(r'(?ms)([^{}]+)\{([^{}]*)\}')
for p in files:
    text = p.read_text(encoding='utf-8', errors='ignore')
    for m in rule_re.finditer(text):
        selector = ' '.join(m.group(1).split())
        body = m.group(2)
        if not re.search(r'(^|[\s,>+~])a(?=$|[\s:.,#\[>+~])', selector):
            continue
        if not re.search(r'(?i)\b(?:border|outline)', body):
            continue
        print(f'{p}: {selector} {{ {" ".join(body.split())[:500]} }}')

print('\n=== INLINE STYLE ATTRIBUTES TOUCHING BORDER/OUTLINE ===')
style_attr_re = re.compile(r'(?i)style\s*=\s*(["\'])(.*?)\1')
for p in files:
    if p.suffix.lower() not in {'.php', '.html'}:
        continue
    text = p.read_text(encoding='utf-8', errors='ignore')
    for i, line in enumerate(text.splitlines(), 1):
        for m in style_attr_re.finditer(line):
            val = m.group(2)
            if re.search(r'(?i)\b(?:border|outline)', val):
                print(f'{p}:{i}: {val}')

print('\n=== RAW <STYLE> EMITTERS ===')
for p in files:
    if p.suffix.lower() not in {'.php', '.html'}:
        continue
    text = p.read_text(encoding='utf-8', errors='ignore')
    for i, line in enumerate(text.splitlines(), 1):
        if '<style' in line.lower() or "echo '<style" in line.lower() or 'echo "<style' in line.lower():
            print(f'{p}:{i}: {line.strip()}')

print('\n=== DONE ===')
