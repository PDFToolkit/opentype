"""Original precomputed device-metric fixture; values are declarations, not rasterizer output."""
import argparse,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont,newTable
from build_metadata_fixture import build as base


def build(destination:Path):
    with tempfile.TemporaryDirectory() as directory:
        path=Path(directory)/'metadata.ttf';base(path);font=TTFont(path)
    glyphs=font.getGlyphOrder();font['head'].flags |= 16
    hdmx=newTable('hdmx');hdmx.hdmx={ppem:{name:(index*3+ppem)//2 for index,name in enumerate(glyphs)} for ppem in [8,12,24]};font['hdmx']=hdmx
    ltsh=newTable('LTSH');ltsh.yPels={name:[1,2,50,55,100,200,255][i%7] for i,name in enumerate(glyphs)};font['LTSH']=ltsh
    vdmx=newTable('VDMX');vdmx.version=1;vdmx.numRecs=2;vdmx.numRatios=3
    vdmx.ratRanges=[dict(bCharSet=1,xRatio=1,yStartRatio=1,yEndRatio=1,groupIndex=0),dict(bCharSet=1,xRatio=4,yStartRatio=2,yEndRatio=3,groupIndex=0),dict(bCharSet=1,xRatio=0,yStartRatio=0,yEndRatio=0,groupIndex=1)]
    vdmx.groups=[{8:(7,-2),12:(10,-3),24:(19,-5)},{8:(8,-2),12:(11,-3),24:(20,-5)}];font['VDMX']=vdmx
    font.recalcTimestamp=False;destination.parent.mkdir(parents=True,exist_ok=True);font.save(destination)


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--check',action='store_true');args=parser.parse_args();destination=Path(__file__).resolve().parents[1]/'tests/fixtures/ToolkitDeviceMetrics.ttf'
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            path=Path(directory)/'device.ttf';build(path);assert path.read_bytes()==destination.read_bytes()
    else:build(destination)
    print('Verified original device metric fixture')
if __name__=='__main__':main()
