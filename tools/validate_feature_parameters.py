#!/usr/bin/env python3
"""Compare semantic feature parameters with independent fontTools decoders."""
import argparse
import json
from pathlib import Path
import struct
import subprocess
from fontTools.ttLib import TTFont
from fontTools.ttLib.tables import otTables
from fontTools.ttLib.tables.otBase import OTTableReader


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', required=True)
    args = parser.parse_args()
    cases = [
        ('size', struct.pack('>5H', 180, 3, 257, 139, 240), otTables.FeatureParamsSize),
        ('size', struct.pack('>5H', 60, 0, 0, 0, 0), otTables.FeatureParamsSize),
        ('ss01', struct.pack('>2H', 0, 257), otTables.FeatureParamsStylisticSet),
        ('ss20', struct.pack('>2H', 0, 5), otTables.FeatureParamsStylisticSet),
        ('cv01', struct.pack('>7H', 0, 256, 257, 258, 2, 300, 2) + bytes.fromhex('00004101f600'), otTables.FeatureParamsCharacterVariants),
        ('cv99', struct.pack('>7H', 0, 0, 0, 0, 0, 0, 0), otTables.FeatureParamsCharacterVariants),
    ]
    expected = []
    for tag, data, kind in cases:
        model = kind()
        model.decompile(OTTableReader(data), TTFont())
        if tag == 'size':
            expected.append({'designSize': round(model.DesignSize*10), 'subfamilyId': model.SubfamilyID,
                             'subfamilyNameId': model.SubfamilyNameID, 'rangeStart': round(model.RangeStart*10),
                             'rangeEnd': round(model.RangeEnd*10)})
        elif tag.startswith('ss'):
            expected.append({'uiLabelNameId': model.UINameID})
        else:
            expected.append({'uiLabelNameId': model.FeatUILabelNameID, 'uiTooltipNameId': model.FeatUITooltipTextNameID,
                             'sampleTextNameId': model.SampleTextNameID, 'namedParameterCount': model.NumNamedParameters,
                             'firstParameterNameId': model.FirstParamUILabelNameID, 'characters': model.Character})
    request = [{'tag': tag, 'hex': data.hex()} for tag, data, _ in cases]
    result = subprocess.run([args.php, str(Path(__file__).with_name('inspect_feature_parameters.php'))],
                            input=json.dumps(request), capture_output=True, text=True, check=True)
    if json.loads(result.stdout) != expected:
        raise ValueError('fontTools and PHP feature parameter fields differ')
    print(json.dumps({'status': 'passed', 'oracle': 'fontTools', 'cases': len(cases), 'parameters': expected}, indent=2))


if __name__ == '__main__':
    main()
