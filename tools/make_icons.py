#!/usr/bin/env python3
"""Generate PWA PNG icons (192, 512, maskable 512, apple-touch 180) using Pillow.
Run: python3 tools/make_icons.py
"""
from pathlib import Path
from PIL import Image, ImageDraw

OUT = Path(__file__).resolve().parent.parent / "public" / "icons"
OUT.mkdir(parents=True, exist_ok=True)
S = 2048  # supersampling resolution


def gradient(size):
    img = Image.new("RGB", (size, size))
    px = img.load()
    a, b = (59, 130, 246), (79, 70, 229)
    for y in range(size):
        for x in range(size):
            t = (x + y) / (2 * (size - 1))
            px[x, y] = tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))
    return img


def glyph(d, scale, ox, oy, w):
    """Calendar + checkmark glyph. Coordinates defined in a 512x512 canvas and scaled."""
    def P(x, y):
        return (ox + x * scale, oy + y * scale)

    def line(pts):
        d.line([P(*p) for p in pts], fill="white", width=int(w * scale), joint="curve")
        for p in (pts[0], pts[-1]):
            cx, cy = P(*p)
            r = w * scale / 2
            d.ellipse([cx - r, cy - r, cx + r, cy + r], fill="white")

    d.rounded_rectangle([*P(112, 136), *P(400, 400)], radius=48 * scale, outline="white", width=int(w * scale))
    line([(184, 104), (184, 166)])
    line([(328, 104), (328, 166)])
    line([(112, 224), (400, 224)])
    line([(196, 316), (240, 360), (320, 272)])


def make(size, maskable=False, rounded=True, glyph_scale=1.0):
    base = gradient(S)
    d = ImageDraw.Draw(base)
    sc = (S / 512) * glyph_scale
    off = (S - 512 * sc) / 2
    glyph(d, sc, off, off, 30)
    if rounded and not maskable:
        mask = Image.new("L", (S, S), 0)
        ImageDraw.Draw(mask).rounded_rectangle([0, 0, S, S], radius=int(S * 0.2266), fill=255)
        out = Image.new("RGBA", (S, S), (0, 0, 0, 0))
        out.paste(base, (0, 0), mask)
    else:
        out = base.convert("RGBA")
    return out.resize((size, size), Image.LANCZOS)


make(192).save(OUT / "icon-192.png")
make(512).save(OUT / "icon-512.png")
make(512, maskable=True, glyph_scale=0.68).save(OUT / "icon-maskable-512.png")   # 80% safe zone
make(180, rounded=False).save(OUT / "apple-touch-icon.png")                       # iOS applies its own mask
print("OK:", *sorted(p.name for p in OUT.glob("*.png")))
