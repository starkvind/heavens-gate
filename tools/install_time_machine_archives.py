#!/usr/bin/env python3
"""Install static display copies of both archived 2007 Heaven's Gate sites.

The original ZIPs are read-only; this program changes only their viewing copies.
"""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import zipfile

ALLOWED = {'.html', '.css', '.js', '.gif', '.jpg', '.jpeg', '.png', '.mid', '.doc'}
MAX_MEMBER = 10 * 1024 * 1024
MAX_TOTAL = 40 * 1024 * 1024


def file_hash(path):
    h = hashlib.sha256()
    with open(path, 'rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def convert(raw, rel, version):
    if not rel.endswith(('.html', '.js')):
        return raw

    def replace_popup(match):
        width = '400' if b'width=400' in match.group(0) else '500'
        code = (
            "function popUp(URL) {\n"
            "  window.open(URL, '_blank', 'toolbar=0,scrollbars=1,location=0,"
            "statusbar=0,menubar=0,resizable=1,width=" + width + ",height=" + width + "');\n"
            "}"
        )
        return code.encode('ascii')

    raw = re.sub(rb'function\s+popUp\s*\(URL\)\s*\{.*?\}', replace_popup, raw, flags=re.DOTALL)
    raw = re.sub(
        rb'<script\s+language="javascript">\s*window\.onload\s*=\s*new Function\(null\)</script>',
        b'', raw, flags=re.IGNORECASE
    )
    if version == '2007' and rel == 'hg/index.html':
        raw = re.sub(
            rb'<\?php.*?\?>', b'<!-- Bloque PHP historico inerte en copia estatica. -->',
            raw, flags=re.DOTALL | re.IGNORECASE
        )
    if version == 'v1.2':
        raw = raw.replace(b'clasificados.php', b'clasificados.html')
        if rel == 'menu.html':
            # Modern browsers must address the image element explicitly: IMG01/02
            # are also legacy global variables containing sprite URLs.
            raw = re.sub(rb'\b(imgover|imgout)\((IMG0[1-7])\)',
                         lambda m: m.group(1) + b"(document.images.namedItem('" + m.group(2) + b"'))", raw)

    try:
        text = raw.decode('utf-8')
    except UnicodeDecodeError:
        text = raw.decode('cp1252', errors='replace')
    if rel.endswith('.html'):
        text = re.sub(
            r'(<head\b[^>]*>)', r'\1\n<meta charset="UTF-8">',
            text, count=1, flags=re.IGNORECASE
        )
        # An earlier charset declaration would override the inserted one.
        text = re.sub(
            r'<meta\s+[^>]*(?:charset\s*=\s*["\x27]?[^\s"\x27>]+)[^>]*>',
            lambda match: '' if 'UTF-8' not in match.group(0) else match.group(0),
            text, flags=re.IGNORECASE
        )
    return text.encode('utf-8')


def install_zip(path, prefix, version, destination):
    if destination.exists() or destination.is_symlink():
        raise ValueError('Refusing to overwrite: ' + str(destination))
    destination.mkdir(parents=True)
    count = 0
    total = 0
    try:
        with zipfile.ZipFile(path) as archive:
            for member in archive.infolist():
                if member.is_dir():
                    continue
                full = PurePosixPath(member.filename)
                if (full.is_absolute() or '..' in full.parts or '\\' in member.filename
                        or stat.S_ISLNK(member.external_attr >> 16)
                        or not member.filename.startswith(prefix)):
                    raise ValueError('Unsafe/unknown archive member: ' + member.filename)
                rel = member.filename[len(prefix):]
                if not rel:
                    continue
                if version == 'v1.2' and rel == 'secciones/documentacion/clasificados.php':
                    rel = 'secciones/documentacion/clasificados.html'
                if PurePosixPath(rel).suffix.lower() not in ALLOWED:
                    continue
                if member.file_size > MAX_MEMBER:
                    raise ValueError('Oversized file: ' + member.filename)
                total += member.file_size
                if total > MAX_TOTAL:
                    raise ValueError('Oversized ZIP archive')
                content = convert(archive.read(member), rel, version)
                target = destination / rel
                target.parent.mkdir(parents=True, exist_ok=True)
                if target.is_symlink():
                    raise ValueError('Unexpected symlink: ' + str(target))
                target.write_bytes(content)
                count += 1
        if not (destination / 'index.html').is_file():
            raise ValueError('Missing index.html: ' + version)
    except Exception:
        shutil.rmtree(destination)
        raise
    print(version, ':', count, 'static files')


def main():
    cli = argparse.ArgumentParser(description=__doc__)
    cli.add_argument('--original', type=Path, required=True, help='heavens.zip')
    cli.add_argument('--v12', type=Path, required=True, help='heavens v1.2.zip')
    cli.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    options = cli.parse_args()

    target = options.root.resolve() / 'public' / 'time-machine'
    if not (target / '.htaccess').is_file():
        cli.error('First deploy the production code containing public/time-machine/.htaccess')
    if not options.original.is_file() or not options.v12.is_file():
        cli.error('Both original ZIP files must exist')

    checksums = {'2007': file_hash(options.original), 'v1.2': file_hash(options.v12)}
    install_zip(options.original, 'heavens/', '2007', target / '2007')
    install_zip(options.v12, 'heavens v1.2/', 'v1.2', target / 'v1.2')
    metadata = {'source_sha256': checksums, 'note': 'Original source ZIPs unmodified'}
    (target / 'installation-manifest.json').write_text(json.dumps(metadata, indent=2) + '\n', encoding='utf-8')
    print('Ready: /public/time-machine/2007/index.html and /public/time-machine/v1.2/index.html')


if __name__ == '__main__':
    main()
