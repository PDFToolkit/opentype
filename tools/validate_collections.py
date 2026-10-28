#!/usr/bin/env python3
"""Compare every collection face/directory byte range against fontTools TTCollection."""
import argparse
import base64
from io import BytesIO
import hashlib
import json
from pathlib import Path
import struct
import subprocess
from fontTools.ttLib import TTCollection, TTFont


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    results = []
    for name in ['ToolkitCollection.ttc', 'ToolkitMixed.otc']:
        path = root / 'tests/fixtures' / name
        raw = path.read_bytes()
        collection = TTCollection(path, lazy=True)
        fonts = []
        for font in collection.fonts:
            tables = [{'tag': tag, 'offset': record.offset, 'length': record.length,
                       'checksum': record.checkSum,
                       'sha256': hashlib.sha256(raw[record.offset:record.offset+record.length]).hexdigest()}
                      for tag, record in sorted(font.reader.tables.items())]
            fonts.append({'version': struct.unpack('>I', font.sfntVersion.encode('latin1'))[0],
                          'glyphCount': font['maxp'].numGlyphs, 'tables': tables})
        expected = {'version': struct.unpack_from('>H', raw, 4)[0], 'fonts': fonts}
        actual = json.loads(subprocess.check_output([args.php, str(root / 'tools/inspect_collections.php'), str(path)]))
        for source_font, result in zip(collection.fonts, actual['fonts']):
            serialized = base64.b64decode(result.pop('serialized'), validate=True)
            rebuilt = TTFont(BytesIO(serialized), checkChecksums=2)
            if sum(struct.unpack('>'+'I'*(len(serialized)//4), serialized)) & 0xFFFFFFFF != 0xB1B0AFBA:
                raise ValueError('Serialized whole-font checksum mismatch')
            if set(rebuilt.reader.tables) != set(source_font.reader.tables) - {'DSIG'}:
                raise ValueError('Serialized table inventory mismatch')
            for tag in rebuilt.reader.tables:
                before, after = source_font.getTableData(tag), rebuilt.getTableData(tag)
                if tag == 'head':
                    before, after = before[:8]+b'\0'*4+before[12:], after[:8]+b'\0'*4+after[12:]
                if before != after:
                    raise ValueError('Serialized table bytes differ: '+tag)
        if actual != expected:
            raise ValueError(f'{name}: collection face/directory mismatch')
        results.append({'file': name, 'sha256': hashlib.sha256(raw).hexdigest(),
                        'faces': len(fonts), 'tableRecords': sum(len(font['tables']) for font in fonts)})
        collection.close()
    print(json.dumps({'status': 'passed', 'oracle': 'fontTools TTCollection', 'collections': results}, indent=2))


if __name__ == '__main__':
    main()
