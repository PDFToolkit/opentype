"""Differential execution of every contextual GSUB/GPOS format and native attachments."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import tempfile

import fontTools
from fontTools.ttLib import TTFont
import uharfbuzz as hb

from build_advanced_layout_fixture import build


def cases():
    for prefix in ("s", "p"):
        for chained in (False, True):
            for format in (1, 2, 3):
                tag = f"{prefix}{int(chained)}0{format}"
                yield {"name": tag, "codePoints": [99, 97, 98, 100, 97, 98], "features": [tag]}
                yield {"name": tag + " mismatch", "codePoints": [97, 99, 98, 100], "features": [tag]}
    yield {"name": "reverse chaining feeds earlier input", "codePoints": [97, 97, 98], "features": ["rvrs"]}
    for tag in ("sng1", "sng2", "kern", "prc2", "prb2", "pext"):
        yield {"name": tag, "codePoints": [97, 98, 99, 100], "features": [tag]}
    for tag in ("curs", "crtl"):
        yield {"name": tag, "codePoints": [97, 98, 99], "features": [tag], "cursive": True}
    for tag in ("lig1", "flt1", "att1"):
        for mark in (0x301, 0x300):
            yield {"name": f"{tag} mark {mark}", "codePoints": [97, mark, 98], "features": [tag]}
    yield {"name": "mark base above", "codePoints": [97, 0x301], "features": ["mark"]}
    yield {"name": "mark base below", "codePoints": [97, 0x300], "features": ["mark"]}
    yield {"name": "mark missing base anchor", "codePoints": [98, 0x300], "features": ["mark"]}
    yield {"name": "mark chain", "codePoints": [97, 0x301, 0x307], "features": ["mark", "mkmk"]}
    yield {"name": "mark ligature default final component", "codePoints": [0xE002, 0x301], "features": ["mklg"]}
    yield {"name": "mark ligature first component", "codePoints": [97, 0x301, 98], "features": ["lig1", "mklg"]}
    yield {"name": "mark ligature final component", "codePoints": [97, 98, 0x301], "features": ["lig1", "mklg"]}
    yield {"name": "kerning ignores unrelated text", "codePoints": [99, 100, 97, 98], "features": ["kern"]}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", required=True, type=Path)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    path = root / "tests/fixtures/ToolkitAdvanced.ttf"
    with tempfile.TemporaryDirectory(prefix="pdftoolkit-layout-") as directory:
        generated = Path(directory) / "fixture.ttf"
        build(generated)
        if generated.read_bytes() != path.read_bytes():
            raise AssertionError("Advanced layout fixture is not reproducible.")
    requests = list(cases())
    result = subprocess.run([str(args.php), str(root / "tools/inspect_advanced_layout.php"), str(path)], input=json.dumps(requests), capture_output=True, text=True)
    if result.returncode:
        raise AssertionError(result.stdout + result.stderr)
    actual = json.loads(result.stdout)
    tt = TTFont(path, checkChecksums=2)
    tags = {record.FeatureTag for tag in ("GSUB", "GPOS") for record in tt[tag].table.FeatureList.FeatureRecord}
    font = hb.Font(hb.Face(path.read_bytes()))
    hb.ot_font_set_funcs(font)
    for case, output in zip(requests, actual["cases"], strict=True):
        buffer = hb.Buffer()
        buffer.add_codepoints(case["codePoints"])
        buffer.direction, buffer.script, buffer.language = "ltr", "Latn", "en"
        buffer.cluster_level = hb.BufferClusterLevel.CHARACTERS
        features = {tag: 0 for tag in tags | {"ccmp", "locl", "liga", "calt", "mark", "mkmk", "kern"}}
        features.update({tag: 1 for tag in case["features"]})
        hb.shape(font, buffer, features)
        glyphs = [item.codepoint for item in buffer.glyph_infos]
        positions = [[p.x_advance, p.y_advance, p.x_offset, p.y_offset] for p in buffer.glyph_positions]
        origins, x, y = [], 0, 0
        for xa, ya, xo, yo in positions:
            origins.append([x + xo, y + yo]); x += xa; y += ya
        expected = {"glyphs": glyphs, "positions": positions, "origins": origins, "advance": [x, y]}
        keys = ("glyphs", "origins", "advance") if case.get("cursive") else tuple(expected)
        for key in keys:
            if output[key] != expected[key]:
                raise AssertionError(f"{case['name']} {key}: PHP={output[key]!r}; HarfBuzz={expected[key]!r}")
    for tag, key in (("GSUB", "gsubTypes"), ("GPOS", "gposTypes")):
        expected = [lookup.LookupType for lookup in tt[tag].table.LookupList.Lookup]
        if actual[key] != expected:
            raise AssertionError(f"{tag} fontTools lookup inventory differs.")
    print(json.dumps({"status": "passed", "cases": len(requests), "fontTools": fontTools.__version__, "uharfbuzz": hb.__version__, "harfbuzz": hb.version_string(), "fixtureSha256": hashlib.sha256(path.read_bytes()).hexdigest(), "checks": ["all six contextual GSUB formats", "reverse chain direction", "GDEF classes and mark sets", "all six contextual GPOS formats", "single and pair positioning", "cursive attachment origins and final advance", "mark to base, ligature and mark", "extension positioning", "fontTools lookup inventories", "reproducible original fixture"]}, indent=2))


if __name__ == "__main__":
    main()
