"""Build original MIT layout programs, including every GSUB/GPOS contextual format."""
from __future__ import annotations

import argparse
from pathlib import Path
import tempfile

from fontTools.feaLib.builder import addOpenTypeFeaturesFromString
from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib.tables import otTables as ot
from fontTools.otlLib.builder import buildCoverage, buildSingleSubstSubtable

GLYPHS = [".notdef", "space", "a", "b", "c", "d", "a.alt", "b.alt", "a_b", "acute", "grave", "dot"]
CMAP = {32: "space", 97: "a", 98: "b", 99: "c", 100: "d", 0xE000: "a.alt", 0xE001: "b.alt", 0xE002: "a_b", 0x301: "acute", 0x300: "grave", 0x307: "dot"}
FEATURES = """
languagesystem DFLT dflt;
languagesystem latn dflt;
@BASE = [a b c d a.alt b.alt];
@LIGATURE = [a_b];
@MARK = [acute grave dot];
@TOP = [acute dot];
table GDEF { GlyphClassDef @BASE, @LIGATURE, @MARK, ; } GDEF;
markClass acute <anchor 0 100> @ABOVE;
markClass dot <anchor 20 80> @ABOVE;
markClass grave <anchor 0 0> @BELOW;
feature sng1 { pos a <10 20 30 40>; } sng1;
feature sng2 { pos a <11 21 31 41>; pos b <12 22 32 42>; } sng2;
feature kern { pos a b -70; pos b c -30; } kern;
feature prc2 { pos [a c] [b d] -45; } prc2;
feature prb2 { pos a <10 0 -50 0> b <20 0 -25 0>; } prb2;
feature curs {
    pos cursive a <anchor 0 0> <anchor 500 100>;
    pos cursive b <anchor 0 30> <anchor 400 70>;
    pos cursive c <anchor 0 20> <anchor NULL>;
} curs;
feature crtl {
    lookupflag RightToLeft;
    pos cursive a <anchor 0 0> <anchor 500 100>;
    pos cursive b <anchor 0 30> <anchor 400 70>;
    pos cursive c <anchor 0 20> <anchor NULL>;
} crtl;
feature mark {
    pos base a <anchor 250 700> mark @ABOVE <anchor 220 -100> mark @BELOW;
    pos base b <anchor 300 650> mark @ABOVE;
} mark;
feature mklg {
    pos ligature a_b <anchor 200 700> mark @ABOVE ligComponent <anchor 450 650> mark @ABOVE;
} mklg;
feature mkmk {
    pos mark acute <anchor 0 250> mark @ABOVE;
    pos mark dot <anchor 20 220> mark @ABOVE;
} mkmk;
lookup PosExt useExtension { pos c <4 5 6 7>; } PosExt;
feature pext { lookup PosExt; } pext;
feature lig1 { lookupflag IgnoreMarks; sub a b by a_b; } lig1;
feature flt1 { lookupflag UseMarkFilteringSet @TOP; sub a b by a_b; } flt1;
feature att1 { lookupflag MarkAttachmentType @TOP; sub a b by a_b; } att1;
"""


def obj(name: str, **fields):
    result = getattr(ot, name)()
    for key, value in fields.items():
        setattr(result, key, value)
    return result


def context(font, positioning: bool, chained: bool, format: int, target: int):
    suffix = "Pos" if positioning else "Subst"
    prefix = "Pos" if positioning else "Sub"
    action_name = "PosLookupRecord" if positioning else "SubstLookupRecord"
    action = obj(action_name, SequenceIndex=0, LookupListIndex=target)
    actions = {action_name: [action]}
    coverage = lambda names: buildCoverage(names, font.getReverseGlyphMap())
    result = obj(("ChainContext" if chained else "Context") + suffix, Format=format)
    if format == 3:
        if chained:
            result.BacktrackCoverage = [coverage(["c"])]
            result.InputCoverage = [coverage(["a"]), coverage(["b"])]
            result.LookAheadCoverage = [coverage(["d"])]
        else:
            result.Coverage = [coverage(["a"]), coverage(["b"])]
        for key, value in actions.items():
            setattr(result, key, value)
        return result
    result.Coverage = coverage(["a"])
    if format == 2:
        definition = obj("ClassDef", classDefs={"a": 1, "b": 2, "c": 3, "d": 4})
        if chained:
            result.BacktrackClassDef = definition
            result.InputClassDef = definition
            result.LookAheadClassDef = definition
        else:
            result.ClassDef = definition
    rule_name = ("Chain" if chained else "") + prefix + ("Class" if format == 2 else "") + "Rule"
    rule = obj(rule_name, **actions)
    if chained:
        rule.Backtrack = [3] if format == 2 else ["c"]
        rule.Input = [2] if format == 2 else ["b"]
        rule.LookAhead = [4] if format == 2 else ["d"]
        rule.InputGlyphCount = 2
    else:
        setattr(rule, "Class" if format == 2 else "Input", [2] if format == 2 else ["b"])
        rule.GlyphCount = 2
    set_name = ("Chain" if chained else "") + prefix + ("Class" if format == 2 else "Rule") + "Set"
    rule_set = obj(set_name, **{rule_name: [rule]})
    setattr(result, set_name, [None, rule_set] if format == 2 else [rule_set])
    return result


def add_feature(font, tag: str, feature_tag: str, type: int, subtable, flags=0):
    table = font[tag].table
    index = len(table.LookupList.Lookup)
    table.LookupList.Lookup.append(obj("Lookup", LookupType=type, LookupFlag=flags, SubTable=[subtable]))
    table.LookupList.LookupCount = len(table.LookupList.Lookup)
    feature = obj("FeatureRecord", FeatureTag=feature_tag, Feature=obj("Feature", FeatureParams=None, LookupListIndex=[index]))
    table.FeatureList.FeatureRecord.append(feature)
    # FeatureList tag order is normative; re-index language selections after sorting.
    for script in table.ScriptList.ScriptRecord:
        if script.Script.DefaultLangSys is not None:
            script.Script.DefaultLangSys.FeatureIndex.append(len(table.FeatureList.FeatureRecord) - 1)
    return index


def build(destination: Path) -> None:
    fb = FontBuilder(1000, isTTF=True)
    fb.setupGlyphOrder(GLYPHS)
    fb.setupCharacterMap(CMAP)
    outlines = {}
    for name in GLYPHS:
        pen = TTGlyphPen(None)
        if name != "space":
            pen.moveTo((0, 0)); pen.lineTo((400, 0)); pen.lineTo((400, 700)); pen.closePath()
        outlines[name] = pen.glyph()
    fb.setupGlyf(outlines)
    fb.setupHorizontalMetrics({name: (0 if name in ("acute", "grave", "dot") else 600, 0) for name in GLYPHS})
    fb.setupHorizontalHeader(ascent=800, descent=-200)
    fb.setupNameTable({"familyName": "PDFToolkit Advanced Layout Fixture", "styleName": "Regular", "uniqueFontIdentifier": "PDFToolkitAdvancedLayout-1", "fullName": "PDFToolkit Advanced Layout Fixture", "psName": "PDFToolkitAdvancedLayout", "version": "Version 1.000"})
    fb.setupOS2(sTypoAscender=800, sTypoDescender=-200, usWinAscent=800, usWinDescent=200)
    fb.setupPost(); fb.setupMaxp()
    addOpenTypeFeaturesFromString(fb.font, FEATURES)
    font = fb.font
    gsub = font["GSUB"].table
    target = len(gsub.LookupList.Lookup)
    gsub.LookupList.Lookup.append(obj("Lookup", LookupType=1, LookupFlag=0, SubTable=[buildSingleSubstSubtable({"a": "a.alt"})]))
    pos_target = next(i for i, lookup in enumerate(font["GPOS"].table.LookupList.Lookup) if lookup.LookupType == 1)
    for chained in (False, True):
        for format in (1, 2, 3):
            add_feature(font, "GSUB", f"s{int(chained)}0{format}", 6 if chained else 5, context(font, False, chained, format, target))
            add_feature(font, "GPOS", f"p{int(chained)}0{format}", 8 if chained else 7, context(font, True, chained, format, pos_target))
    reverse = obj("ReverseChainSingleSubst", Format=1, Coverage=buildCoverage(["a"], font.getReverseGlyphMap()), BacktrackCoverage=[], LookAheadCoverage=[buildCoverage(["b", "b.alt"], font.getReverseGlyphMap())], Substitute=["b.alt"])
    add_feature(font, "GSUB", "rvrs", 8, reverse)
    for tag in ("GSUB", "GPOS"):
        table = font[tag].table
        old = list(table.FeatureList.FeatureRecord)
        table.FeatureList.FeatureRecord.sort(key=lambda item: item.FeatureTag)
        remap = {i: table.FeatureList.FeatureRecord.index(record) for i, record in enumerate(old)}
        for script in table.ScriptList.ScriptRecord:
            language = script.Script.DefaultLangSys
            if language is not None:
                language.FeatureIndex = sorted(remap[i] for i in language.FeatureIndex)
        table.FeatureList.FeatureCount = len(table.FeatureList.FeatureRecord)
        table.LookupList.LookupCount = len(table.LookupList.Lookup)
    font["head"].created = font["head"].modified = 3850070400
    font.recalcTimestamp = False
    destination.parent.mkdir(parents=True, exist_ok=True)
    fb.save(destination)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=Path(__file__).resolve().parents[1] / "tests/fixtures/ToolkitAdvanced.ttf")
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "fixture.ttf"
            build(path)
            if path.read_bytes() != args.output.read_bytes():
                raise SystemExit("The advanced layout fixture differs from its reproducible generator.")
    else:
        build(args.output)
    print(f"Verified original advanced layout fixture: {args.output}")


if __name__ == "__main__":
    main()
