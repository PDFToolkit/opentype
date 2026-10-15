"""Original variable layout fixture: GDEF deltas and conditional GSUB/GPOS features."""
from __future__ import annotations

import argparse
from pathlib import Path
import tempfile

from fontTools.ttLib import TTFont, newTable
from fontTools.ttLib.tables._f_v_a_r import Axis
from fontTools.otlLib.builder import buildSingleSubstSubtable, buildSinglePosSubtable, buildValue
from fontTools.varLib.builder import buildVarRegionList, buildVarData, buildVarStore, buildVarDevTable
from fontTools.varLib.featureVars import buildConditionTable, buildFeatureTableSubstitutionRecord, buildFeatureVariationRecord, buildFeatureVariations

from build_advanced_layout_fixture import build as build_base, obj


def build(destination: Path):
    with tempfile.TemporaryDirectory() as directory:
        base = Path(directory) / "base.ttf"
        build_base(base)
        font = TTFont(base)
    fvar = newTable("fvar")
    axis = Axis()
    axis.axisTag, axis.minValue, axis.defaultValue, axis.maxValue = "wght", 100, 400, 900
    axis.flags, axis.axisNameID = 0, 256
    fvar.axes, fvar.instances = [axis], []
    font["fvar"] = fvar
    font["name"].setName("Weight", 256, 3, 1, 0x409)
    region_list = buildVarRegionList([{"wght": (0, 1, 1)}, {"wght": (-1, -1, 0)}], ["wght"])
    data = buildVarData([0, 1], [[100, -40], [-80, 60]])
    font["GDEF"].table.Version = 0x00010003
    font["GDEF"].table.VarStore = buildVarStore(region_list, [data])
    # A true VariationIndex in an ordinary GPOS value record.
    gpos = font["GPOS"].table
    variable_pos = buildSinglePosSubtable({"a": buildValue({"XAdvance": 10, "XAdvDevice": buildVarDevTable(0)})}, font.getReverseGlyphMap())
    variable_lookup = len(gpos.LookupList.Lookup)
    gpos.LookupList.Lookup.append(obj("Lookup", LookupType=1, LookupFlag=0, SubTable=[variable_pos]))
    for record in gpos.FeatureList.FeatureRecord:
        if record.FeatureTag == "sng2":
            record.Feature.LookupListIndex = [variable_lookup]
    for tag, feature_tag in (("GSUB", "s001"), ("GPOS", "sng1")):
        table = font[tag].table
        index = len(table.LookupList.Lookup)
        subtable = buildSingleSubstSubtable({"a": "b.alt"}) if tag == "GSUB" else buildSinglePosSubtable({"a": buildValue({"XPlacement": 90, "XAdvance": 70})}, font.getReverseGlyphMap())
        table.LookupList.Lookup.append(obj("Lookup", LookupType=1, LookupFlag=0, SubTable=[subtable]))
        feature_index = next(i for i, record in enumerate(table.FeatureList.FeatureRecord) if record.FeatureTag == feature_tag)
        substitution = buildFeatureTableSubstitutionRecord(feature_index, [index])
        table.Version = 0x00010001
        table.FeatureVariations = buildFeatureVariations([buildFeatureVariationRecord([buildConditionTable(0, 0.5, 1)], [substitution])])
        table.LookupList.LookupCount = len(table.LookupList.Lookup)
    font.recalcTimestamp = False
    destination.parent.mkdir(parents=True, exist_ok=True)
    font.save(destination)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=Path(__file__).resolve().parents[1] / "tests/fixtures/ToolkitVariable.ttf")
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "fixture.ttf"
            build(path)
            if path.read_bytes() != args.output.read_bytes():
                raise SystemExit("Variable layout fixture differs from its reproducible generator.")
    else:
        build(args.output)
    print(f"Verified original variable layout fixture: {args.output}")


if __name__ == "__main__":
    main()
