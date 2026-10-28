#!/usr/bin/env python3
"""Build original TTC/OTC fixtures using the independently generated MIT fonts."""
import argparse
import io
from pathlib import Path
import struct
from fontTools.ttLib import TTFont, TTCollection


def build(root: Path, mixed: bool) -> bytes:
    collection = TTCollection()
    paths = [root / 'ToolkitUnicode.ttf', root / ('ToolkitUnicode.otf' if mixed else 'ToolkitUnicode.ttf')]
    collection.fonts = [TTFont(path, recalcTimestamp=False, recalcBBoxes=False) for path in paths]
    collection.fonts[1]['name'].setName('Toolkit Collection Second', 1, 3, 1, 0x409)
    output = io.BytesIO()
    collection.save(output, shareTables=True)
    for font in collection.fonts:
        font.close()
    data = output.getvalue()
    if not mixed:
        return data
    # Version 2 retains explicit empty signature fields, adding 12 bytes after offsets.
    count, = struct.unpack_from('>I', data, 8)
    end = 12 + 4 * count
    result = bytearray(data[:end] + bytes(12) + data[end:])
    struct.pack_into('>I', result, 4, 0x20000)
    for index in range(count):
        position = 12 + 4 * index
        offset, = struct.unpack_from('>I', data, position)
        struct.pack_into('>I', result, position, offset + 12)
        tables, = struct.unpack_from('>H', data, offset + 4)
        for table in range(tables):
            at = offset + 12 + 16 * table + 8
            original, = struct.unpack_from('>I', data, at)
            struct.pack_into('>I', result, at + 12, original + 12)
    return bytes(result)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1] / 'tests/fixtures'
    for name, mixed in [('ToolkitCollection.ttc', False), ('ToolkitMixed.otc', True)]:
        data = build(root, mixed)
        path = root / name
        if args.check:
            if path.read_bytes() != data:
                raise ValueError(f'{name} is not reproducible')
        else:
            path.write_bytes(data)
        print(f'{name}: {len(data)} bytes')


if __name__ == '__main__':
    main()
