"""Original vertical and variable metric fixture, derived from ToolkitAxes."""
import argparse
from pathlib import Path
import tempfile
from fontTools.ttLib import TTFont,newTable
from fontTools.fontBuilder import FontBuilder
from fontTools.varLib.builder import buildVarRegionList,buildVarData,buildVarStore,buildVarIdxMap
from fontTools.ttLib.tables import otTables
from build_axis_fixture import build as base


def build(destination: Path):
    with tempfile.TemporaryDirectory() as directory:
        path=Path(directory)/'base.ttf'
        base(path)
        font=TTFont(path)
    glyphs=font.getGlyphOrder()
    builder=FontBuilder(font=font)
    builder.setupVerticalMetrics({name:(1000 if index == 0 else 1100, 50+index) for index,name in enumerate(glyphs)})
    builder.setupVerticalHeader(ascent=500,descent=-500,lineGap=0)
    regions=buildVarRegionList([{'wght':(0,1,1)},{'wdth':(-1,-1,0)}],['wght','wdth'])
    data=buildVarData([0,1],[[100,-40],[-80,60],[20,80],[0,0]])
    for tag in ('HVAR','VVAR'):
        table=newTable(tag)
        table.table=getattr(otTables,tag)()
        table.table.Version=0x00010000
        table.table.VarStore=buildVarStore(regions,[data])
        mapping=buildVarIdxMap([3 if tag == 'HVAR' and font['hmtx'].metrics[name][0] == 0 else i%3 for i,name in enumerate(glyphs)],glyphs)
        if tag == 'HVAR':
            table.table.AdvWidthMap=mapping
            table.table.LsbMap=buildVarIdxMap([2]*len(glyphs),glyphs)
            table.table.RsbMap=None
        else:
            table.table.AdvHeightMap=mapping
            table.table.TsbMap=buildVarIdxMap([1]*len(glyphs),glyphs)
            table.table.BsbMap=buildVarIdxMap([2]*len(glyphs),glyphs)
            table.table.VOrgMap=None
        font[tag]=table
    mvar=newTable('MVAR');mvar.table=otTables.MVAR()
    mvar.table.Version=0x00010000;mvar.table.Reserved=0;mvar.table.ValueRecordSize=8
    mvar.table.VarStore=buildVarStore(regions,[data]);mvar.table.ValueRecord=[]
    for tag,index in [('hasc',0),('hdsc',1),('vasc',2)]:
        record=otTables.MetricsValueRecord();record.ValueTag=tag;record.VarIdx=index
        mvar.table.ValueRecord.append(record)
    mvar.table.ValueRecordCount=len(mvar.table.ValueRecord)
    font['MVAR']=mvar
    font.recalcTimestamp=False
    destination.parent.mkdir(parents=True,exist_ok=True)
    font.save(destination)


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output',type=Path,default=Path(__file__).resolve().parents[1]/'tests/fixtures/ToolkitMetricVariations.ttf')
    parser.add_argument('--check',action='store_true')
    args=parser.parse_args()
    if args.check:
        with tempfile.TemporaryDirectory() as directory:
            path=Path(directory)/'metric.ttf';build(path)
            assert path.read_bytes()==args.output.read_bytes(), 'Metric fixture differs'
    else:
        build(args.output)
    print(f'Verified original metric fixture: {args.output}')


if __name__=='__main__':main()
