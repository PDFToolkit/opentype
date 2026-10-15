"""Compare complete head/hhea/maxp/post/gasp/STAT objects against fontTools."""
import argparse,json,subprocess,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont
from build_metadata_fixture import build


def compare(path,php,root):
    font=TTFont(path);run=subprocess.run([str(php),str(root/'tools/inspect_headers.php'),str(path)],text=True,capture_output=True);assert run.returncode==0,run.stdout+run.stderr
    actual=json.loads(run.stdout);count=0
    aliases={'head':{'checksumAdjustment':'checkSumAdjustment'},'hhea':{'ascender':'ascent','descender':'descent'},'maxp':{'version':'tableVersion'},'post':{}}
    for name in ['head','hhea','maxp','post']:
        expected={}
        for key in actual[name]:
            if key=='bounds':expected[key]={k:getattr(font[name],k) for k in ['xMin','yMin','xMax','yMax']}
            elif name=='post' and key=='version':expected[key]=round(font[name].formatType*65536)
            elif key=='glyphNames':expected[key]=None if font['post'].formatType==3 else font.getGlyphOrder()
            else:expected[key]=getattr(font[name],aliases[name].get(key,key),None)
        assert actual[name]==expected,(path,name,actual[name],expected);count+=len(expected)
    if 'gasp' in font:
        assert actual['gasp']=={'version':font['gasp'].version,'ranges':[{'maximumPpem':ppem,'behavior':flags}for ppem,flags in sorted(font['gasp'].gaspRange.items())]};count+=len(font['gasp'].gaspRange)*2
    if 'STAT' in font:
        stat=font['STAT'].table
        expected={'minorVersion':stat.Version&65535,'axes':[{'tag':{'value':axis.AxisTag},'nameId':axis.AxisNameID,'ordering':axis.AxisOrdering}for axis in stat.DesignAxisRecord.Axis],'values':[],'elidedFallbackNameId':getattr(stat,'ElidedFallbackNameID',None)}
        for value in stat.AxisValueArray.AxisValue:
            coords=[{'axisIndex':v.AxisIndex,'value':v.Value}for v in value.AxisValueRecord]if value.Format==4 else [{'axisIndex':value.AxisIndex,'value':value.NominalValue if value.Format==2 else value.Value}]
            expected['values'].append({'format':value.Format,'nameId':value.ValueNameID,'flags':value.Flags,'coordinates':coords,'rangeMinimum':getattr(value,'RangeMinValue',None),'rangeMaximum':getattr(value,'RangeMaxValue',None),'linkedValue':getattr(value,'LinkedValue',None)})
        assert actual['STAT']==expected,(path,'STAT',actual['STAT'],expected);count+=len(expected['values'])+len(expected['axes'])
    return count


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--php',type=Path,required=True);args=parser.parse_args();root=Path(__file__).resolve().parents[1]
    files=sorted((root/'tests/fixtures').glob('*.ttf'))+sorted((root/'tests/fixtures').glob('*.otf'));fields=sum(compare(path,args.php,root)for path in files)
    with tempfile.TemporaryDirectory() as directory:
        fixture=Path(directory)/'metadata.ttf';build(fixture);assert fixture.read_bytes()==(root/'tests/fixtures/ToolkitMetadata.ttf').read_bytes()
        for version in [0x10000,0x10001]:
            font=TTFont(fixture);stat=font['STAT'].table;stat.Version=version;stat.AxisValueArray.AxisValue=[v for v in stat.AxisValueArray.AxisValue if v.Format!=4];stat.AxisValueCount=len(stat.AxisValueArray.AxisValue)
            path=Path(directory)/f'stat-{version}.ttf';font.save(path);fields+=compare(path,args.php,root)
    print(json.dumps({'status':'passed','fonts':len(files)+2,'fieldGroups':fields,'scope':'complete header, profile, PostScript, rasterization recommendation and STAT records'},indent=2))
if __name__=='__main__':main()
