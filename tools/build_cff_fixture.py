"""Build original CFF1 and two-axis CFF2 charstring fixtures with every path operator."""
from pathlib import Path
import argparse,tempfile
from fontTools.fontBuilder import FontBuilder
from fontTools.misc.psCharStrings import T2CharString
from fontTools.cffLib import SubrsIndex, FDSelect

PROGRAMS = {
 '.notdef': [20, 0, 'rmoveto', 300, 0, 0, 400, -300, 0, 'rlineto'],
 'line': [20, 0, 'rmoveto', 100, 100, -100, 'hlineto', 100, 100, -100, 'vlineto'],
 'curve': [20, 0, 'rmoveto', 20, 80, 80, 0, 20, -80, 'rrcurveto'],
 'vv': [20, 0, 'rmoveto', 10, 50, 80, 40, -50, 40, -60, 20, 30, 'vvcurveto'],
 'hh': [20, 0, 'rmoveto', 10, 50, 80, 40, -50, 40, -60, 20, 30, 'hhcurveto'],
 'hv': [20, 0, 'rmoveto', 50, 80, 40, -50, 40, -60, 20, 30, 25, 'hvcurveto'],
 'vh': [20, 0, 'rmoveto', 50, 80, 40, -50, 40, -60, 20, 30, 25, 'vhcurveto'],
 'curveline': [20, 0, 'rmoveto', 20, 80, 80, 0, 20, -80, 30, 20, 'rcurveline'],
 'linecurve': [20, 0, 'rmoveto', 30, 20, 20, 80, 80, 0, 20, -80, 'rlinecurve'],
 'hflex': [20, 'hmoveto', 20,30,40,50,60,70,80, 'hflex'],
 'flex': [20, 'vmoveto', 20,30,40,50,60,70,80,90,100,110,120,130,50, 'flex'],
 'hflex1': [20,0,'rmoveto', 20,30,40,50,60,70,80,90,100,'hflex1'],
 'flex1': [20,0,'rmoveto', 20,30,40,50,60,70,80,90,100,110,120,'flex1'],
 'subr': [20,0,'rmoveto', -107,'callsubr',-107,'callgsubr'],
 'hint': [0,20,'hstemhm',30,20,'vstemhm','hintmask',b'\xc0',20,0,'rmoveto',100,200,'rlineto'],
}

def build(destination:Path,cff2=False):
    programs={name:list(program) for name,program in PROGRAMS.items()}
    if not cff2:
        programs.update({'A':[20,0,'rmoveto',100,300,100,-300,'rlineto'], 'acute':[0,0,'rmoveto',30,50,50,0,'rlineto'], 'Aacute':[100,400,65,194]})
    if cff2:
        # Defaults x,y then deltas for x in the two regions, followed by y's deltas.
        programs['blend']=[0,'vsindex',20,0,100,40,60,-20,2,'blend','rmoveto',100,200,'rlineto']
    fb=FontBuilder(1000,isTTF=False)
    order=list(programs);fb.setupGlyphOrder(order)
    fb.setupCharacterMap({0x41+i:name for i,name in enumerate(order[1:])})
    fb.setupHorizontalMetrics({name:(700,20) for name in order})
    fb.setupHorizontalHeader(ascent=800,descent=-200)
    label='ToolkitCff2' if cff2 else 'ToolkitCff'
    fb.setupNameTable({'familyName':label,'styleName':'Regular','uniqueFontIdentifier':label,'fullName':label,'psName':label,'version':'Version 1.0'})
    fb.setupOS2(sTypoAscender=800,sTypoDescender=-200,usWinAscent=900,usWinDescent=200)
    fb.setupPost()
    chars={name:T2CharString(program=([] if cff2 else [700])+program+([] if cff2 else ['endchar'])) for name,program in programs.items()}
    if cff2:
        fb.setupFvar([('wght',100,400,900,'Weight'),('wdth',62.5,100,200,'Width')],[])
        fb.setupCFF2(chars,[{'BlueValues':[0,20],'StdHW':20},{'BlueValues':[0,30],'StdHW':30}],regions=[{'wght':(0,1,1)},{'wdth':(-1,-1,0)}])
        top=fb.font['CFF2'].cff.topDictIndex[0]
        select=FDSelect(format=4);select.gidArray=[0 if index%2==0 else 1 for index in range(len(order))]
        top.FDSelect=select;top.CharStrings.fdSelect=select
        for index,name in enumerate(order):
            chars[name].fdSelectIndex=select.gidArray[index];chars[name].private=top.FDArray[select.gidArray[index]].Private
        privates=[fd.Private for fd in top.FDArray]
    else:
        fb.setupCFF(label,{'FullName':label},chars,{'defaultWidthX':700,'nominalWidthX':0})
        top=fb.font['CFF '].cff.topDictIndex[0];privates=[top.Private]
        top.Encoding=['.notdef']*256
        for index,name in enumerate(order[1:15]):top.Encoding[20+index]=name
        top.Encoding[90]='A';top.Encoding[91]='acute';top.Encoding[92]='Aacute'
    global_subrs=fb.font['CFF2' if cff2 else 'CFF '].cff.GlobalSubrs
    for private in privates:
        private.Subrs=SubrsIndex()
        private.Subrs.append(T2CharString(program=[30,40,'rlineto']+([] if cff2 else ['return']),private=private,globalSubrs=global_subrs))
    global_subrs.append(T2CharString(program=[50,60,'rlineto']+([] if cff2 else ['return']),private=privates[0],globalSubrs=global_subrs))
    fb.font['head'].created=fb.font['head'].modified=2082844800
    fb.font.recalcTimestamp=False
    destination.parent.mkdir(parents=True,exist_ok=True);fb.save(destination)

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--check',action='store_true');args=parser.parse_args();root=Path(__file__).resolve().parents[1]
    for name,cff2 in [('ToolkitCff.otf',False),('ToolkitCff2.otf',True)]:
        destination=root/'tests/fixtures'/name
        if args.check:
            with tempfile.TemporaryDirectory() as directory:
                path=Path(directory)/name;build(path,cff2);assert path.read_bytes()==destination.read_bytes(),name
        else:build(destination,cff2)
    print('Verified original CFF/CFF2 fixtures')
if __name__=='__main__':main()
