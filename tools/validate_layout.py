"""Compare PHP SFNT/GSUB output with independent fontTools and HarfBuzz."""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
import subprocess
import tempfile
from pathlib import Path

import fontTools
from fontTools.ttLib import TTFont
import uharfbuzz as hb

from build_layout_fixture import build


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", required=True, type=Path)
    parser.add_argument("--font", type=Path, default=Path(__file__).resolve().parents[1] / "tests/fixtures/ToolkitLayout.ttf")
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    with tempfile.TemporaryDirectory(prefix="pdftoolkit-opentype-") as directory:
        regenerated = Path(directory) / "fixture.ttf"
        build(regenerated)
        if regenerated.read_bytes() != args.font.read_bytes():
            raise AssertionError("The committed fixture differs from its reproducible original generator.")
    tt = TTFont(args.font, checkChecksums=2)
    names = tt.getGlyphOrder()
    face = hb.Face(args.font.read_bytes())
    font = hb.Font(face)
    hb.ot_font_set_funcs(font)
    requests = [
        {"name": "ligature ffi", "codePoints": [102, 102, 105], "features": ["liga"]},
        {"name": "ligature fi", "codePoints": [102, 105], "features": ["liga"]},
        {"name": "ligature preference", "codePoints": [102, 102, 105, 102, 105], "features": ["liga"]},
        {"name": "small capitals", "codePoints": [97, 98, 97], "features": ["smcp"]},
        {"name": "alternate one", "codePoints": [97, 98], "features": ["salt"], "alternate": 1},
        {"name": "alternate two", "codePoints": [97, 97], "features": ["salt"], "alternate": 2},
        {"name": "multiple substitution", "codePoints": [0xFB01], "features": ["ccmp"]},
        {"name": "extension", "codePoints": [97, 98], "features": ["vrt2"]},
        {"name": "no selected features", "codePoints": [97, 102, 105, 0x1F600], "features": []},
        {"name": "missing feature", "codePoints": [97, 98], "features": ["zzzz"]},
        {"name": "combined independent features", "codePoints": [97, 102, 105], "features": ["liga", "smcp"]},
        {"name": "required Turkish feature", "codePoints": [97, 98], "features": [], "language": "TRK ", "hbLanguage": "tr"},
        {"name": "default language fallback", "codePoints": [97, 98], "features": ["smcp"], "language": "FRA ", "hbLanguage": "fr"},
        {"name": "default script fallback", "codePoints": [97, 98], "features": ["smcp"], "script": "grek", "hbScript": "Grek"},
    ]
    for case in requests:
        case.setdefault("script", "latn")
    result = subprocess.run(
        [str(args.php), str(root / "tools/inspect_layout.php"), str(args.font)],
        input=json.dumps(requests), text=True, capture_output=True, check=True,
    )
    php = json.loads(result.stdout)
    gsub = tt["GSUB"].table
    expected = {
        "glyphCount": len(names),
        "tables": sorted(tag for tag in tt.keys() if tag != "GlyphOrder"),
        "scripts": [r.ScriptTag for r in gsub.ScriptList.ScriptRecord],
        "features": [r.FeatureTag for r in gsub.FeatureList.FeatureRecord],
        "lookupTypes": [lookup.LookupType for lookup in gsub.LookupList.Lookup],
    }
    for key, value in expected.items():
        if php[key] != value:
            raise AssertionError(f"fontTools {key}: {php[key]!r} != {value!r}")
    for case, actual in zip(requests, php["cases"], strict=True):
        buffer = hb.Buffer()
        buffer.add_codepoints(case["codePoints"])
        buffer.direction = "ltr"
        buffer.script = case.get("hbScript", "Latn")
        buffer.language = case.get("hbLanguage", "en")
        features = {tag: 0 for tag in ("calt", "ccmp", "liga", "locl", "salt", "smcp", "vrt2")}
        features.update({tag: case.get("alternate", 1) if tag == "salt" else 1 for tag in case["features"]})
        hb.shape(font, buffer, features)
        reference = {
            "glyphs": [info.codepoint for info in buffer.glyph_infos],
            "clusters": [info.cluster for info in buffer.glyph_infos],
        }
        if actual != reference:
            raise AssertionError(f"{case['name']}: PHP={actual!r}; HarfBuzz={reference!r}")
    # Empty AlternateSet arrays are legal. HarfBuzz treats them as no-match,
    # so a later subtable in the same lookup can still provide a replacement.
    with tempfile.TemporaryDirectory(prefix="pdftoolkit-alternates-") as directory:
        for fallback in (False, True):
            variant = TTFont(args.font)
            variant.recalcTimestamp = False
            lookup = next(item for item in variant["GSUB"].table.LookupList.Lookup if item.LookupType == 3)
            original = copy.deepcopy(lookup.SubTable[0])
            lookup.SubTable[0].alternates["a"] = []
            if fallback:
                lookup.SubTable.append(original)
                lookup.SubTableCount += 1
            path = Path(directory) / f"empty-alternates-{fallback}.ttf"
            variant.save(path)
            case = {"codePoints": [97], "script": "latn", "features": ["salt"], "alternate": 2}
            output = subprocess.run(
                [str(args.php), str(root / "tools/inspect_layout.php"), str(path)],
                input=json.dumps([case]), text=True, capture_output=True, check=True,
            )
            actual = json.loads(output.stdout)["cases"][0]
            variant_font = hb.Font(hb.Face(path.read_bytes()))
            hb.ot_font_set_funcs(variant_font)
            buffer = hb.Buffer()
            buffer.add_codepoints([97])
            buffer.direction, buffer.script, buffer.language = "ltr", "Latn", "en"
            hb.shape(variant_font, buffer, {"ccmp": 0, "liga": 0, "locl": 0, "calt": 0, "salt": 2})
            expected = {"glyphs": [item.codepoint for item in buffer.glyph_infos], "clusters": [item.cluster for item in buffer.glyph_infos]}
            if actual != expected:
                raise AssertionError(f"empty alternates fallback={fallback}: {actual!r} != {expected!r}")
    print(json.dumps({
        "status": "passed", "cases": len(requests) + 2, "fontTools": fontTools.__version__,
        "uharfbuzz": hb.__version__, "harfbuzz": hb.version_string(),
        "fixtureSha256": hashlib.sha256(args.font.read_bytes()).hexdigest(),
        "checks": ["reproducible original fixture", "independent SFNT inventory", "GSUB feature and lookup inventory", "glyph ids", "input cluster provenance"],
    }, indent=2))


if __name__ == "__main__":
    main()
