#!/usr/bin/env python3
"""Compone el banner 2x6 de marcas para el PDF (uso local)."""

from __future__ import annotations

import io
from pathlib import Path

import cairosvg
from PIL import Image, ImageDraw, ImageFont, ImageOps

ROOT = Path(__file__).resolve().parents[1]
RAW = Path("/tmp/marcas/raw")
OUT_DIR = ROOT / "assets" / "img" / "marcas"
NAVY = (11, 60, 93)
GREY = (180, 180, 180)


def knockout_dark(im: Image.Image, thresh: int = 22) -> Image.Image:
    im = im.convert("RGBA")
    px = im.load()
    w, h = im.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if a == 0:
                continue
            if r <= thresh and g <= thresh and b <= thresh:
                px[x, y] = (255, 255, 255, 0)
            elif r >= 245 and g >= 245 and b >= 245:
                # texto blanco que quedaría invisible sobre fondo blanco
                px[x, y] = (40, 40, 40, a)
    return im


def fit_cell(im: Image.Image, box: tuple[int, int]) -> Image.Image:
    im = im.convert("RGBA")
    im.thumbnail(box, Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", box, (255, 255, 255, 255))
    x = (box[0] - im.size[0]) // 2
    y = (box[1] - im.size[1]) // 2
    canvas.alpha_composite(im, (x, y))
    return canvas


def svg_png(path: Path, width: int = 520) -> Image.Image:
    data = cairosvg.svg2png(url=str(path), output_width=width)
    return Image.open(io.BytesIO(data)).convert("RGBA")


def font(size: int, bold: bool = True) -> ImageFont.FreeTypeFont:
    name = "DejaVuSans-Bold.ttf" if bold else "DejaVuSans.ttf"
    return ImageFont.truetype("/usr/share/fonts/truetype/dejavu/" + name, size)


def make_flexlink(size: tuple[int, int]) -> Image.Image:
    im = Image.new("RGBA", size, (255, 255, 255, 255))
    d = ImageDraw.Draw(im)
    m = 8
    d.rounded_rectangle((m, size[1] // 2 - 28, size[0] - m - 10, size[1] // 2 + 28), 6, fill=(227, 6, 19))
    d.polygon(
        [
            (size[0] - m - 10, size[1] // 2 - 10),
            (size[0] - m + 4, size[1] // 2),
            (size[0] - m - 10, size[1] // 2 + 10),
        ],
        fill=(227, 6, 19),
    )
    f = font(22)
    text = "FLEXLINK"
    bbox = d.textbbox((0, 0), text, font=f)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    d.text(((size[0] - tw) / 2 - 4, (size[1] - th) / 2 - 4), text, font=f, fill=(255, 255, 255))
    return im


def make_cmc(size: tuple[int, int]) -> Image.Image:
    im = Image.new("RGBA", size, (255, 255, 255, 255))
    d = ImageDraw.Draw(im)
    box = (18, 10, size[0] - 18, size[1] - 28)
    d.rectangle(box, fill=(230, 120, 20))
    d.rectangle((box[0] + 8, box[1] + 8, box[2] - 8, box[1] + 12), fill=(255, 255, 255))
    d.rectangle((box[0] + 8, box[3] - 12, box[2] - 8, box[3] - 8), fill=(255, 255, 255))
    f = font(26)
    text = "CMC"
    bbox = d.textbbox((0, 0), text, font=f)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    d.text(((size[0] - tw) / 2, (box[1] + box[3] - th) / 2 - 4), text, font=f, fill=(255, 255, 255))
    f2 = font(11, bold=False)
    sub = "cmc Klebetechnik"
    bbox = d.textbbox((0, 0), sub, font=f2)
    tw = bbox[2] - bbox[0]
    d.text(((size[0] - tw) / 2, size[1] - 22), sub, font=f2, fill=(40, 40, 40))
    return im


def load_logo(name: str, size: tuple[int, int]) -> Image.Image:
    if name == "flexlink":
        return make_flexlink(size)
    if name == "cmc":
        return make_cmc(size)

    path_map = {
        "sonic": RAW / "sonic.png",
        "lc": RAW / "lc.png",
        "movex": RAW / "movex.png",
        "eltra": RAW / "eltra.svg",
        "elektror": RAW / "elektror.svg",
        "haida": RAW / "haida.gif",
        "intralox": RAW / "intralox.svg",
        "columbia": RAW / "columbia.png",
        "combi": RAW / "combi.png",
        "oriental": RAW / "oriental.jpg",
    }
    path = path_map[name]
    if path.suffix.lower() == ".svg":
        im = svg_png(path, 640)
    else:
        im = Image.open(path).convert("RGBA")
    if name in ("sonic", "eltra", "columbia"):
        im = knockout_dark(im, 22)
    bg = Image.new("RGBA", im.size, (255, 255, 255, 255))
    bg.alpha_composite(im)
    return fit_cell(bg, size)


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    cols, rows = 6, 2
    cell_w, cell_h = 190, 78
    pad = 8
    bar = 6
    width = cols * cell_w + (cols + 1) * pad
    height = bar + rows * cell_h + (rows + 1) * pad + bar + 18
    banner = Image.new("RGB", (width, height), (255, 255, 255))
    d = ImageDraw.Draw(banner)
    d.rectangle((0, 0, width, bar), fill=NAVY)
    d.rectangle((0, height - 18 - bar, width, height - 18), fill=NAVY)
    d.rectangle((0, height - 18, width, height), fill=GREY)
    d.rectangle((0, 0, width - 1, height - 1), outline=(30, 30, 30), width=2)

    names = [
        "sonic",
        "lc",
        "movex",
        "eltra",
        "flexlink",
        "elektror",
        "haida",
        "intralox",
        "columbia",
        "combi",
        "cmc",
        "oriental",
    ]
    for i, name in enumerate(names):
        r, c = divmod(i, cols)
        x = pad + c * (cell_w + pad)
        y = bar + pad + r * (cell_h + pad)
        cell = load_logo(name, (cell_w - 4, cell_h - 4))
        banner.paste(cell.convert("RGB"), (x + 2, y + 2))
        cell.save(OUT_DIR / (name + ".png"), format="PNG", optimize=True)

    out = OUT_DIR / "banner_marcas.png"
    banner.save(out, optimize=True)
    print("wrote", out, banner.size, out.stat().st_size)

    # company logo with white background
    src = Path("/tmp/marcas/LogoPaez-Ver1.png")
    if src.is_file():
        logo = knockout_dark(Image.open(src).convert("RGBA"), 12)
        bg = Image.new("RGBA", logo.size, (255, 255, 255, 255))
        bg.alpha_composite(logo)
        dest = ROOT / "assets" / "img" / "logo.png"
        bg.save(dest, format="PNG", optimize=True)
        print("wrote", dest, dest.stat().st_size)


if __name__ == "__main__":
    main()
