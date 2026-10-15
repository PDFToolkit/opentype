"""Independent HarfBuzz checks for Device tables, ItemVariationStore and feature variations."""
from __future__ import annotations

import argparse
import json
from pathlib import Path
import subprocess
import tempfile

from fontTools.ttLib import TTFont
from fontTools.otlLib.builder import buildSinglePosSubtable, buildValue, buildDevice
import uharfbuzz as hb

from build_variation_fixture import build


def compare(php: Path, root: Path, path: Path, requests):
    result = subprocess.run([str(php), str(root / "tools/inspect_advanced_layout.php"), str(path)], input=json.dumps(requests), capture_output=True, text=True)
    if result.returncode:
        raise AssertionError(result.stdout + result.stderr)
    actual = json.loads(result.stdout)["cases"]
    tt = TTFont(path, checkChecksums=2)
    tags = {record.FeatureTag for table in ("GSUB", "GPOS") for record in tt[table].table.FeatureList.FeatureRecord}
    for case, output in zip(requests, actual, strict=True):
        font = hb.Font(hb.Face(path.read_bytes()))
        hb.ot_font_set_funcs(font)
        if "coordinates" in case:
            font.set_var_coords_normalized(case["coordinates"])
        if "pixelsPerEm" in case:
            font.ppem = (case["pixelsPerEm"], case["pixelsPerEm"])
        buffer = hb.Buffer()
        buffer.add_codepoints(case["codePoints"])
        buffer.direction, buffer.script, buffer.language = "ltr", "Latn", "en"
        buffer.cluster_level = hb.BufferClusterLevel.CHARACTERS
        features = {tag: 0 for tag in tags | {"ccmp", "locl", "liga", "calt", "mark", "mkmk", "kern", "rvrn"}}
        features.update({tag: 1 for tag in case["features"]})
        hb.shape(font, buffer, features)
        expected = {"glyphs": [item.codepoint for item in buffer.glyph_infos], "positions": [[p.x_advance, p.y_advance, p.x_offset, p.y_offset] for p in buffer.glyph_positions]}
        for key, value in expected.items():
            if output[key] != value:
                # PHP retains exact fractional design units; hb_position_t quantizes
                # a Device pixel adjustment to an integer design unit. No tolerance
                # is used for glyph IDs or for non-Device variation execution.
                if key == "positions" and "pixelsPerEm" in case and all(abs(a - b) < 1 for actual_position, expected_position in zip(output[key], value, strict=True) for a, b in zip(actual_position, expected_position, strict=True)):
                    continue
                raise AssertionError(f"{case['name']} {key}: PHP={output[key]!r}; HarfBuzz={value!r}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", type=Path, required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    path = root / "tests/fixtures/ToolkitVariable.ttf"
    with tempfile.TemporaryDirectory(prefix="pdftoolkit-variations-") as directory:
        regenerated = Path(directory) / "variable.ttf"
        build(regenerated)
        if regenerated.read_bytes() != path.read_bytes():
            raise AssertionError("Variable fixture is not reproducible.")
        requests = [{"name": f"{feature} at {coordinate}", "codePoints": [99, 97, 98, 100], "features": [feature], "coordinates": [coordinate]} for coordinate in (-1.0, -0.5, 0.0, 0.25, 0.5, 0.75, 1.0) for feature in ("s001", "sng1", "sng2")]
        compare(args.php, root, path, requests)
        cases = len(requests)
        for delta in (-1, -3, -100):
            font = TTFont(root / "tests/fixtures/ToolkitAdvanced.ttf")
            font.recalcTimestamp = False
            device = buildDevice({10: delta, 11: 1})
            replacement = buildSinglePosSubtable({"a": buildValue({"XPlacement": 5, "XPlaDevice": device})}, font.getReverseGlyphMap())
            lookup = font["GPOS"].table.LookupList.Lookup[0]
            lookup.SubTable, lookup.SubTableCount = [replacement], 1
            generated = Path(directory) / f"device-{device.DeltaFormat}.ttf"
            font.save(generated)
            requests = [{"name": f"Device format {device.DeltaFormat} at {size}", "codePoints": [97], "features": ["sng1"], **({"pixelsPerEm": size} if size else {})} for size in (None, 9, 10, 11, 12)]
            compare(args.php, root, generated, requests)
            cases += len(requests)
    print(json.dumps({"status": "passed", "cases": cases, "harfbuzz": hb.version_string(), "deviceToleranceDesignUnits": "less than 1 for hb_position_t integer quantization only", "checks": ["conditional GSUB features", "conditional GPOS features", "native GDEF ItemVariationStore at seven normalized instances", "signed Device formats 1, 2 and 3", "explicit pixel scale and absent/outside device sizes"]}, indent=2))


if __name__ == "__main__":
    main()
