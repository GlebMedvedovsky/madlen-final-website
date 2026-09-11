import io
import pathlib
import subprocess
import sys
import tempfile
import zipfile

script = pathlib.Path(__file__).resolve().parents[1] / 'production' / 'extract-package.py'
with tempfile.TemporaryDirectory(prefix='madlen-extract-qa-') as temporary:
    root = pathlib.Path(temporary)
    cases = [('valid', 'publication.json', True), ('parent', '../escape', False),
             ('absolute', '/escape', False), ('backslash', 'media\\escape', False),
             ('private', '.env', False), ('link', 'media/link', False)]
    for label, name, expected in cases:
        archive = root / (label + '.zip')
        with zipfile.ZipFile(archive, 'w') as z:
            entry = zipfile.ZipInfo(name)
            entry.filename = entry.orig_filename = name
            if label == 'link':
                entry.create_system = 3
                entry.external_attr = 0o120777 << 16
            z.writestr(entry, '{}')
        target = root / label
        target.mkdir()
        result = subprocess.run([sys.executable, str(script), str(archive), str(target)], capture_output=True)
        assert (result.returncode == 0) == expected, (label, result.stderr.decode())
    assert not (root / 'escape').exists()
print('PASS: real ZIP extraction, traversal/absolute/backslash/private/symlink rejection; isolated temp directory')
