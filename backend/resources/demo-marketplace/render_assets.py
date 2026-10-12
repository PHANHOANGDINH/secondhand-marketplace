"""Render original product illustrations; no external imagery or runtime dependency.
Optional development tool: Python + Pillow. Output: 960x720 WebP.
"""
import json
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parent
S = 2

def font(size, bold=False):
    candidates = [Path("C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf"),
                  Path("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")]
    for path in candidates:
        if path.exists():
            return ImageFont.truetype(str(path), size*S)
    return ImageFont.load_default()

def render(entry, view):
    palettes = {"phones": ((239,244,250),(213,226,239)), "laptops": ((243,245,249),(218,227,237)),
                "accessories": ((241,245,243),(218,231,224)), "home": ((249,246,240),(234,225,208)),
                "fashion": ((247,241,239),(232,218,212)), "books": ((243,244,237),(224,230,210))}
    top,bottom = palettes[entry["group"]]
    canvas=Image.new("RGB",(960*S,720*S)); d=ImageDraw.Draw(canvas)
    for y in range(720*S):
        t=y/(720*S-1)
        d.line((0,y,960*S,y),fill=tuple(round(a*(1-t)+b*t) for a,b in zip(top,bottom)))
    shadow=Image.new("RGBA",canvas.size); sd=ImageDraw.Draw(shadow)
    sd.ellipse((220*S,505*S,760*S,575*S),fill=(44,53,65,52))
    canvas=Image.alpha_composite(canvas.convert("RGBA"),shadow.filter(ImageFilter.GaussianBlur(22*S)))
    obj=Image.new("RGBA",(960*S,720*S)); p=ImageDraw.Draw(obj)
    def box(x0,y0,x1,y1,fill,r=0,outline=None,width=1):
        p.rounded_rectangle(tuple(round(x*S) for x in (x0,y0,x1,y1)),radius=r*S,fill=fill,outline=outline,width=width*S)
    def ellipse(x0,y0,x1,y1,fill,outline=None,width=1):
        p.ellipse(tuple(round(x*S) for x in (x0,y0,x1,y1)),fill=fill,outline=outline,width=width*S)
    def line(points,fill,width=2): p.line([(round(x*S),round(y*S)) for x,y in points],fill=fill,width=width*S,joint="curve")
    def poly(points,fill,outline=None): p.polygon([(round(x*S),round(y*S)) for x,y in points],fill=fill,outline=outline)
    def text(x,y,value,size=20,color="#253041",bold=False): p.text((x*S,y*S),value,font=font(size,bold),fill=color)
    kind=entry["art"]; key=entry["key"]
    if kind in ("phone","tablet"):
        colors={"iphone-13":"#54748d","galaxy-s22":"#667b6d","redmi-note-12":"#9299a5","ipad-air-5":"#928aa8"}
        body=colors[key]; left,right=(255,565) if kind=="tablet" else (328,525)
        box(left-45,130,right-45,527,body,28,"#afb9c4",2)
        box(left-31,146,left+48,230,body,13,"#bdcbd4",1)
        for i in range(1 if kind=="tablet" else (2 if key=="iphone-13" else 3)):
            x=left-12+(i%2)*34; y=164+(i//2)*32
            ellipse(x-10,y-10,x+12,y+12,"#161f2b","#8da6b8",3);ellipse(x-3,y-4,x+4,y+3,"#365774")
        box(left+35,143,right+65,540,"#b9c2cc",27,"#617280",2)
        box(left+41,149,right+59,533,"#141c29",23)
        box(left+48,156,right+52,525,"#204e6d",19)
        ellipse(left+48,200,right+48,456,"#4773a7")
        ellipse(left+48,365,right+48,521,"#a5bed5")
        box(left+99,158,right+1,174,"#141c29",7)
        line([(left+103,511),(right+1,511)],"#ecf3f8",3)
        text(left+68,227,"09:41",34,"#f7fbff",True)
    elif kind=="laptop":
        metal="#a3adb8" if key!="thinkpad-t14" else "#333b44"
        box(253,166,709,463,metal,13,"#727e8a",2);box(264,176,698,445,"#17232f",7)
        box(275,187,687,431,"#567d95",2)
        poly([(275,370),(410,230),(575,431),(275,431)],"#98b7bd")
        poly([(390,431),(594,230),(687,335),(687,431)],"#284b68")
        ellipse(477,179,483,185,"#8c98a3")
        poly([(253,461),(709,461),(777,530),(182,530)],metal,"#7e8994")
        poly([(182,530),(777,530),(765,544),(195,544)],"#87929d")
        for row in range(4):
            for col in range(14):
                x=285+col*27-row*9; y=465+row*11
                box(x,y,x+21,y+7,"#343e47",2)
        box(409,507,552,528,"#bdc5ce",3,"#788997",1)
        if key=="thinkpad-t14":ellipse(475,486,481,492,"#c44140")
        text(445,415,"AIR" if key=="macbook-air-m1" else "NOTEBOOK",11,"#c8dce7",True)
    elif kind=="earbuds":
        box(343,328,619,525,"#fafaf6",46,"#bdc7ce",3);line([(348,386),(614,386)],"#bdc7ce",2)
        ellipse(473,428,484,439,"#78a788")
        for x,flip in [(363,False),(548,True)]:
            ellipse(x-27,205,x+34,261,"#fcfcf8","#bac7d1",2)
            box(x-10,248,x+13,335,"#fbfbf7",11,"#bcc9d4",2)
            ellipse(x-8,217,x+10,237,"#45515c");line([(x-6,309),(x+8,309)],"#d2dae0",2)
    elif kind=="mouse":
        ellipse(332,183,639,514,"#35434b","#6b7b84",3)
        box(371,178,610,455,"#46545d",85)
        line([(490,189),(490,295)],"#15232e",3)
        box(475,235,503,295,"#1c2730",12,"#82909a",2)
        for y in range(244,289,6):line([(481,y),(497,y)],"#9da9b3",2)
        poly([(355,373),(395,450),(384,514),(319,480),(313,421)],"#2a353e")
        box(344,324,389,340,"#64717a",5);box(349,347,398,362,"#64717a",5)
        text(465,417,"MX",24,"#8398a2",True)
    elif kind=="keyboard":
        poly([(237,229),(704,229),(764,482),(186,482)],"#77838d")
        box(201,243,746,481,"#26313b",12,"#8495a0",3)
        for row in range(5):
            for col in range(15):
                x=215+col*34; y=257+row*39
                color="#465362" if col<12 else "#80919c"
                if row==0 and col==0: color="#b7704a"
                box(x,y,x+28,y+30,color,4,"#172430",1)
                text(x+7,y+7, str((col+row)%10) if row==0 else chr(65+(col+row*7)%26),9,"#e7edf2")
        box(331,416,570,449,"#80919c",4)
    elif kind=="headphones":
        p.arc((315*S,143*S,647*S,492*S),180,360,fill="#27343e",width=40*S)
        p.arc((329*S,157*S,633*S,478*S),180,360,fill="#667985",width=9*S)
        box(301,300,377,504,"#35434d",29,"#6e7d89",3);box(584,300,660,504,"#35434d",29,"#6e7d89",3)
        box(360,320,401,484,"#1d2c36",18);box(561,320,602,484,"#1d2c36",18)
        line([(333,326),(333,473)],"#72828c",2);line([(629,326),(629,473)],"#72828c",2)
    elif kind=="fryer":
        box(330,185,640,525,"#303b45",58,"#5c6b76",3)
        box(348,208,622,294,"#17232c",27);text(407,227,"180°  15",26,"#c6d5de",True)
        line([(348,326),(622,326)],"#9da9af",2);box(445,351,527,463,"#a3acb3",16,"#c7cfd2",2)
        box(456,365,516,450,"#5a6b76",12);box(365,516,404,536,"#202c35",6);box(566,516,605,536,"#202c35",6)
    elif kind=="coffee":
        box(331,189,638,526,"#86949d",19,"#44515b",3)
        box(350,212,619,297,"#d9dfe0",8)
        for x in (401,483,565):ellipse(x-17,239,x+17,273,"#4f626e","#edf2ef",3)
        box(356,305,613,500,"#2c3942",9)
        box(452,298,507,321,"#d8e0e3",6);box(477,314,597,335,"#26343d",9)
        box(405,394,537,472,"#f6f1e6",8);p.arc((507*S,403*S,567*S,465*S),260,100,fill="#f6f1e6",width=10*S)
        box(366,485,605,512,"#bbc7cc",4)
        for x in range(381,598,12):line([(x,489),(x,506)],"#7b8c98",3)
        line([(480,345),(480,388)],"#b78756",2)
    elif kind=="fan":
        ellipse(378,496,594,553,"#d6dee1","#a2b3bd",2);box(477,308,494,523,"#eef0eb",8,"#a5b7bd",2)
        ellipse(356,145,621,410,"#e7eae5","#adbfc5",5)
        for r in range(20,125,10):ellipse(488-r,277-r,488+r,277+r,None,"#b9c8cc",1)
        for angle in range(0,360,12):
            import math
            line([(488,277),(488+125*math.cos(math.radians(angle)),277+125*math.sin(math.radians(angle)))],"#becdd1",1)
        ellipse(463,252,513,302,"#eff0e9","#acbbc4",3)
    elif kind=="jacket":
        poly([(369,183),(436,168),(527,168),(595,184),(703,329),(638,386),(589,319),(585,540),(378,540),(371,319),(322,386),(259,329)],"#577c9d","#365f80")
        poly([(436,168),(481,203),(527,168),(501,232),(481,210),(461,232)],"#93afc5")
        line([(481,218),(481,536)],"#c3c6b8",3)
        for x in (399,512):box(x,262,x+55,312,"#6c90ae",4,"#a7b6bf",2);poly([(x,262),(x+55,262),(x+27,277)],"#91acc0")
        for y in range(247,533,53):ellipse(483,y,489,y+6,"#d2c9af")
        line([(381,504),(580,504)],"#a3b4bb",2)
    elif kind=="shoes":
        for offset in (0,77):
            y=300+offset
            poly([(305,y-69),(370,y-112),(420,y-51),(531,y-23),(637,y+12),(679,y+49),(673,y+74),(284,y+74),(278,y+48)],"#f4f2e8","#bcc5c4")
            box(280,y+65,679,y+92,"#e6e5da",12,"#aabbbf",2)
            poly([(307,y-70),(338,y-86),(369,y-109),(380,y-73),(344,y-40)],"#688b7a")
            for x in range(402,522,22):line([(x,y-36),(x+42,y-14)],"#c0c7c4",3)
            for x in range(485,558,15):ellipse(x,y+11,x+4,y+15,"#7c9293")
    elif kind=="backpack":
        p.arc((437*S,171*S,532*S,249*S),180,360,fill="#4d6471",width=15*S)
        box(334,201,634,546,"#8498a3",55,"#536d7b",3)
        box(347,216,621,528,"#91a4af",44)
        box(366,366,603,527,"#677f8d",24,"#485e6b",2)
        line([(375,377),(594,377)],"#d0d9dc",3);line([(351,287),(615,287)],"#3d5564",3)
        box(466,320,511,339,"#435d6c",4);box(599,368,610,397,"#b1bec2",2)
    elif kind=="book":
        cover="#355979" if key=="clean-code" else "#777e56"
        poly([(323,205),(587,167),(632,506),(365,550)],"#dddace")
        poly([(331,200),(586,166),(624,492),(367,536)],cover)
        line([(348,203),(387,530)],"#9aabae",4)
        if key=="clean-code": text(376,237,"CLEAN",38,"#f7f1db",True);text(382,289,"CODE",38,"#f7f1db",True);text(397,387,"PROGRAMMING",13,"#d4e3e7")
        else: text(371,228,"GIÁO TRÌNH",20,"#f6f1d9",True);text(379,283,"CƠ SỞ",32,"#f6f1d9",True);text(383,331,"DỮ LIỆU",29,"#f6f1d9",True)
        for y in range(504,540,6):line([(381,y),(625,y-39)],"#b7b6a6",1)
    if view>1:obj=obj.rotate(5,resample=Image.Resampling.BICUBIC,center=(480*S,350*S))
    canvas=Image.alpha_composite(canvas,obj)
    final=ImageDraw.Draw(canvas)
    final.text((48*S,38*S),"SECONDHAND / DEMO",font=font(15,True),fill="#667780")
    final.text((48*S,667*S),"ẢNH MINH HỌA · KHÔNG PHẢI ẢNH CHỤP SẢN PHẨM THẬT",font=font(12),fill="#6f7c84")
    if view>1:final.text((810*S,667*S),"GÓC 2",font=font(12,True),fill="#6f7c84")
    return canvas.convert("RGB").resize((960,720),Image.Resampling.LANCZOS)

if __name__=="__main__":
    output=ROOT/"images";output.mkdir(exist_ok=True)
    catalog=json.loads((ROOT/"catalog.json").read_text(encoding="utf-8"))
    for entry in catalog:
        for view in range(1,entry["images"]+1):
            render(entry,view).save(output/f"{entry['key']}-{view}.webp",format="WEBP",quality=85,method=6)
    paths=list(output.glob("*.webp"))
    print(f"Rendered {len(paths)} original images, {sum(p.stat().st_size for p in paths):,} bytes total.")
