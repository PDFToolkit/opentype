"""Build original MIT synthetic TrueType and CFF fonts exercising Unicode and UVS."""
from __future__ import annotations

import argparse
from pathlib import Path
from tempfile import TemporaryDirectory

from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.pens.t2CharStringPen import T2CharStringPen
from fontTools.ttLib.tables._c_m_a_p import CmapSubtable


def build(destination: Path, is_ttf: bool) -> None:
    names = [".notdef", "A", "B", "A.alt", "smile"]
    builder = FontBuilder(1000, isTTF=is_ttf)
    builder.setupGlyphOrder(names)
    builder.setupCharacterMap({65: "A", 66: "B", 0x1F600: "smile"})
    outlines = {}
    for index, name in enumerate(names):
        pen = TTGlyphPen(None) if is_ttf else T2CharStringPen(500 + index * 50, None)
        pen.moveTo((20, 0))
        pen.lineTo((200 + index * 10, 0))
        pen.lineTo((200 + index * 10, 400 + index * 10))
        pen.lineTo((20, 400 + index * 10))
        pen.closePath()
        outlines[name] = pen.glyph() if is_ttf else pen.getCharString()
    if is_ttf:
        builder.setupGlyf(outlines)
    else:
        builder.setupCFF("PDFToolkitUnicodeFixture", {"FullName": "PDFToolkit Unicode Fixture",
                         "FamilyName": "PDFToolkit Unicode Fixture", "Weight": "Regular"},
                         outlines, {})
    builder.setupHorizontalMetrics({name: (500 + index * 50, -20 + index * 10)
                                    for index, name in enumerate(names)})
    builder.setupHorizontalHeader(ascent=800, descent=-200)
    builder.setupNameTable({"familyName": "PDFToolkit Unicode Fixture", "styleName": "Regular",
                           "uniqueFontIdentifier": "PDFToolkitUnicodeFixture-1",
                           "fullName": "PDFToolkit Unicode Fixture",
                           "psName": "PDFToolkitUnicodeFixture", "version": "Version 1.000",
                           "copyright": "Copyright 2026 PDFToolkit contributors. MIT license."})
    builder.setupOS2(sTypoAscender=800, sTypoDescender=-200, usWinAscent=800, usWinDescent=200)
    builder.setupPost()
    builder.setupMaxp()
    variation = CmapSubtable.newSubtable(14)
    variation.platformID = 0
    variation.platEncID = 5
    variation.language = 0
    variation.cmap = {}
    variation.uvsDict = {0x180B: [(0x1820, "A.alt")],
                         0xFE0F: [(65, None), (66, "A.alt")],
                         0xE0100: [(0x1F600, "A.alt")]}
    builder.font["cmap"].tables.append(variation)
    builder.font["head"].created = 3850070400
    builder.font["head"].modified = 3850070400
    builder.font.recalcTimestamp = False
    destination.parent.mkdir(parents=True, exist_ok=True)
    builder.save(destination)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", type=Path,
                        default=Path(__file__).resolve().parents[1] / "tests/fixtures")
    parser.add_argument("--check", action="store_true",
                        help="Rebuild in a temporary directory and compare without rewriting fixtures.")
    arguments = parser.parse_args()
    if arguments.check:
        with TemporaryDirectory(prefix="pdftoolkit-opentype-unicode-") as temporary:
            temporary_root = Path(temporary).resolve()
            for suffix, is_ttf in (("ttf", True), ("otf", False)):
                original = arguments.output_dir / f"ToolkitUnicode.{suffix}"
                generated = temporary_root / original.name
                build(generated, is_ttf)
                if not original.is_file() or original.read_bytes() != generated.read_bytes():
                    raise SystemExit(f"Unicode fixture is missing or stale: {original}")
                print(f"Verified reproducible Unicode fixture: {original}")
        return
    for suffix, is_ttf in (("ttf", True), ("otf", False)):
        destination = arguments.output_dir / f"ToolkitUnicode.{suffix}"
        build(destination, is_ttf)
        print(f"Built original MIT Unicode fixture: {destination}")


if __name__ == "__main__":
    main()
