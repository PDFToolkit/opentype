"""Cross-check sparse gvar/IUP, composites, phantom metrics and cvar independently."""
import argparse,copy,itertools,json,subprocess,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont
from fontTools.ttLib.tables._g_l_y_f import GlyphCoordinates
from fontTools.ttLib.ttGlyphSet import _setCoordinates
from fontTools.varLib.models import supportScalar
from fontTools.varLib.iup import iup_delta
from fontTools.varLib.instancer import instantiateVariableFont
from validate_outlines import canonical
from build_gvar_fixture import build
import uharfbuzz as hb


def close(actual,expected):
    if isinstance(actual,(float,int)):return abs(actual-expected)<1e-7
    if isinstance(actual,str):return actual==expected
    return len(actual)==len(expected) and all(close(a,b) for a,b in zip(actual,expected))


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--php',type=Path,required=True);args=parser.parse_args();root=Path(__file__).resolve().parents[1];path=root/'tests/fixtures/ToolkitGvar.ttf'
    with tempfile.TemporaryDirectory() as directory:
        temporary=Path(directory)/'gvar.ttf';build(temporary);assert temporary.read_bytes()==path.read_bytes()
    original=TTFont(path);oracle=hb.Font(hb.Face(path.read_bytes()));hb.ot_font_set_funcs(oracle)
    funcs=hb.DrawFuncs();funcs.set_move_to_func(lambda x,y,state:state.append(['Move',[[x,y]]]))
    funcs.set_line_to_func(lambda x,y,state:state.append(['Line',[[x,y]]]))
    funcs.set_quadratic_to_func(lambda ax,ay,x,y,state:state.append(['Quadratic',[[ax,ay],[x,y]]]))
    funcs.set_close_path_func(lambda state:state.append(['Close',[]]))
    count=0
    for wght,wdth in itertools.product([-1,-.5,0,.5,1],[-1,-.5,-.25,0,1]):
        location={'wght':wght,'wdth':wdth};oracle.set_var_coords_normalized([wght,wdth])
        run=subprocess.run([str(args.php),str(root/'tools/inspect_gvar.php'),str(path),json.dumps([wght,wdth])],text=True,capture_output=True);assert run.returncode==0,run.stdout+run.stderr
        actual=json.loads(run.stdout);font=copy.deepcopy(original);glyphs={};phantoms={}
        for name in font.getGlyphOrder():
            glyf=original['glyf'];coords,control=glyf._getCoordinatesAndControls(name,original['hmtx'].metrics,original['vmtx'].metrics)
            total=coords.copy()
            for variation in original['gvar'].variations[name]:
                scalar=supportScalar(location,variation.axes)
                if scalar:
                    ends=control.endPts if control.numberOfContours>=1 else list(range(len(control.endPts)))
                    delta=iup_delta(variation.coordinates,coords,ends) if None in variation.coordinates else variation.coordinates
                    total+=GlyphCoordinates(delta)*scalar
            phantoms[name]=[list(p) for p in total[-4:]]
            glyph=copy.deepcopy(glyf[name]);_setCoordinates(glyph,total,glyf,recalcBounds=False);glyphs[name]=glyph
        font['glyf'].glyphs=glyphs
        for index,(name,result) in enumerate(zip(font.getGlyphOrder(),actual['glyphs'],strict=True)):
            points,ends,flags=font['glyf'][name].getCoordinates(font['glyf']);expected=[list(p) for p in points]
            assert close(result['points'],expected),(name,location,result['points'],expected)
            assert result['ends']==list(ends)
            assert close(result['phantoms'],phantoms[name]),(name,'phantoms',location,result['phantoms'],phantoms[name])
            native=[[op,[[x-result['phantoms'][0][0],y] for x,y in pts]] for op,pts in result['path']]
            drawn=[];oracle.draw_glyph(index,funcs,drawn)
            assert close(canonical(native),canonical(drawn)),(name,location,canonical(native),canonical(drawn))
            assert oracle.get_glyph_h_advance(index)==round(result['phantoms'][1][0]-result['phantoms'][0][0]),(name,'hadvance')
            assert oracle.get_glyph_v_advance(index)==-round(result['phantoms'][2][1]-result['phantoms'][3][1]),(name,'vadvance')
            count+=1
        user={'wght':400+wght*(300 if wght<0 else 500),'wdth':100+wdth*(37.5 if wdth<0 else 100)}
        instantiated=instantiateVariableFont(original,user,inplace=False)
        assert actual['cvt']==list(instantiated['cvt '].values),(location,actual['cvt'],instantiated['cvt '].values)
    # USE_MY_METRICS selects child phantom values even for transformed components.
    metrics_cases=0
    for name in ['offset','scaled','matrix']:
        font=TTFont(path);glyph=font['glyf'][name];glyph.components[0].flags|=0x200
        font['gvar'].variations[name][0].coordinates[len(glyph.components)+1]=(190,0)
        with tempfile.TemporaryDirectory() as directory:
            variant=Path(directory)/'metrics.ttf';font.save(variant)
            hbfont=hb.Font(hb.Face(variant.read_bytes()));hb.ot_font_set_funcs(hbfont)
            for location in [[0,0],[1,-.5]]:
                hbfont.set_var_coords_normalized(location)
                run=subprocess.run([str(args.php),str(root/'tools/inspect_gvar.php'),str(variant),json.dumps(location)],text=True,capture_output=True);assert run.returncode==0,run.stdout+run.stderr
                result=json.loads(run.stdout)['glyphs'][font.getGlyphID(name)]
                assert hbfont.get_glyph_h_advance(font.getGlyphID(name))==result['phantoms'][1][0]-result['phantoms'][0][0]
                assert hbfont.get_glyph_v_advance(font.getGlyphID(name))==-(result['phantoms'][2][1]-result['phantoms'][3][1])
                metrics_cases+=1
    print(json.dumps({'status':'passed','instances':25,'glyphs':count,'cvtValues':100,'useMyMetricsCases':metrics_cases,'harfbuzz':hb.version_string(),'scope':'unhinted point interpolation, sparse IUP, nested composite geometry and phantom advances; cvar design values'},indent=2))
if __name__=='__main__':main()
