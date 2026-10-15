"""Original gvar/cvar fixture with two axes, sparse IUP, composite and phantom deltas."""
import argparse,array,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont,newTable
from fontTools.fontBuilder import FontBuilder
from fontTools.ttLib.tables.TupleVariation import TupleVariation
from build_outline_fixture import build as base


def build(destination:Path):
    with tempfile.TemporaryDirectory() as directory:
        path=Path(directory)/'outlines.ttf';base(path);font=TTFont(path)
    fb=FontBuilder(font=font);fb.setupFvar([('wght',100,400,900,'Weight'),('wdth',62.5,100,200,'Width')],[])
    fb.setupVerticalMetrics({name:(1000,80) for name in font.getGlyphOrder()});fb.setupVerticalHeader(ascent=800,descent=-200)
    gvar=newTable('gvar');gvar.version=1;gvar.reserved=0;gvar.variations={}
    for name in font.getGlyphOrder():
        glyph=font['glyf'][name]
        count=len(glyph.components) if glyph.isComposite() else len(glyph.coordinates) if glyph.numberOfContours else 0
        deltas=[None]*(count+4)
        if glyph.isComposite():
            for index,component in enumerate(glyph.components):
                if hasattr(component,'x'):deltas[index]=(20*(index+1),-10*(index+1))
        elif count:
            deltas[0]=(0,20);deltas[count-1]=(120,60)
        deltas[count]=(10,0);deltas[count+1]=(90,0);deltas[count+2]=(0,40);deltas[count+3]=(0,-60)
        second=[(20,30)]*count+[(0,0),(40,0),(0,10),(0,-10)]
        if glyph.isComposite():
            second=[(10,20) if hasattr(c,'x') else None for c in glyph.components]+second[count:]
        gvar.variations[name]=[TupleVariation({'wght':(0,1,1)},deltas),TupleVariation({'wdth':(-1,-.5,0)},second)]
    font['gvar']=gvar
    cvt=newTable('cvt ');cvt.values=array.array('h',[40,80,-20,200]);font['cvt ']=cvt
    cvar=newTable('cvar');cvar.version=1;cvar.variations=[TupleVariation({'wght':(0,1,1)},[20,None,-10,40]),TupleVariation({'wdth':(-1,-.5,0)},[None,30,10,None])];font['cvar']=cvar
    font.recalcTimestamp=False;destination.parent.mkdir(parents=True,exist_ok=True);font.save(destination)


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--check',action='store_true');args=parser.parse_args();destination=Path(__file__).resolve().parents[1]/'tests/fixtures/ToolkitGvar.ttf'
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            path=Path(directory)/'gvar.ttf';build(path);assert path.read_bytes()==destination.read_bytes()
    else:build(destination)
    print('Verified original gvar/cvar fixture')
if __name__=='__main__':main()
