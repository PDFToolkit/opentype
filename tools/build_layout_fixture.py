"""Original MIT-licensed synthetic OpenType fixtures; no external font outlines."""
from __future__ import annotations

import argparse
from pathlib import Path
import tempfile

from fontTools.feaLib.builder import addOpenTypeFeaturesFromString
from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib import newTable


FEATURES = """
languagesystem DFLT dflt;
languagesystem latn dflt;
languagesystem latn TRK;
feature ccmp { sub fi by f i; } ccmp;
feature liga {
    sub f f i by ffi;
    sub f i by fi;
    sub f f by ff;
} liga;
feature salt { sub a from [a.alt1 a.alt2]; } salt;
feature smcp { sub a by a.sc; sub b by b.sc; } smcp;
lookup Vertical useExtension { sub a by a.vert; } Vertical;
feature vrt2 { lookup Vertical; } vrt2;
feature locl {
    script latn;
    language TRK exclude_dflt required;
    sub a by a.trk;
} locl;
feature calt { sub a' b by a.alt1; } calt;
"""


def build(destination: Path) -> None:
    glyph_order = [
        ".notdef", "space", "a", "b", "f", "i", "fi", "ffi", "ff",
        "a.sc", "b.sc", "a.alt1", "a.alt2", "a.vert", "a.trk", "smile",
    ]
    fb = FontBuilder(1000, isTTF=True)
    fb.setupGlyphOrder(glyph_order)
    fb.setupCharacterMap({32: "space", 97: "a", 98: "b", 102: "f", 105: "i", 0xFB01: "fi", 0x1F600: "smile"})
    glyphs = {}
    for index, name in enumerate(glyph_order):
        pen = TTGlyphPen(None)
        if name != "space":
            pen.moveTo((40, 0))
            pen.lineTo((300 + index * 5, 0))
            pen.lineTo((300 + index * 5, 500 + index * 3))
            pen.lineTo((40, 500 + index * 3))
            pen.closePath()
        glyphs[name] = pen.glyph()
    fb.setupGlyf(glyphs)
    fb.setupHorizontalMetrics({name: (600, 40) for name in glyph_order})
    fb.setupHorizontalHeader(ascent=800, descent=-200)
    fb.setupNameTable({
        "familyName": "PDFToolkit Layout Fixture", "styleName": "Regular",
        "uniqueFontIdentifier": "PDFToolkitLayoutFixture-1",
        "fullName": "PDFToolkit Layout Fixture", "psName": "PDFToolkitLayoutFixture",
        "version": "Version 1.000", "copyright": "Copyright 2026 PDFToolkit contributors. MIT license.",
    })
    fb.setupOS2(sTypoAscender=800, sTypoDescender=-200, usWinAscent=800, usWinDescent=200)
    fb.setupPost()
    fb.setupMaxp()
    addOpenTypeFeaturesFromString(fb.font, FEATURES)
    # Reproducible OpenType timestamps: 2026-01-01 in seconds since 1904-01-01.
    fb.font["head"].created = 3850070400
    fb.font["head"].modified = 3850070400
    fb.font.recalcTimestamp = False
    destination.parent.mkdir(parents=True, exist_ok=True)
    fb.save(destination)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=Path(__file__).resolve().parents[1] / "tests/fixtures/ToolkitLayout.ttf")
    parser.add_argument("--check", action="store_true", help="Verify reproducibility without changing the committed fixture")
    args = parser.parse_args()
    if args.check:
        with tempfile.TemporaryDirectory(prefix="pdftoolkit-layout-") as directory:
            generated = Path(directory) / "ToolkitLayout.ttf"
            build(generated)
            if generated.read_bytes() != args.output.read_bytes():
                raise SystemExit("The committed layout fixture differs from its original generator.")
        print(f"Verified reproducible original MIT font: {args.output}")
    else:
        build(args.output)
        print(f"Built original MIT synthetic font: {args.output}")


if __name__ == "__main__":
    main()
