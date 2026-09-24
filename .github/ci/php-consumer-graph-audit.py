#!/usr/bin/env python3
from collections import Counter, defaultdict
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

runtime_paths = []
for base in (ROOT / 'app', ROOT / 'api'):
    if base.exists():
        runtime_paths.extend(base.rglob('*.php'))
if (ROOT / 'index.php').exists():
    runtime_paths.append(ROOT / 'index.php')
runtime_paths = sorted(set(runtime_paths))

ci_root = ROOT / '.github' / 'ci'
ci_paths = sorted(
    p for p in ci_root.rglob('*')
    if p.is_file() and p.suffix.lower() in {'.php', '.py', '.js'}
) if ci_root.exists() else []

runtime_text = {p: p.read_text(encoding='utf-8', errors='replace') for p in runtime_paths}
ci_text = {p: p.read_text(encoding='utf-8', errors='replace') for p in ci_paths}

php_block_re = re.compile(r'<\?php(.*?)(?:\?>|$)', re.S)

def php_only(text: str) -> str:
    blocks = php_block_re.findall(text)
    return '\n'.join(blocks)

runtime_php_text = {p: php_only(text) for p, text in runtime_text.items()}
ci_php_text = {p: php_only(text) for p, text in ci_text.items()}

helper_files = sorted((ROOT / 'app' / 'helpers').glob('*.php'))
partial_files = sorted((ROOT / 'app' / 'partials').rglob('*.php'))
domain_files = sorted((ROOT / 'app' / 'domains').rglob('*.php'))
owned_files = sorted(set(helper_files + partial_files + domain_files))

def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()

# File-level consumer map. Filename/path references are conservative:
# zero references is strong evidence; positive references are not proof of liveness.
file_targets = helper_files + partial_files
runtime_file_refs = {p: [] for p in file_targets}
ci_file_refs = {p: [] for p in file_targets}
for source, text in runtime_text.items():
    for target in file_targets:
        if source == target:
            continue
        if rel(target) in text or target.name in text:
            runtime_file_refs[target].append(rel(source))
for source, text in ci_text.items():
    for target in file_targets:
        if rel(target) in text or target.name in text:
            ci_file_refs[target].append(rel(source))

unreferenced_helpers = [
    (rel(p), sorted(set(ci_file_refs[p])))
    for p in helper_files if not runtime_file_refs[p]
]
unreferenced_partials = [
    (rel(p), sorted(set(ci_file_refs[p])))
    for p in partial_files if not runtime_file_refs[p]
]

# Symbol declarations in helpers/partials/domains.
decl_re = re.compile(r'(?m)^\s*(?:if\s*\([^\n]*\)\s*)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(')
declarations = defaultdict(list)
for path in owned_files:
    text = runtime_php_text.get(path, php_only(path.read_text(encoding='utf-8', errors='replace')))
    for match in decl_re.finditer(text):
        line = text.count('\n', 0, match.start()) + 1
        declarations[match.group(1)].append((path, line))

names = set(declarations)
call_re = re.compile(r'\b([A-Za-z_][A-Za-z0-9_]*)\s*\(')
quoted_re = re.compile(r'([\'"])([A-Za-z_][A-Za-z0-9_]*)\1')
guard_re = re.compile(r'\b(?:function_exists|is_callable)\s*\(\s*([\'"])([A-Za-z_][A-Za-z0-9_]*)\1\s*\)')

def build_symbol_index(corpus, subtract_declarations):
    counts = Counter()
    files = defaultdict(set)
    for path, text in corpus.items():
        local = Counter(m.group(1) for m in call_re.finditer(text) if m.group(1) in names)
        local.update(m.group(2) for m in quoted_re.finditer(text) if m.group(2) in names)
        for m in guard_re.finditer(text):
            if m.group(2) in names:
                local[m.group(2)] -= 1
        if subtract_declarations:
            for name, locs in declarations.items():
                local[name] -= sum(1 for p, _ in locs if p == path)
        for name, count in local.items():
            if count > 0:
                counts[name] += count
                files[name].add(rel(path))
    return counts, files

runtime_counts, runtime_ref_files = build_symbol_index(runtime_php_text, True)
ci_counts, ci_ref_files = build_symbol_index(ci_php_text, False)

duplicates = {name: locs for name, locs in declarations.items() if len(locs) > 1}
zero_runtime_symbols = []
for name, locs in sorted(declarations.items()):
    if runtime_counts[name] == 0:
        zero_runtime_symbols.append({
            'name': name,
            'decls': [f'{rel(p)}:{line}' for p, line in locs],
            'ci_refs': sorted(ci_ref_files.get(name, set())),
        })

ci_only_symbols = [item for item in zero_runtime_symbols if ci_counts[item['name']] > 0]

print('# Phase 7.2 helper/partial/symbol consumer graph')
print(f'Runtime PHP files scanned: {len(runtime_paths)}')
print(f'Helpers scanned: {len(helper_files)}')
print(f'Partials scanned: {len(partial_files)}')
print(f'Domain PHP files scanned: {len(domain_files)}')
print(f'Named functions declared in helpers/partials/domains: {len(declarations)}')
print(f'Duplicate named function symbols: {len(duplicates)}')
print(f'Helpers with zero runtime filename/path consumers: {len(unreferenced_helpers)}')
print(f'Partials with zero runtime filename/path consumers: {len(unreferenced_partials)}')
print(f'Named functions with zero runtime semantic refs: {len(zero_runtime_symbols)}')
print(f'Zero-runtime functions referenced only by CI: {len(ci_only_symbols)}')

print('\n## UNREFERENCED_HELPERS')
for path, ci_refs in unreferenced_helpers:
    print(f'{path} | CI={",".join(ci_refs) if ci_refs else "-"}')

print('\n## UNREFERENCED_PARTIALS')
for path, ci_refs in unreferenced_partials:
    print(f'{path} | CI={",".join(ci_refs) if ci_refs else "-"}')

print('\n## DUPLICATE_SYMBOLS')
for name, locs in sorted(duplicates.items()):
    print(name + ' | ' + ', '.join(f'{rel(p)}:{line}' for p, line in locs))

print('\n## ZERO_RUNTIME_SYMBOLS')
for item in zero_runtime_symbols:
    print(
        item['name'] + ' | DECL=' + ','.join(item['decls'])
        + ' | CI=' + (','.join(item['ci_refs']) if item['ci_refs'] else '-')
    )

errors = []
if unreferenced_helpers:
    errors.append(f'{len(unreferenced_helpers)} helper file(s) have zero runtime consumers')
if unreferenced_partials:
    errors.append(f'{len(unreferenced_partials)} partial file(s) have zero runtime consumers')
if duplicates:
    errors.append(f'{len(duplicates)} duplicate named PHP function symbol(s) remain')
if zero_runtime_symbols:
    errors.append(f'{len(zero_runtime_symbols)} named PHP function(s) have zero runtime semantic refs')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Phase 7.2 consumer graph audit: PASS')
