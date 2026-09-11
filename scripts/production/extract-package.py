"""Extract only the immutable CMS payload. No paths outside the fresh destination."""
import pathlib
import stat
import sys
import zipfile

source, target = map(pathlib.Path, sys.argv[1:])
if not target.is_dir() or any(target.iterdir()):
    raise ValueError("destination must be an empty directory")
with zipfile.ZipFile(source) as archive:
    seen = set()
    total = 0
    for entry in archive.infolist():
        name = entry.orig_filename
        path = pathlib.PurePosixPath(name)
        total += entry.file_size
        if (not name or "\0" in name or name.startswith(("/", "\\")) or "\\" in name or ":" in name
                or ".." in path.parts or name in seen
                or str(path) != name.rstrip('/') or len(archive.infolist()) > 20000
                or path.parts[0] not in {"publication.json", "content-manifest.json", "media"}
                or stat.S_ISLNK(entry.external_attr >> 16) or total > 2 * 1024**3):
            raise ValueError("unsafe publication archive")
        seen.add(name)
    archive.extractall(target)
