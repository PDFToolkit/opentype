"""Compare native unhinted Type 2 geometry with fontTools and HarfBuzz."""
import argparse,json,subprocess,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont
from fontTools.pens.recordingPen import DecomposingRecordingPen
import uharfbuzz as hb
from build_cff_fixture import build
from validate_outlines import canonical


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--php',type=Path,required=True);args=parser.parse_args();root=Path(__file__).resolve().parents[1]
    glyphs=instances=0
    for path in sorted((root/'tests/fixtures').glob('*.otf')):
        if path.name in ['ToolkitCff.otf','ToolkitCff2.otf']:
            with tempfile.TemporaryDirectory() as directory:
                temporary=Path(directory)/path.name;build(temporary,path.name=='ToolkitCff2.otf');assert temporary.read_bytes()==path.read_bytes()
        font=TTFont(path);cff2='CFF2' in font
        locations=[[-1,-1],[-1,0],[0,0],[1,0],[0,-1],[1,-1],[.5,-.5],[1,1]] if cff2 else [None]
        oracle=hb.Font(hb.Face(path.read_bytes()));hb.ot_font_set_funcs(oracle)
        funcs=hb.DrawFuncs();funcs.set_move_to_func(lambda x,y,state:state.append(['Move',[[x,y]]]))
        funcs.set_line_to_func(lambda x,y,state:state.append(['Line',[[x,y]]]))
        funcs.set_cubic_to_func(lambda ax,ay,bx,by,x,y,state:state.append(['Cubic',[[ax,ay],[bx,by],[x,y]]]))
        funcs.set_close_path_func(lambda state:state.append(['Close',[]]))
        for location in locations:
            command=[str(args.php),str(root/'tools/inspect_cff.php'),str(path)]
            if location is not None:command.append(json.dumps(location));oracle.set_var_coords_normalized(location)
            run=subprocess.run(command,text=True,capture_output=True);assert run.returncode==0,run.stdout+run.stderr
            results=json.loads(run.stdout)
            glyphset=font.getGlyphSet(location=dict(zip(['wght','wdth'],location)) if location else None,normalized=True)
            for index,(name,result) in enumerate(zip(font.getGlyphOrder(),results,strict=True)):
                pen=DecomposingRecordingPen(glyphset);glyphset[name].draw(pen)
                names={'moveTo':'Move','lineTo':'Line','curveTo':'Cubic','closePath':'Close'}
                expected=[[names[op],[list(p) for p in points]] for op,points in pen.value]
                assert canonical(result['path'])==canonical(expected),(path,name,location,result['path'],expected)
                drawn=[];oracle.draw_glyph(index,funcs,drawn)
                assert canonical(result['path'])==canonical(drawn),(path,name,location,result['path'],drawn)
                if not cff2:
                    assert result['width']==font['CFF '].cff.topDictIndex[0].CharStrings[name].width,(name,'width')
                glyphs+=1
            instances+=1
    print(json.dumps({'status':'passed','instances':instances,'glyphs':glyphs,'harfbuzz':hb.version_string(),'scope':'unhinted CFF/CFF2 cubic paths, subroutines and explicit CFF2 blend coordinates'},indent=2))
if __name__=='__main__':main()
