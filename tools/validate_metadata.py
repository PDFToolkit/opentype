"""Compare every modeled name/OS2 field with fontTools, including all OS2 versions."""
import argparse
import base64
import json
from pathlib import Path
import subprocess
import tempfile
from fontTools.ttLib import TTFont


def compare(php, root, path):
    font = TTFont(path, checkChecksums=2)
    run = subprocess.run([str(php), str(root / 'tools/inspect_metadata.php'), str(path)], text=True, capture_output=True)
    assert run.returncode == 0, run.stdout + run.stderr
    actual = json.loads(run.stdout)
    names = [[n.platformID,n.platEncID,n.langID,n.nameID,base64.b64encode(n.string).decode(),n.toUnicode()] for n in font['name'].names]
    assert actual['names'] == names
    os2 = font['OS/2']
    base = ('version xAvgCharWidth usWeightClass usWidthClass ySubscriptXSize ySubscriptYSize ySubscriptXOffset ySubscriptYOffset ySuperscriptXSize ySuperscriptYSize ySuperscriptXOffset ySuperscriptYOffset yStrikeoutSize yStrikeoutPosition sFamilyClass fsSelection usFirstCharIndex usLastCharIndex sTypoAscender sTypoDescender sTypoLineGap usWinAscent usWinDescent sxHeight sCapHeight usDefaultChar usBreakChar usMaxContext usLowerOpticalPointSize usUpperOpticalPointSize').split()
    expected = {key: getattr(os2,key,None) for key in base}
    # fontTools exposes optical sizes in points; OpenType fields are in twentieths.
    for key in ('usLowerOpticalPointSize','usUpperOpticalPointSize'):
        if expected[key] is not None:
            expected[key] = round(expected[key] * 20)
    expected['achVendId'] = os2.achVendID
    expected['embeddingRights'] = {'fsType': os2.fsType, 'tableVersion': os2.version}
    expected['panose'] = {'values': [getattr(os2.panose,key) for key in ('bFamilyType bSerifStyle bWeight bProportion bContrast bStrokeVariation bArmStyle bLetterForm bMidline bXHeight').split()]}
    expected['unicodeRanges'] = {'words': [getattr(os2,f'ulUnicodeRange{i}') for i in range(1,5)]}
    expected['codePageRanges'] = {'words': [os2.ulCodePageRange1,os2.ulCodePageRange2]} if os2.version >= 1 else None
    assert actual['os2'] == expected, (path, actual['os2'], expected)
    return len(names), len(expected)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', type=Path, required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    files = list((root / 'tests/fixtures').glob('*.ttf')) + list((root / 'tests/fixtures').glob('*.otf'))
    counts = [compare(args.php,root,path) for path in files]
    with tempfile.TemporaryDirectory() as directory:
        for version in range(6):
            font = TTFont(root / 'tests/fixtures/ToolkitAxes.ttf')
            table = font['OS/2']
            table.version = version
            table.sxHeight,table.sCapHeight,table.usDefaultChar,table.usBreakChar,table.usMaxContext = 500,700,0,32,3
            table.usLowerOpticalPointSize,table.usUpperOpticalPointSize = 12,24
            font.recalcTimestamp = False
            path = Path(directory) / f'os2-{version}.ttf'
            font.save(path)
            counts.append(compare(args.php,root,path))
    print(json.dumps({'status':'passed','fontCases':len(counts),'nameRecords':sum(a for a,b in counts),'os2FieldComparisons':sum(b for a,b in counts),'os2Versions':list(range(6))}, indent=2))


if __name__ == '__main__':
    main()
