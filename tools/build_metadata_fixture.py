"""Original extended metadata fixture, including all four STAT axis-value formats."""
import argparse,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont,newTable
from fontTools.otlLib.builder import buildStatTable
from build_axis_fixture import build as base


def build(destination:Path):
    with tempfile.TemporaryDirectory() as directory:
        path=Path(directory)/'axes.ttf';base(path);font=TTFont(path)
    buildStatTable(font,[
        dict(tag='wdth',name=257,ordering=0,values=[dict(value=100,name='Normal',flags=2),dict(nominalValue=81.25,rangeMinValue=62.5,rangeMaxValue=90,name='Condensed')]),
        dict(tag='wght',name=256,ordering=1,values=[dict(value=400,linkedValue=700,name='Regular',flags=2),dict(value=700,name='Bold')]),
    ],locations=[dict(name='Bold Condensed',location={'wght':700,'wdth':81.25})],elidedFallbackName=2)
    gasp=newTable('gasp');gasp.version=1;gasp.gaspRange={8:10,16:5,65535:15};font['gasp']=gasp
    font['head'].fontRevision=1.25;font['post'].italicAngle=-12.5;font['post'].underlinePosition=-100;font['post'].underlineThickness=50
    font.recalcTimestamp=False;destination.parent.mkdir(parents=True,exist_ok=True);font.save(destination)


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--check',action='store_true');args=parser.parse_args();destination=Path(__file__).resolve().parents[1]/'tests/fixtures/ToolkitMetadata.ttf'
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            path=Path(directory)/'metadata.ttf';build(path);assert path.read_bytes()==destination.read_bytes()
    else:build(destination)
    print('Verified original metadata fixture')
if __name__=='__main__':main()
