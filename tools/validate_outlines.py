"""Compare all native quadratic points with fontTools and drawing geometry with HarfBuzz."""
import argparse,json,subprocess,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont
import uharfbuzz as hb
from build_outline_fixture import build


def canonical(commands):
    # HarfBuzz may emit the implicit closing line explicitly.
    result=[];start=None;current=None
    for operation,points in commands:
        if operation=='Move':start=points[0];current=start
        if operation=='Close':
            if current!=start:result.append(['Line',[start]])
        if operation=='Line' and points[0]==current:continue
        result.append([operation,points])
        if points:current=points[-1]
    return result


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--php',type=Path,required=True);args=parser.parse_args()
    root=Path(__file__).resolve().parents[1]
    with tempfile.TemporaryDirectory() as directory:
        path=Path(directory)/'outline.ttf';build(path);assert path.read_bytes()==(root/'tests/fixtures/ToolkitOutlines.ttf').read_bytes()
    glyph_count=point_count=0
    for path in sorted((root/'tests/fixtures').glob('*.ttf')):
        font=TTFont(path);run=subprocess.run([str(args.php),str(root/'tools/inspect_outlines.php'),str(path)],text=True,capture_output=True)
        assert run.returncode==0,run.stdout+run.stderr
        actual=json.loads(run.stdout);glyf=font['glyf'];oracle=hb.Font(hb.Face(path.read_bytes()));hb.ot_font_set_funcs(oracle)
        funcs=hb.DrawFuncs()
        funcs.set_move_to_func(lambda x,y,state:state.append(['Move',[[x,y]]]))
        funcs.set_line_to_func(lambda x,y,state:state.append(['Line',[[x,y]]]))
        funcs.set_quadratic_to_func(lambda cx,cy,x,y,state:state.append(['Quadratic',[[cx,cy],[x,y]]]))
        funcs.set_close_path_func(lambda state:state.append(['Close',[]]))
        for index,(name,result) in enumerate(zip(font.getGlyphOrder(),actual,strict=True)):
            glyph=glyf[name];coordinates,ends,flags=glyph.getCoordinates(glyf)
            expected=[[x,y,bool(flag&1)] for (x,y),flag in zip(coordinates,flags,strict=True)]
            assert result['points']==expected,(path,name,result['points'],expected)
            assert result['ends']==list(ends),(path,name)
            assert result['instructions']==(glyph.program.getBytecode().hex() if hasattr(glyph,'program') else '')
            drawn=[];oracle.draw_glyph(index,funcs,drawn)
            # HarfBuzz adjusts outline x by hmtx lsb-xMin; compare in the same frame.
            shift=font['hmtx'].metrics[name][1]-(glyph.xMin if hasattr(glyph,'xMin') else 0)
            native=[[op,[[x+shift,y] for x,y in pts]] for op,pts in result['path']]
            assert canonical(native)==canonical(drawn),(path,name,canonical(native),canonical(drawn))
            glyph_count+=1;point_count+=len(expected)
    print(json.dumps({'status':'passed','glyphs':glyph_count,'points':point_count,'harfbuzz':hb.version_string(),'scope':'unhinted default-instance points and quadratic geometry; no bytecode or variation execution'},indent=2))


if __name__=='__main__':main()
