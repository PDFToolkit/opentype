#!/usr/bin/env python3
"""Compare context-preserving source-cluster feature ranges with HarfBuzz."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import uharfbuzz as hb
from fontTools.ttLib import TTFont


def cases():
    def case(name, text, tag, start, end, enabled=True, global_on=False):
        return {'name': name, 'codePoints': [ord(x) for x in text], 'features': [tag] if global_on else [],
                'ranges': [{'tag': tag, 'start': start, 'end': end, 'enabled': enabled}]}
    for prefix in ['s', 'p']:
        for fmt in [1, 2, 3]:
            tag = f'{prefix}10{fmt}'
            yield case(tag+' outside context preserved', 'cabdcabd', tag, 1, 3)
            yield case(tag+' only one matched input enabled', 'cabdcabd', tag, 1, 2)
            yield case(tag+' disabled first occurrence', 'cabdcabd', tag, 1, 3, False, True)
    yield case('ligature whole input', 'abab', 'lig1', 0, 2)
    yield case('ligature split boundary', 'abab', 'lig1', 0, 1)
    yield case('ligature ignores unselected mark', 'ábab', 'lig1', 0, 3)
    yield case('ligature second occurrence disabled', 'abab', 'lig1', 2, 4, False, True)
    yield case('pair second input masked', 'abab', 'kern', 0, 1)
    yield case('pair selected', 'abab', 'kern', 0, 2)
    yield case('mark requires base and mark selection', 'áá', 'mark', 1, 2)
    yield case('single positioning second occurrence', 'aa', 'sng1', 1, 2)
    yield case('cursive second input masked', 'abc', 'curs', 0, 1)
    yield case('reverse keeps outside context', 'aab', 'rvrs', 1, 2)
    item = case('later overlapping range overrides', 'ababab', 'lig1', 0, 6)
    item['ranges'] += [{'tag': 'lig1', 'start': 1, 'end': 5, 'enabled': False}, {'tag': 'lig1', 'start': 4, 'end': 6, 'enabled': True}]
    yield item
    item = case('two independent feature masks', 'abab', 'lig1', 0, 2)
    item['ranges'] += [{'tag': 'kern', 'start': 2, 'end': 4}]
    yield item


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    requests = list(cases())
    requests += [
        {'name': 'multiple expansion restricted to second cluster', 'font': 'ToolkitLayout.ttf', 'codePoints': [0xFB01, 0xFB01], 'features': [], 'ranges': [{'tag': 'ccmp', 'start': 1, 'end': 2}]},
        {'name': 'small caps after earlier unrelated cluster', 'font': 'ToolkitLayout.ttf', 'codePoints': [97, 98, 97, 98], 'features': [], 'ranges': [{'tag': 'smcp', 'start': 2, 'end': 4}]},
    ]
    results = []
    for case in requests:
        path = root/'tests/fixtures'/case.get('font', 'ToolkitAdvanced.ttf')
        result = subprocess.run([args.php, str(root/'tools/inspect_advanced_layout.php'), str(path)], input=json.dumps([case]), text=True, capture_output=True, check=True)
        actual = json.loads(result.stdout)['cases'][0]
        font = hb.Font(hb.Face(path.read_bytes()))
        table = TTFont(path)
        tags = {record.FeatureTag for tag in ['GSUB', 'GPOS'] if tag in table for record in table[tag].table.FeatureList.FeatureRecord}
        buffer = hb.Buffer()
        buffer.add_codepoints(case['codePoints'])
        buffer.direction, buffer.script, buffer.language = 'ltr', 'latn', 'en'
        buffer.cluster_level = hb.BufferClusterLevel.CHARACTERS
        features = {tag: 0 for tag in tags | {'ccmp', 'locl', 'liga', 'calt', 'mark', 'mkmk', 'kern'}}
        features.update({tag: 1 for tag in case['features']})
        for item in case['ranges']:
            tag = item['tag']
            if not isinstance(features[tag], list):
                features[tag] = [(0, 0xFFFFFFFF, features[tag])]
            features[tag].append((item['start'], item['end'], int(item.get('enabled', True))))
        hb.shape(font, buffer, features)
        expected = {'glyphs': [g.codepoint for g in buffer.glyph_infos], 'positions': [[p.x_advance, p.y_advance, p.x_offset, p.y_offset] for p in buffer.glyph_positions]}
        for key in expected:
            if expected[key] != actual[key]:
                raise ValueError(f"{case['name']} {key}: PHP={actual[key]}, HarfBuzz={expected[key]}")
        results.append({'name': case['name'], 'glyphs': expected['glyphs'], 'status': 'passed'})
    print(json.dumps({'status': 'passed', 'harfbuzz': hb.version_string(), 'uharfbuzz': hb.__version__, 'fixtures': {name: hashlib.sha256((root/'tests/fixtures'/name).read_bytes()).hexdigest() for name in ['ToolkitAdvanced.ttf', 'ToolkitLayout.ttf']}, 'cases': results}, indent=2))


if __name__ == '__main__':
    main()
