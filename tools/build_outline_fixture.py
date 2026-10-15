"""Original quadratic, attached and transformed TrueType outlines."""
import argparse
from pathlib import Path
import tempfile
from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib.tables._g_l_y_f import Glyph,GlyphComponent,GlyphCoordinates
from fontTools.ttLib.tables.ttProgram import Program


def component(name,flags=0x1000,xy=(0,0),transform=None,points=None):
    value=GlyphComponent();value.glyphName=name;value.flags=flags
    if points is not None:value.firstPt,value.secondPt=points
    else:value.x,value.y=xy
    if transform is not None:value.transform=transform
    return value


def composite(*components):
    glyph=Glyph();glyph.numberOfContours=-1;glyph.components=list(components)
    return glyph


def build(destination:Path):
    names=['.notdef','empty','curve','alloff','offset','scaled','matrix','attached','nested']
    builder=FontBuilder(1000,isTTF=True);builder.setupGlyphOrder(names)
    builder.setupCharacterMap({65+i:g for i,g in enumerate(names)})
    pen=TTGlyphPen(None);pen.moveTo((0,0));pen.lineTo((200,0));pen.lineTo((200,400));pen.closePath()
    triangle=pen.glyph()
    pen=TTGlyphPen(None);pen.moveTo((0,0));pen.qCurveTo((100,300),(300,300),(400,0));pen.lineTo((0,0));pen.closePath()
    curve=pen.glyph();curve.program=Program();curve.program.fromBytecode([0xB0,0,0x21])
    alloff=Glyph();alloff.numberOfContours=1;alloff.coordinates=GlyphCoordinates([(0,100),(100,200),(200,100),(100,0)]);alloff.endPtsOfContours=[3];alloff.flags=bytearray([0,0,0,0]);alloff.program=Program();alloff.program.fromBytecode([])
    empty=Glyph();empty.numberOfContours=0
    glyphs={'.notdef':triangle,'empty':empty,'curve':curve,'alloff':alloff,
        'offset':composite(component('curve',xy=(30,-20))),
        'scaled':composite(component('curve',flags=0x0800,xy=(60,-20),transform=[[0.5,0],[0,0.75]])),
        'matrix':composite(component('curve',xy=(-100,20),transform=[[0.75,0.25],[-0.25,0.5]])),
        'attached':composite(component('.notdef'),component('curve',flags=0,points=(1,0))),
        'nested':composite(component('scaled',xy=(20,30)),component('alloff',xy=(-50,40))),
    }
    builder.setupGlyf(glyphs);builder.setupHorizontalMetrics({g:(600,0) for g in names});builder.setupHorizontalHeader(ascent=800,descent=-200)
    builder.setupNameTable({'familyName':'PDFToolkit Outline Fixture','styleName':'Regular','uniqueFontIdentifier':'PDFToolkitOutline-1','fullName':'PDFToolkit Outline Fixture','psName':'PDFToolkitOutline','version':'Version 1.000'})
    builder.setupOS2(sTypoAscender=800,sTypoDescender=-200,usWinAscent=800,usWinDescent=200)
    builder.setupPost();builder.setupMaxp();builder.font['head'].created=builder.font['head'].modified=3850070400;builder.font.recalcTimestamp=False
    destination.parent.mkdir(parents=True,exist_ok=True);builder.save(destination)


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output',type=Path,default=Path(__file__).resolve().parents[1]/'tests/fixtures/ToolkitOutlines.ttf');parser.add_argument('--check',action='store_true');args=parser.parse_args()
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            path=Path(directory)/'outlines.ttf';build(path);assert path.read_bytes()==args.output.read_bytes()
    else:build(args.output)
    print(f'Verified original outline fixture: {args.output}')


if __name__=='__main__':main()
