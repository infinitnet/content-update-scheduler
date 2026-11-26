#!/usr/bin/env python3
"""Deterministic WordPress plugin ZIP builder and verifier (stdlib only).

Used by .github/workflows/package-plugin.yml. Runs the same way locally:

  python3 -I .github/scripts/build-plugin-zip.py version --main <file>
  python3 -I .github/scripts/build-plugin-zip.py build --slug <slug> --main <file> \
      --include <path>[=<dest>] [--include ...] [--constant NAME] [--out dist]

`build` packs only git-tracked files under the allowlisted paths into
dist/<slug>-<version>.zip with one top-level <slug>/ directory, fixed
timestamps and permissions, writes an identical dist/<slug>.zip, then reopens
the archive and checks: single top-level folder, main file present, header
version (plus the optional version constant and readme.txt Stable tag) equal
to the expected version, and no junk files.
"""
import argparse
import hashlib
import os
import re
import subprocess
import sys
import zipfile
from pathlib import Path

FIXED_DATE = (1980, 1, 1, 0, 0, 0)
HEADER_RE = re.compile(r"^[ \t/*#]*Version:[ \t]*(\S+)[ \t]*$", re.M)
STABLE_RE = re.compile(r"^[ \t]*Stable tag:[ \t]*(\S+)[ \t]*$", re.M | re.I)
# Never ship these, even when they sit inside an allowlisted directory.
JUNK_RE = re.compile(
    r"(^|/)(\.[^/]+|__MACOSX|Thumbs\.db|desktop\.ini)(/|$)"
    r"|(^|/)(tests?|node_modules|vendor|graphify-out|graphify|docs|dist|\.github)(/|$)"
    r"|(^|/)(AGENTS|CLAUDE|GEMINI|NOTES|TODO|todo)\.md$"
    r"|(^|/)(composer\.(json|lock)|package(-lock)?\.json|phpunit\.xml(\.dist)?|phpcs\.xml(\.dist)?|phpstan\.neon(\.dist)?)$"
    r"|\.(zip|log|sql|sqlite|bak|orig|swp)$"
)


def fail(msg):
    print(f"ERROR: {msg}", file=sys.stderr)
    raise SystemExit(1)


def header_version(text):
    m = HEADER_RE.search(text[:8192])
    return m.group(1) if m else None


def constant_version(text, name):
    m = re.search(r"define\(\s*['\"]" + re.escape(name) + r"['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)", text)
    return m.group(1) if m else None


def tracked_files():
    try:
        out = subprocess.run(["git", "ls-files", "-z"], capture_output=True, check=True)
    except (OSError, subprocess.CalledProcessError):
        fail("must run inside a git checkout (only tracked files are packaged)")
    return sorted(f for f in out.stdout.decode("utf-8").split("\0") if f)


def write_output(**values):
    path = os.environ.get("GITHUB_OUTPUT")
    if path:
        with open(path, "a", encoding="utf-8") as fh:
            for k, v in values.items():
                fh.write(f"{k}={v}\n")


def zinfo(name, is_dir):
    zi = zipfile.ZipInfo(name + "/" if is_dir else name, FIXED_DATE)
    zi.create_system = 3
    zi.external_attr = ((0o40755 << 16) | 0x10) if is_dir else (0o100644 << 16)
    zi.compress_type = zipfile.ZIP_STORED if is_dir else zipfile.ZIP_DEFLATED
    return zi


def collect(includes):
    """Map archive-relative path -> source path for every tracked allowlisted file."""
    tracked = tracked_files()
    mapping = {}
    for spec in includes:
        src, _, dest = spec.partition("=")
        src = src.strip("/")
        dest = (dest or src).strip("/")
        hits = [f for f in tracked if f == src or f.startswith(src + "/")]
        if not hits:
            fail(f"allowlisted path has no tracked files: {src}")
        for f in hits:
            rel = dest + f[len(src):]
            if JUNK_RE.search(rel):
                print(f"skip junk: {f}")
                continue
            mapping[rel] = f
    return mapping


def verify(path, slug, main_rel, version, constant):
    errors = []
    with zipfile.ZipFile(path) as zf:
        bad = zf.testzip()
        if bad:
            errors.append(f"corrupt entry {bad}")
        names = zf.namelist()
        tops = {n.split("/", 1)[0] for n in names}
        if tops != {slug}:
            errors.append(f"top-level entries must be only {slug}/, got {sorted(tops)}")
        files = [n for n in names if not n.endswith("/")]
        for n in files:
            rel = n.split("/", 1)[1] if "/" in n else n
            if JUNK_RE.search(rel):
                errors.append(f"junk file in ZIP: {n}")
        main_name = f"{slug}/{main_rel}"
        if main_name not in names:
            errors.append(f"main file missing: {main_name}")
        else:
            text = zf.read(main_name).decode("utf-8", "replace")
            if header_version(text) != version:
                errors.append(f"header version {header_version(text)} != {version}")
            if constant and constant_version(text, constant) != version:
                errors.append(f"{constant} {constant_version(text, constant)} != {version}")
        readme = f"{slug}/readme.txt"
        if readme in names:
            m = STABLE_RE.search(zf.read(readme).decode("utf-8", "replace"))
            if m and m.group(1).lower() != "trunk" and m.group(1) != version:
                errors.append(f"readme.txt Stable tag {m.group(1)} != {version}")
    if errors:
        for e in errors:
            print(f"verify: {e}", file=sys.stderr)
        fail(f"{path} failed verification")
    print(f"verified {path}: {len(files)} files, single top-level {slug}/, version {version}")


def cmd_version(args):
    text = Path(args.main).read_text(encoding="utf-8")
    version = header_version(text)
    if not version:
        fail(f"no Version header in {args.main}")
    if args.constant and constant_version(text, args.constant) != version:
        fail(f"{args.constant}={constant_version(text, args.constant)} does not match header {version}")
    print(version)
    write_output(version=version)


def cmd_build(args):
    text = Path(args.main).read_text(encoding="utf-8")
    version = header_version(text)
    if not version:
        fail(f"no Version header in {args.main}")
    mapping = collect(args.include)
    main_rel = args.main_dest or args.main
    if main_rel not in mapping:
        fail(f"main file {main_rel} is not in the allowlist")

    entries = {args.slug: None}
    for rel, src in mapping.items():
        parts = rel.split("/")
        for i in range(1, len(parts)):
            entries[f"{args.slug}/" + "/".join(parts[:i])] = None
        entries[f"{args.slug}/{rel}"] = src

    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    versioned = out / f"{args.slug}-{version}.zip"
    with zipfile.ZipFile(versioned, "w") as zf:
        for name in sorted(entries):
            src = entries[name]
            if src is None:
                zf.writestr(zinfo(name, True), b"")
            else:
                zf.writestr(zinfo(name, False), Path(src).read_bytes(),
                            compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    stable = out / f"{args.slug}.zip"
    stable.write_bytes(versioned.read_bytes())

    for name in sorted(entries):
        if entries[name] is not None:
            print(f"  {name}")
    verify(versioned, args.slug, main_rel, version, args.constant)
    verify(stable, args.slug, main_rel, version, args.constant)
    digest = hashlib.sha256(versioned.read_bytes()).hexdigest()
    print(f"sha256 {digest}  {versioned.name}")
    write_output(version=version, versioned=str(versioned), stable=str(stable), sha256=digest)


def main():
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest="cmd", required=True)
    v = sub.add_parser("version")
    v.add_argument("--main", required=True)
    v.add_argument("--constant")
    b = sub.add_parser("build")
    b.add_argument("--slug", required=True)
    b.add_argument("--main", required=True, help="main plugin file in the repository")
    b.add_argument("--main-dest", help="main file path inside the slug folder, if remapped")
    b.add_argument("--include", action="append", required=True, help="tracked path, optionally src=dest")
    b.add_argument("--constant", help="PHP version constant that must equal the header version")
    b.add_argument("--out", default="dist")
    args = ap.parse_args()
    {"version": cmd_version, "build": cmd_build}[args.cmd](args)


if __name__ == "__main__":
    main()
