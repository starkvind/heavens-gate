#!/usr/bin/env python3
from collections import defaultdict
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]

runtime_paths = []
for base in (ROOT / 'app', ROOT / 'api'):
    if base.exists():
        runtime_paths.extend(base.rglob('*.php'))
if (ROOT / 'index.php').exists():
    runtime_paths.append(ROOT / 'index.php')
runtime_paths = sorted(set(runtime_paths))

ci_paths = []
ci_root = ROOT / '.github' / 'ci'
if ci_root.exists():
    ci_paths = sorted(
        p for p in ci_root.rglob('*')
        if p.is_file() and p.suffix.lower() in {'.php', '.py', '.js'}
    )

runtime_text = {
    p: p.read_text(encoding='utf-8', errors='replace')
    for p in runtime_paths
}
ci_text = {
    p: p.read_text(encoding='utf-8', errors='replace')
    for p in ci_paths
}

helper_files = sorted((ROOT / 'app' / 'helpers').glob('*.php'))
partial_files = sorted((ROOT / 'app' / 'partials').rglob('*.php'))
domain_files = sorted((ROOT / 'app' / 'domains').rglob('*.php'))
owned_files = sorted(set(helper_files + partial_files + domain_files))

def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()

def file_refs(target: Path, haystack: dict[Path, str]) -> list[str]:
    relative = rel(target)
    basename = target.name
    refs = []
    for path, text in haystack.items():
        if path == target:
            continue
        if relative in text or basename in text:
            refs.append(rel(path))
    return sorted(refs)

unreferenced_helpers = []
unreferenced_partials = []
ci_only_files = []

for target in helper_files + partial_files:
    runtime_refs = file_refs(target, runtime_text)
    ci_refs = file_refs(target, ci_text)
    if not runtime_refs:
        item = (rel(target), ci_refs)
        if target in helper_files:
            unreferenced_helpers.append(item)
        else:
            unreferenced_partials.append(item)
        if ci_refs:
            ci_only_files.append((rel(target), ci_refs))

decl_re = re.compile(r'(?m)^\s*(?:if\s*\([^\n]*\)\s*)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(')
declarations = defaultdict(list)
for path in owned_files:
    text = runtime_text.get(path, path.read_text(encoding='utf-8', errors='replace'))
    for match in decl_re.finditer(text):
        line = text.count('\n', 0, match.start()) + 1
        declarations[match.group(1)].append((path, line))

duplicates = {
    name: locs for name, locs in declarations.items()
    if len(locs) > 1
}

def semantic_ref_count(name: str, corpus: dict[Path, str], declaration_locs=None) -> tuple[int, list[str]]:
    direct = re.compile(r'\b' + re.escape(name) + r'\s*\(')
    quoted = re.compile(r'([\'"])' + re.escape(name) + r'\1')
    guard = re.compile(r'\b(?:function_exists|is_callable)\s*\(\s*([\'"])' + re.escape(name) + r'\1\s*\)')
    count = 0
    files = []
    decl_by_path = defaultdict(int)
    for path, _line in declaration_locs or []:
        decl_by_path[path] += 1
    for path, text in corpus.items():
        n = len(direct.findall(text)) + len(quoted.findall(text)) - len(guard.findall(text))
        n -= decl_by_path.get(path, 0)
        if n > 0:
            count += n
            files.append(rel(path))
    return count, sorted(set(files))

zero_runtime_symbols = []
ci_only_symbols = []
for name, locs in sorted(declarations.items()):
    runtime_count, runtime_files = semantic_ref_count(name, runtime_text, locs)
    ci_count, ci_files = semantic_ref_count(name, ci_text, [])
    if runtime_count == 0:
        item = {
            'name': name,
            'decls': [f'{rel(p)}:{line}' for p, line in locs],
            'ci_refs': ci_files,
        }
        zero_runtime_symbols.append(item)
        if ci_count > 0:
            ci_only_symbols.append(item)

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
