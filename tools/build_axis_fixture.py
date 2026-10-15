"""Original two-axis fixture: preserve wght,wdth order with asymmetric ranges and avar."""
from pathlib import Path
import argparse
import tempfile
from fontTools.ttLib import TTFont, newTable
from fontTools.ttLib.tables._f_v_a_r import Axis, NamedInstance
from build_variation_fixture import build as base


def build(destination: Path):
    with tempfile.TemporaryDirectory() as directory:
        path = Path(directory) / 'base.ttf'
        base(path)
        font = TTFont(path)
    axes = []
    for tag, minimum, default, maximum, flags, name, label in (
        ('wght', 100, 400, 900, 0, 256, 'Weight'),
        ('wdth', 62.5, 100, 200, 1, 257, 'Width'),
    ):
        axis = Axis()
        axis.axisTag, axis.minValue, axis.defaultValue, axis.maxValue = tag, minimum, default, maximum
        axis.flags, axis.axisNameID = flags, name
        axes.append(axis)
        font['name'].setName(label, name, 3, 1, 0x409)
    font['fvar'].axes = axes
    instance = NamedInstance()
    instance.subfamilyNameID, instance.flags = 258, 0
    instance.coordinates = {'wght': 650, 'wdth': 81.25}
    instance.postscriptNameID = 259
    font['name'].setName('Bold Condensed', 258, 3, 1, 0x409)
    font['name'].setName('ToolkitAxes-BoldCondensed', 259, 3, 1, 0x409)
    font['fvar'].instances = [instance]
    avar = newTable('avar')
    avar.majorVersion, avar.minorVersion = 1, 0
    avar.segments = {
        'wght': {-1.0: -1.0, -0.5: -0.75, 0.0: 0.0, 0.5: 0.25, 1.0: 1.0},
        'wdth': {-1.0: -1.0, -0.5: -0.25, 0.0: 0.0, 0.25: 0.5, 1.0: 1.0},
    }
    font['avar'] = avar
    # Existing GDEF region data gain a neutral second axis; layout remains meaningful.
    import copy
    for region in font['GDEF'].table.VarStore.VarRegionList.Region:
        neutral = copy.deepcopy(region.VarRegionAxis[0])
        neutral.StartCoord = neutral.PeakCoord = neutral.EndCoord = 0
        region.VarRegionAxis.append(neutral)
    font['GDEF'].table.VarStore.VarRegionList.RegionAxisCount = 2
    font.recalcTimestamp = False
    destination.parent.mkdir(parents=True, exist_ok=True)
    font.save(destination)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=Path(__file__).resolve().parents[1] / 'tests/fixtures/ToolkitAxes.ttf')
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            temporary = Path(directory) / 'axes.ttf'
            build(temporary)
            assert temporary.read_bytes() == args.output.read_bytes(), 'Axis fixture differs from generator'
    else:
        build(args.output)
    print(f'Verified original two-axis fixture: {args.output}')


if __name__ == '__main__':
    main()
