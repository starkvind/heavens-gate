from pathlib import Path

path = Path('assets/css/hg-admin.css')
css = path.read_text(encoding='utf-8')

blocks = [
    (
        '/* ==== admin.gallery.css ==== */',
        '/* ==== admin.groups.css ==== */',
    ),
    (
        '/* ==== extracted: //raspberrypi/SSD Raspberry/html/hg/app/controllers/admin/admin_gallery.php ==== */',
        '/* ==== extracted: //raspberrypi/SSD Raspberry/html/hg/app/controllers/admin/admin_groups.php ==== */',
    ),
]

old_input = '.input, select { background:#0b1220; color:#e6e9ef; border:1px solid #1f2a44; padding:6px 8px; border-radius:8px; }'
new_input = '#galleryContainer .input, #galleryContainer select { background:#0b1220; color:#e6e9ef; border:1px solid #1f2a44; padding:6px 8px; border-radius:8px; }'
old_label = 'label { display:block; margin-bottom:4px; }'
new_label = '#galleryContainer label { display:block; margin-bottom:4px; }'

for start_marker, end_marker in blocks:
    if css.count(start_marker) != 1 or css.count(end_marker) != 1:
        raise SystemExit(f'unexpected marker count: {start_marker} / {end_marker}')
    start = css.index(start_marker)
    end = css.index(end_marker, start)
    block = css[start:end]
    if block.count(old_input) != 1:
        raise SystemExit(f'expected one leaking input/select rule in {start_marker}')
    if block.count(old_label) != 1:
        raise SystemExit(f'expected one leaking label rule in {start_marker}')
    patched = block.replace(old_input, new_input).replace(old_label, new_label)
    if old_input in patched or old_label in patched:
        raise SystemExit(f'gallery leak survives inside {start_marker}')
    if new_input not in patched or new_label not in patched:
        raise SystemExit(f'scoped gallery rule missing inside {start_marker}')
    css = css[:start] + patched + css[end:]

if css.count(old_input) != 0:
    raise SystemExit('unscoped gallery input/select rule survives')
if css.count(new_input) != 2 or css.count(new_label) != 2:
    raise SystemExit('expected both gallery copies to be scoped')
if css.count('{') != css.count('}'):
    raise SystemExit('unbalanced braces after admin leak cleanup')

path.write_text(css, encoding='utf-8')
