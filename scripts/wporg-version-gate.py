#!/usr/bin/env python3
"""Decide whether a push should publish to WordPress.org.

Reads the plugin header, CUS_VERSION, and readme Stable tag. All three must
match an x.y.z version. Publishes only when that version is greater than the
previous commit. Writes GitHub Actions outputs when GITHUB_OUTPUT is set.
"""

import os
import re
import subprocess
import sys
from typing import NoReturn

PLUGIN = 'content-update-scheduler.php'
README = 'readme.txt'
VERSION_RE = re.compile(r'^(\d+)\.(\d+)\.(\d+)$')
HEADER_RE = re.compile(r'^\s*\*\s*Version:\s*(\S+)', re.M)
CONSTANT_RE = re.compile(r"define\(\s*'CUS_VERSION'\s*,\s*'([^']+)'\s*\)")
STABLE_RE = re.compile(r'^Stable tag:\s*(\S+)\s*$', re.M)


def fail(message: str) -> NoReturn:
    print(message, file=sys.stderr)
    raise SystemExit(1)


def parse_version(version, label):
    if not VERSION_RE.match(version or ''):
        fail(f'Invalid {label} (expected x.y.z): {version or "missing"}')
    return tuple(int(part) for part in version.split('.'))


def git(*args):
    return subprocess.check_output(['git', *args], text=True)


def commit_exists(ref):
    if not ref or re.fullmatch(r'0+', ref):
        return False
    try:
        git('cat-file', '-e', f'{ref}^{{commit}}')
    except subprocess.CalledProcessError:
        return False
    return True


def read_at(ref, path):
    try:
        return subprocess.check_output(
            ['git', 'show', f'{ref}:{path}'],
            text=True,
            stderr=subprocess.DEVNULL,
        )
    except subprocess.CalledProcessError:
        return None


def versions_in(plugin, readme, label):
    if plugin is None or readme is None:
        fail(f'{label} is missing {PLUGIN} or {README}; refusing to publish automatically.')
    header = HEADER_RE.search(plugin)
    constant = CONSTANT_RE.search(plugin)
    stable = STABLE_RE.search(readme)
    found = {
        'plugin header': header.group(1) if header else None,
        'CUS_VERSION': constant.group(1) if constant else None,
        'readme Stable tag': stable.group(1) if stable else None,
    }
    missing = [name for name, value in found.items() if not value]
    if missing:
        fail(f'{label} version metadata missing: {", ".join(missing)}')
    unique = set(found.values())
    if len(unique) != 1:
        details = ', '.join(f'{name}={value}' for name, value in found.items())
        fail(f'{label} version metadata is inconsistent: {details}')
    version = unique.pop()
    parse_version(version, label)
    return version


def main():
    plugin = open(PLUGIN, encoding='utf-8').read()
    readme = open(README, encoding='utf-8').read()
    current = versions_in(plugin, readme, 'Current')

    previous_ref = None
    before = os.environ.get('GITHUB_EVENT_BEFORE', '')
    if commit_exists(before):
        previous_ref = before
    elif commit_exists('HEAD^'):
        previous_ref = 'HEAD^'

    if not previous_ref:
        fail('Unable to determine the previous commit; refusing to publish automatically.')

    previous = versions_in(
        read_at(previous_ref, PLUGIN),
        read_at(previous_ref, README),
        f'Previous ({previous_ref})',
    )
    comparison = (parse_version(current, 'current') > parse_version(previous, 'previous')) - (
        parse_version(current, 'current') < parse_version(previous, 'previous')
    )
    if comparison < 0:
        fail(f'Version regression: current {current} is lower than previous {previous}')

    publish = comparison > 0
    print(f'Current version: {current}')
    print(f'Previous version: {previous}')
    print('Version increased; WordPress.org publish gate is open.' if publish else 'Version unchanged; WordPress.org publish gate is closed.')

    output = os.environ.get('GITHUB_OUTPUT')
    if output:
        with open(output, 'a', encoding='utf-8') as handle:
            handle.write(f'version={current}\n')
            handle.write(f'previous_version={previous}\n')
            handle.write(f'publish={"true" if publish else "false"}\n')


if __name__ == '__main__':
    main()
