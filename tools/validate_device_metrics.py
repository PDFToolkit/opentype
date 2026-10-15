"""Compare device metric declarations and explicit selections with fontTools records."""
import argparse,json,subprocess,tempfile
from pathlib import Path
from fontTools.ttLib import TTFont
from build_device_fixture import build


def compare(path,php,root):
    font=TTFont(path);run=subprocess.run([str(php),str(root/'tools/inspect_device_metrics.php'),str(path)],text=True,capture_output=True);assert run.returncode==0,run.stdout+run.stderr
    actual=json.loads(run.stdout);names=font.getGlyphOrder()
    hdmx={'records':[dict(pixelsPerEm=ppem,maximumWidth=max(widths.values()),widths=[widths[name]for name in names])for ppem,widths in sorted(font['hdmx'].hdmx.items())]}
    assert actual['hdmx']==hdmx
    assert actual['LTSH']=={'verticalPixels':[font['LTSH'].yPels[name]for name in names]}
    vdmx=font['VDMX'];ratios=[]
    def group(index):
        records=vdmx.groups[index];return dict(startSize=min(records),endSize=max(records),heights=[dict(pixelsPerEm=ppem,maximum=hi,minimum=lo)for ppem,(hi,lo)in sorted(records.items())])
    for r in vdmx.ratRanges:ratios.append(dict(characterSet=r['bCharSet'],xRatio=r['xRatio'],yStartRatio=r['yStartRatio'],yEndRatio=r['yEndRatio'],group=group(r['groupIndex'])))
    assert actual['VDMX']==dict(version=vdmx.version,ratios=ratios)
    selected=[]
    for x,y in [(72,72),(144,72),(96,72),(72,96)]:
        records=next(r['group']['heights']for r in ratios if r['characterSet']==1 and(r['xRatio']==0 or x*r['yStartRatio']<=y*r['xRatio']<=x*r['yEndRatio']))
        selected.extend(next((r for r in records if r['pixelsPerEm']==ppem),None)for ppem in [8,10,12,24])
    assert actual['selected']==selected
    return len(names)*4+len(selected)+sum(len(r['group']['heights']) for r in ratios)


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--php',type=Path,required=True);args=parser.parse_args();root=Path(__file__).resolve().parents[1];fixture=root/'tests/fixtures/ToolkitDeviceMetrics.ttf';checks=compare(fixture,args.php,root)
    with tempfile.TemporaryDirectory() as directory:
        path=Path(directory)/'device.ttf';build(path);assert path.read_bytes()==fixture.read_bytes()
        font=TTFont(path);font['VDMX'].version=0;font.save(path);checks+=compare(path,args.php,root)
    print(json.dumps({'status':'passed','fonts':2,'metricAndSelectionChecks':checks,'scope':'precomputed device metrics and explicit selection; no hinting or rasterization'},indent=2))
if __name__=='__main__':main()
