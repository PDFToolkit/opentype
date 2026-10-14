#!/usr/bin/env python3
"""Compare the PHP model with independent fontTools on complete SFNT fixtures."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import struct
import subprocess

from fontTools.ttLib import TTFont


def compare(php: str, path: Path, inspector: Path) -> dict[str, object]:
    raw = path.read_bytes()
    font = TTFont(path, lazy=False, recalcBBoxes=False, recalcTimestamp=False)
    try:
        best_cmap = font.getBestCmap()
        if best_cmap is None:
            raise ValueError(f"No Unicode cmap in {path}")
        points = set(best_cmap)
        points.update((0, 1, 65, 0xD7FF, 0xE000, 0x10FFFF))
        points.update(point + delta for point in best_cmap for delta in (-1, 1)
                      if 0 <= point + delta <= 0x10FFFF
                      and not 0xD800 <= point + delta <= 0xDFFF)
        points = sorted(point for point in points if not 0xD800 <= point <= 0xDFFF)
        glyphs = [{"codepoint": point, "glyph": font.getGlyphID(best_cmap[point])
                   if point in best_cmap else 0} for point in points]
        variation_requests = []
        expected_variations = []
        for subtable in font["cmap"].tables:
            if subtable.format != 14:
                continue
            for selector, mappings in sorted(subtable.uvsDict.items()):
                for base, glyph_name in mappings:
                    variation_requests.append({"base": base, "selector": selector})
                    glyph = (font.getGlyphID(glyph_name) if glyph_name is not None
                             else font.getGlyphID(best_cmap[base]) if base in best_cmap else 0)
                    expected_variations.append({"base": base, "selector": selector, "glyph": glyph})
        variation_requests.append({"base": 0x10FFFF, "selector": 0xE01EF})
        expected_variations.append({"base": 0x10FFFF, "selector": 0xE01EF, "glyph": None})
        request = {"codepoints": points, "variations": variation_requests}
        process = subprocess.run([php, str(inspector), str(path)], input=json.dumps(request),
                                 text=True, capture_output=True, check=True)
        actual = json.loads(process.stdout)
        expected_tables = []
        for tag, entry in sorted(font.reader.tables.items()):
            data = raw[entry.offset:entry.offset + entry.length]
            expected_tables.append({"tag": tag, "checksum": entry.checkSum, "length": entry.length,
                                    "sha256": hashlib.sha256(data).hexdigest()})
        expected_metrics = [list(font["hmtx"].metrics[name]) for name in font.getGlyphOrder()]
        expected = {
            "sfntVersion": struct.unpack(">I", raw[:4])[0],
            "glyphCount": font["maxp"].numGlyphs,
            "unitsPerEm": font["head"].unitsPerEm,
            "tables": expected_tables,
            "glyphs": glyphs,
            "variations": expected_variations,
            "metrics": expected_metrics,
        }
        for key, value in expected.items():
            if actual[key] != value:
                raise ValueError(f"{path.name}: independent {key} comparison failed")
        return {"file": path.name, "sha256": hashlib.sha256(raw).hexdigest(),
                "tables": len(expected_tables), "glyphs": expected["glyphCount"],
                "unicodeProbes": len(points), "variationProbes": len(expected_variations),
                "cmapFormat": actual["cmapFormat"]}
    finally:
        font.close()


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", required=True)
    parser.add_argument("fonts", nargs="+", type=Path)
    arguments = parser.parse_args()
    inspector = Path(__file__).with_name("inspect_font_model.php")
    results = [compare(arguments.php, path.resolve(), inspector.resolve()) for path in arguments.fonts]
    print(json.dumps({"status": "passed", "oracle": "fontTools", "fonts": results},
                     indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
