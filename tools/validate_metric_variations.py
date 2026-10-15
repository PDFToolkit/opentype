"""Compare vertical metrics and HVAR/VVAR/MVAR interpolation with fontTools and HarfBuzz."""
import argparse
import json
from pathlib import Path
import subprocess
import tempfile
from fontTools.ttLib import TTFont
from fontTools.fontBuilder import FontBuilder
from fontTools.varLib.varStore import VarStoreInstancer
import uharfbuzz as hb
from build_metric_variation_fixture import build


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php',type=Path,required=True);args=parser.parse_args()
    root=Path(__file__).resolve().parents[1];path=root/'tests/fixtures/ToolkitMetricVariations.ttf'
    with tempfile.TemporaryDirectory() as directory:
        regenerated=Path(directory)/'metric.ttf';build(regenerated)
        assert regenerated.read_bytes()==path.read_bytes(), 'Not reproducible'
    tt=TTFont(path,checkChecksums=2);glyphs=tt.getGlyphOrder();axes=tt['fvar'].axes
    requests=[[a,b] for a in (-1,-0.5,0,0.25,0.5,1) for b in (-1,-0.5,0,0.5,1)]
    run=subprocess.run([str(args.php),str(root/'tools/inspect_metric_variations.php'),str(path)],input=json.dumps(requests),text=True,capture_output=True)
    assert run.returncode==0,run.stdout+run.stderr
    actual=json.loads(run.stdout)
    assert actual['verticalMetrics']==[{'advanceHeight':tt['vmtx'].metrics[g][0],'topSideBearing':tt['vmtx'].metrics[g][1]} for g in glyphs]
    header=tt['vhea']
    for key,value in actual['header'].items():
        expected=getattr(header,'tableVersion' if key=='version' else ('numberOfVMetrics' if key=='numOfLongVerMetrics' else key))
        assert value==expected,(key,value,expected)
    count=0
    for coordinates,result in zip(requests,actual['cases'],strict=True):
        location=dict(zip((a.axisTag for a in axes),coordinates))
        h=tt['HVAR'].table;v=tt['VVAR'].table;m=tt['MVAR'].table
        hi=VarStoreInstancer(h.VarStore,axes,location);vi=VarStoreInstancer(v.VarStore,axes,location);mi=VarStoreInstancer(m.VarStore,axes,location)
        font=hb.Font(hb.Face(path.read_bytes()));hb.ot_font_set_funcs(font);font.set_var_coords_normalized(coordinates)
        for index,(glyph,values) in enumerate(zip(glyphs,result['glyphs'],strict=True)):
            expected=[tt['hmtx'].metrics[glyph][0]+hi[h.AdvWidthMap.mapping[glyph]],tt['vmtx'].metrics[glyph][0]+vi[v.AdvHeightMap.mapping[glyph]],hi[h.LsbMap.mapping[glyph]],None,vi[v.TsbMap.mapping[glyph]],vi[v.BsbMap.mapping[glyph]],None]
            assert values==expected,(coordinates,glyph,values,expected)
            assert values[:2]==[font.get_glyph_h_advance(index),-font.get_glyph_v_advance(index)],(coordinates,index,values[:2])
            count+=7
        assert result['mvar']=={record.ValueTag:mi[record.VarIdx] for record in m.ValueRecord}
    with tempfile.TemporaryDirectory() as directory:
        cff=TTFont(root/'tests/fixtures/ToolkitUnicode.otf')
        names=cff.getGlyphOrder();builder=FontBuilder(font=cff,isTTF=False)
        expected=[[1100,50+i,800 if i%2==0 else 900+i] for i in range(len(names))]
        builder.setupVerticalMetrics({g:(v[0],v[1]) for g,v in zip(names,expected,strict=True)})
        builder.setupVerticalHeader(ascent=500,descent=-500)
        builder.setupVerticalOrigins({g:v[2] for g,v in zip(names,expected,strict=True)},defaultVerticalOrigin=800)
        cff.recalcTimestamp=False;path=Path(directory)/'vertical.otf';cff.save(path)
        run=subprocess.run([str(args.php),str(root/'tools/inspect_vertical.php'),str(path)],text=True,capture_output=True)
        assert run.returncode==0,run.stdout+run.stderr
        assert json.loads(run.stdout)==expected
        oracle=hb.Font(hb.Face(path.read_bytes()));hb.ot_font_set_funcs(oracle)
        for i,row in enumerate(expected):
            assert oracle.get_glyph_v_origin(i)[1]==row[2]
    print(json.dumps({'status':'passed','cffVerticalOrigins':len(expected),'instances':len(requests),'glyphMetricComparisons':count,'fontWideComparisons':len(requests)*3,'harfbuzz':hb.version_string(),'harfbuzzScope':'horizontal and vertical advances; side-bearing and MVAR deltas compared with fontTools'},indent=2))


if __name__=='__main__':main()
