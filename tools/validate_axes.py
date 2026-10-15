"""Check user normalization against fontTools and HarfBuzz using two asymmetric axes."""
import argparse
import json
from pathlib import Path
import subprocess
import tempfile
from fontTools.ttLib import TTFont
from fontTools.varLib.models import normalizeLocation, piecewiseLinearMap
from fontTools.misc.fixedTools import floatToFixedToFloat
import uharfbuzz as hb
from build_axis_fixture import build
from validate_variations import compare


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', type=Path, required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    source = root / 'tests/fixtures/ToolkitAxes.ttf'
    tt = TTFont(source)
    requests = [{}, {'wght': 650}, {'wdth': 81.25}, {'wdth': 125, 'wght': 650}]
    requests += [{'wdth': width, 'wght': weight} for weight in (-100, 100, 250, 325, 400, 525, 650, 775, 900, 1200) for width in (0, 62.5, 81.25, 100, 112.5, 125, 200, 250)]
    # Non-grid and half-LSB inputs check mandated 16.16 then 2.14 rounding.
    requests += [{'wght': x, 'wdth': 114.12} for x in (400.000007, 400.000008, 333.33, 466.67, 612.345)]
    with tempfile.TemporaryDirectory() as directory:
        regenerated = Path(directory) / 'axes.ttf'
        build(regenerated)
        assert regenerated.read_bytes() == source.read_bytes(), 'Not reproducible'
    run = subprocess.run([str(args.php), str(root / 'tools/inspect_axes.php'), str(source)], input=json.dumps(requests), text=True, capture_output=True)
    assert run.returncode == 0, run.stdout + run.stderr
    actual = json.loads(run.stdout)
    axes = tt['fvar'].axes
    assert actual['axes'] == [[a.axisTag, a.minValue, a.defaultValue, a.maxValue, a.flags, a.axisNameID] for a in axes]
    assert actual['instances'] == [[i.subfamilyNameID, [i.coordinates[a.axisTag] for a in axes], i.postscriptNameID] for i in tt['fvar'].instances]
    bounds = {a.axisTag: (a.minValue, a.defaultValue, a.maxValue) for a in axes}
    for request, result in zip(requests, actual['coordinates'], strict=True):
        normal = normalizeLocation({k: floatToFixedToFloat(v, 16) for k, v in request.items()}, bounds)
        expected = [floatToFixedToFloat(piecewiseLinearMap(normal[a.axisTag], tt['avar'].segments[a.axisTag]), 14) for a in axes]
        font = hb.Font(hb.Face(source.read_bytes()))
        font.set_variations(request)
        harfbuzz = font.get_var_coords_normalized()
        if not harfbuzz:
            harfbuzz = [0.0] * len(axes)
        assert result == list(harfbuzz), (request, result, harfbuzz)
        # fontTools calculates in float rather than mandated intermediate 16.16;
        # permit one F2DOT14 unit only on these non-grid inputs.
        assert all(abs(a-b) <= 1/16384 for a,b in zip(result, expected, strict=True)), (request, result, expected)
    cases = [{'name': f'{feature} at {i}', 'codePoints': [99,97,98,100], 'features': [feature], 'coordinates': coordinates} for i,coordinates in enumerate(actual['coordinates'][:84]) if (coordinates[0] * (100 if coordinates[0] >= 0 else 40)).is_integer() for feature in ('s001','sng1','sng2')]
    compare(args.php, root, source, cases)
    print(json.dumps({'status': 'passed', 'normalizationCases': len(requests), 'layoutCases': len(cases), 'axisOrder': [a.axisTag for a in axes], 'harfbuzz': hb.version_string(), 'fontToolsNonGridTolerance': 'one F2DOT14 unit; HarfBuzz exact'}, indent=2))


if __name__ == '__main__':
    main()
