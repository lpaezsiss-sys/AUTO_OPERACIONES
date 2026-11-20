#!/usr/bin/env python3
"""Deck 16:9 — propuesta CRM LPAEZsis para inversionistas (10 slides)."""

from __future__ import annotations

import os

from PIL import Image, ImageDraw, ImageFont
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.oxml.ns import qn
from pptx.util import Inches, Pt

NAVY = RGBColor(0x05, 0x29, 0x4B)
NAVY2 = RGBColor(0x0A, 0x3A, 0x66)
YELLOW = RGBColor(0xFE, 0xC0, 0x01)
INK = RGBColor(0x0E, 0x1A, 0x24)
MUTED = RGBColor(0x5B, 0x6B, 0x7A)
BG = RGBColor(0xF4, 0xF6, 0xF8)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
CARD = RGBColor(0xFF, 0xFF, 0xFF)
LINE = RGBColor(0xD5, 0xDE, 0xE6)

NAVY_HEX = (5, 41, 75)
NAVY2_HEX = (10, 58, 102)
YELLOW_HEX = (254, 192, 1)
INK_HEX = (14, 26, 36)
MUTED_HEX = (91, 107, 122)
BG_HEX = (244, 246, 248)
WHITE_HEX = (255, 255, 255)
LINE_HEX = (213, 222, 230)

FONT = "Liberation Sans"
FONT_R = "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf"
FONT_B = "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf"

W, H = Inches(13.333), Inches(7.5)
SLIDE_W, SLIDE_H = 1920, 1080


def rgb(shape, color):
    shape.fill.solid()
    shape.fill.fore_color.rgb = color
    shape.line.fill.background()


def line_rgb(shape, color, pt=1.0):
    shape.line.color.rgb = color
    shape.line.width = Pt(pt)


def rect(slide, x, y, w, h, color, round=False):
    kind = MSO_SHAPE.ROUNDED_RECTANGLE if round else MSO_SHAPE.RECTANGLE
    sh = slide.shapes.add_shape(kind, x, y, w, h)
    rgb(sh, color)
    if round:
        try:
            sh.adjustments[0] = 0.08
        except Exception:
            pass
    sh.shadow.inherit = False
    return sh


def box(slide, x, y, w, h, text, size=14, bold=False, color=INK, align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.TOP, font=FONT):
    tb = slide.shapes.add_textbox(x, y, w, h)
    tf = tb.text_frame
    tf.word_wrap = True
    tf.auto_size = None
    try:
        tf._txBody.bodyPr.set("anchor", {MSO_ANCHOR.TOP: "t", MSO_ANCHOR.MIDDLE: "ctr", MSO_ANCHOR.BOTTOM: "b"}[anchor])
    except Exception:
        pass
    p = tf.paragraphs[0]
    p.alignment = align
    run = p.add_run()
    run.text = text
    run.font.name = font
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    # latin typeface
    rPr = run._r.get_or_add_rPr()
    for tag in ("a:latin", "a:cs", "a:ea"):
        el = rPr.find(qn(tag))
        if el is None:
            el = rPr.makeelement(qn(tag), {})
            rPr.append(el)
        el.set("typeface", font)
    return tb


def footer(slide, n, dark=False):
    c = RGBColor(0x9A, 0xA8, 0xB5) if not dark else RGBColor(0x8A, 0xA0, 0xB4)
    box(slide, Inches(0.55), Inches(7.12), Inches(10.2), Inches(0.28),
        "LPAEZsis  ·  CRM industrial  ·  Confidencial  ·  crm.lpaezsis.cl", 10, False, c)
    box(slide, Inches(11.6), Inches(7.12), Inches(1.1), Inches(0.28),
        str(n).zfill(2), 10, True, YELLOW if dark else NAVY, PP_ALIGN.RIGHT)


def accent_bar(slide, y=Inches(0.42)):
    rect(slide, Inches(0.55), y, Inches(0.14), Inches(0.42), YELLOW)


def title(slide, text, y=Inches(0.38)):
    accent_bar(slide, y)
    box(slide, Inches(0.85), y - Inches(0.04), Inches(11.8), Inches(0.55), text, 26, True, NAVY)


def kicker(slide, text):
    box(slide, Inches(0.55), Inches(0.18), Inches(12), Inches(0.24), text.upper(), 10, True, MUTED)


def card(slide, x, y, w, h):
    sh = rect(slide, x, y, w, h, WHITE, round=True)
    line_rgb(sh, LINE, 1)
    return sh


def add_logo(slide, path, x, y, size):
    if os.path.isfile(path):
        slide.shapes.add_picture(path, x, y, size, size)


def make_logo(path):
    img = Image.new("RGBA", (256, 256), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    d.rounded_rectangle((0, 0, 255, 255), radius=48, fill=NAVY_HEX)
    d.rounded_rectangle((150, 80, 204, 176), radius=8, fill=YELLOW_HEX)
    try:
        f = ImageFont.truetype(FONT_B, 118)
    except Exception:
        f = ImageFont.load_default()
    d.text((28, 52), "L", font=f, fill=WHITE_HEX)
    img.save(path)


def font(size, bold=False):
    path = FONT_B if bold else FONT_R
    try:
        return ImageFont.truetype(path, size)
    except Exception:
        return ImageFont.load_default()


def draw_round(d, xy, r, fill, outline=None, width=1):
    d.rounded_rectangle(xy, radius=r, fill=fill, outline=outline, width=width)


def png_base(dark=False):
    img = Image.new("RGB", (SLIDE_W, SLIDE_H), NAVY_HEX if dark else BG_HEX)
    d = ImageDraw.Draw(img)
    if not dark:
        d.rectangle((0, 0, SLIDE_W, 8), fill=YELLOW_HEX)
        d.rectangle((0, SLIDE_H - 8, SLIDE_W, SLIDE_H), fill=NAVY_HEX)
    return img, d


def png_footer(d, n, dark=False):
    c = (138, 160, 180) if dark else (154, 168, 181)
    d.text((80, 1022), "LPAEZsis  ·  CRM industrial  ·  Confidencial  ·  crm.lpaezsis.cl",
           font=font(18), fill=c)
    label = str(n).zfill(2)
    d.text((1780, 1020), label, font=font(20, True), fill=YELLOW_HEX if dark else NAVY_HEX)


def png_title(d, text, y=78):
    d.rectangle((80, y + 8, 98, y + 52), fill=YELLOW_HEX)
    d.text((118, y), text, font=font(40, True), fill=NAVY_HEX)


def png_kicker(d, text):
    d.text((80, 32), text.upper(), font=font(16, True), fill=MUTED_HEX)


def png_card(d, xy):
    draw_round(d, xy, 18, WHITE_HEX, LINE_HEX, 2)


def wrap(text, fnt, max_w, d):
    words = text.split()
    lines, cur = [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        if d.textlength(trial, font=fnt) <= max_w:
            cur = trial
        else:
            if cur:
                lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def build_pptx(out, logo):
    prs = Presentation()
    prs.slide_width = W
    prs.slide_height = H
    blank = prs.slide_layouts[6]

    # 1 cover
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, NAVY)
    rect(s, 0, 0, Inches(0.18), H, YELLOW)
    add_logo(s, logo, Inches(0.55), Inches(0.42), Inches(0.62))
    box(s, Inches(1.3), Inches(0.52), Inches(6), Inches(0.4), "LPAEZSIS", 16, True, YELLOW)
    box(s, Inches(0.55), Inches(1.7), Inches(12), Inches(0.4),
        "PROPUESTA DE PRODUCTO  ·  10 SLIDES", 13, True, YELLOW)
    box(s, Inches(0.55), Inches(2.15), Inches(12.2), Inches(1.4),
        "El sistema operativo\nde la venta industrial", 40, True, WHITE)
    box(s, Inches(0.55), Inches(4.55), Inches(11.5), Inches(0.9),
        "Cómo el CRM LPAEZsis pasa de cotizar SKU a gestionar la cuenta de planta,\nincorporar RRSS, controlar publicaciones y trabajar con un bot que califica — no que vende.",
        16, False, RGBColor(0xD6, 0xE0, 0xEA))
    box(s, Inches(0.55), Inches(5.85), Inches(10), Inches(0.35),
        "Clientes  ·  RRSS  ·  Publicaciones  ·  Bot          Noviembre 2026          crm.lpaezsis.cl",
        13, False, YELLOW)
    footer(s, 1, dark=True)

    # 2 problem
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "El problema")
    title(s, "El cierre industrial no pasa por un like")
    box(s, Inches(0.55), Inches(1.15), Inches(12.2), Inches(0.5),
        "LPAEZsis vende a jefes de mantención, packing y planta. El canal que cierra es WhatsApp + visita + cotización.",
        15, False, MUTED)
    problems = [
        ("01", "La relación vive afuera", "El vendedor opera entre WhatsApp, feria y memoria. El lead no nace en el CRM."),
        ("02", "MKT y comercial no se hablan", "Se publica un SKU o un plazo que el cotizador no puede honrar."),
        ("03", "El bot genérico rompe la venta", "Inventa precio, stock o IVA. En industrial eso quema la cuenta."),
        ("04", "No hay atribución", "No se sabe qué anuncio, feria o post abrió la cotización ganada."),
    ]
    for i, (n, t, b) in enumerate(problems):
        col, row = i % 2, i // 2
        x = Inches(0.55 + col * 6.35)
        y = Inches(1.8 + row * 2.35)
        card(s, x, y, Inches(6.1), Inches(2.15))
        box(s, x + Inches(0.28), y + Inches(0.28), Inches(1.2), Inches(0.35), n, 16, True, YELLOW)
        box(s, x + Inches(0.28), y + Inches(0.7), Inches(5.5), Inches(0.4), t, 18, True, NAVY)
        box(s, x + Inches(0.28), y + Inches(1.15), Inches(5.5), Inches(0.75), b, 14, False, MUTED)
    footer(s, 2)

    # 3 already live
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "Producto en producción")
    title(s, "No es un pitch de vaporware")
    box(s, Inches(0.55), Inches(1.12), Inches(12.2), Inches(0.4),
        "crm.lpaezsis.cl ya opera el ciclo comercial. La propuesta extiende esa base; no la reemplaza.",
        15, False, MUTED)
    live = [
        ("Cuenta y venta", "Empresa → contacto → oportunidad → cotización PDF, con precio por cliente y lista."),
        ("Inventario vivo", "Stock por SKU en solo lectura. Un SKU nuevo entra al catálogo CRM con stock 0. Nunca se pisa prod.db."),
        ("Pipeline real", "Etapas, motivo de pérdida, mis oportunidades, agenda hoy/vencidas, comisiones."),
        ("Stack que escala barato", "PHP en BlueHosting, sin SPA, sin Composer obligatorio. El mismo árbol que ya está en cPanel."),
    ]
    for i, (t, b) in enumerate(live):
        y = Inches(1.65 + i * 1.2)
        card(s, Inches(0.55), y, Inches(12.2), Inches(1.08))
        rect(s, Inches(0.55), y, Inches(0.12), Inches(1.08), YELLOW)
        box(s, Inches(0.95), y + Inches(0.16), Inches(11.4), Inches(0.35), t, 16, True, NAVY)
        box(s, Inches(0.95), y + Inches(0.52), Inches(11.4), Inches(0.42), b, 14, False, MUTED)
    footer(s, 3)

    # 4 thesis
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "Tesis")
    title(s, "Tres capas. Un CRM.")
    box(s, Inches(0.55), Inches(1.12), Inches(12.2), Inches(0.45),
        "RRSS y bot alimentan el CRM. No lo reemplazan. El riesgo es construir un Meta Business Suite genérico y perder el foco: planta, SKU, precio y seguimiento.",
        15, False, MUTED)
    layers = [
        ("01  Cuenta cliente", "Quién es, qué planta, qué SKU, qué trato.\nDueño: vendedor."),
        ("02  Contenido", "Qué se publica, cuándo, con qué CTA y SKU.\nDueño: MKT, con visto bueno comercial."),
        ("03  Conversación", "Inbox + bot que abre o califica, y pasa al humano.\nDueño: bot + vendedor."),
    ]
    for i, (t, b) in enumerate(layers):
        x = Inches(0.55 + i * 4.2)
        card(s, x, Inches(1.85), Inches(4.0), Inches(3.55))
        rect(s, x, Inches(1.85), Inches(4.0), Inches(0.12), YELLOW)
        box(s, x + Inches(0.28), Inches(2.2), Inches(3.45), Inches(0.9), t, 18, True, NAVY)
        box(s, x + Inches(0.28), Inches(3.2), Inches(3.45), Inches(1.8), b, 15, False, MUTED)
    footer(s, 4)

    # 5 clients
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "Gestión de clientes")
    title(s, "De listado a cuenta 360 de planta")
    items = [
        ("Ficha que se usa", "Pestañas Resumen · Planta/SKU · Cotizaciones · Conversaciones · Contenido. Salud de cuenta: activo, silencioso, en riesgo."),
        ("El dato que cierra recambio", "Línea, marca, SKU habitual y último precio. El motor Precios ya existe; la ficha aún no lo muestra como 360."),
        ("Alta de lead en 30 s", "RUT + un contacto + canal. El resto se completa después. Origen real: feria, UTM, bot, referido."),
        ("Consentimiento", "Canal, fecha y quién autorizó (Ley 19.628). Sin eso, WhatsApp masivo quema marca y cuenta."),
    ]
    for i, (t, b) in enumerate(items):
        y = Inches(1.2 + i * 1.28)
        n = str(i + 1).zfill(2)
        card(s, Inches(0.55), y, Inches(12.2), Inches(1.16))
        box(s, Inches(0.8), y + Inches(0.32), Inches(0.7), Inches(0.45), n, 18, True, YELLOW)
        box(s, Inches(1.6), y + Inches(0.16), Inches(10.7), Inches(0.35), t, 16, True, NAVY)
        box(s, Inches(1.6), y + Inches(0.55), Inches(10.7), Inches(0.48), b, 14, False, MUTED)
    footer(s, 5)

    # 6 social
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "Redes")
    title(s, "RRSS con criterio B2B, no de moda")
    box(s, Inches(0.55), Inches(1.12), Inches(12.2), Inches(0.4),
        "No las doce redes. Tres, en este orden, porque así se vende maquinaria y recambio en Chile.",
        15, False, MUTED)
    rrss = [
        ("1", "WhatsApp Business", "Prioridad 1. Conversación, postventa y feria. El inbox vive en la ficha del cliente."),
        ("2", "LinkedIn", "Prioridad 2. Prospección a planta, gerencia y ferias. Handle en contacto, no scraping."),
        ("3", "Instagram / Facebook", "Prioridad 3. Marca, video de línea y feria. CTA siempre a WhatsApp o cotizar."),
    ]
    for i, (n, t, b) in enumerate(rrss):
        x = Inches(0.55 + i * 4.2)
        card(s, x, Inches(1.7), Inches(4.0), Inches(3.35))
        box(s, x + Inches(0.28), Inches(1.95), Inches(1.0), Inches(0.45), n, 28, True, YELLOW)
        box(s, x + Inches(0.28), Inches(2.55), Inches(3.45), Inches(0.7), t, 18, True, NAVY)
        box(s, x + Inches(0.28), Inches(3.3), Inches(3.45), Inches(1.4), b, 14, False, MUTED)
    box(s, Inches(0.55), Inches(5.25), Inches(12.2), Inches(1.4),
        "Reglas no negociables\nInbox unificado (WhatsApp primero). UTM al cotizador. Cero scraping. Cero claves personales de vendedores. YouTube/TikTok solo si hay video de producto.",
        14, False, MUTED)
    footer(s, 6)

    # 7 publications
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "Contenido")
    title(s, "MKT redacta. Comercial aprueba. El CRM registra.")
    flow = [
        ("Borrador", "Copy, media, SKU, CTA"),
        ("Revisión", "Visto bueno de precio y promesa técnica"),
        ("Programada", "Fecha/hora Chile, canal, UTM"),
        ("Publicada", "Link canónico + clics a cotizar"),
    ]
    for i, (t, b) in enumerate(flow):
        x = Inches(0.55 + i * 3.15)
        card(s, x, Inches(1.25), Inches(2.95), Inches(1.85))
        box(s, x + Inches(0.2), Inches(1.4), Inches(2.55), Inches(0.3), "0" + str(i + 1), 12, True, YELLOW)
        box(s, x + Inches(0.2), Inches(1.75), Inches(2.55), Inches(0.4), t, 16, True, NAVY)
        box(s, x + Inches(0.2), Inches(2.2), Inches(2.55), Inches(0.7), b, 13, False, MUTED)
        if i < 3:
            box(s, x + Inches(2.85), Inches(1.9), Inches(0.3), Inches(0.4), "→", 18, True, YELLOW)
    points = [
        "Objeto Publicación en el CRM — no un Excel paralelo ni Canva desconectado.",
        "Cada post lleva SKU/marca y CTA Cotizar o WhatsApp. El KPI no es el like: es el lead y la COT.",
        "Vista vendedor: “este post menciona tu cuenta / tu SKU” + abrir lead.",
        "Empezar por LinkedIn + Instagram. No programar seis redes el primer mes.",
    ]
    for i, p in enumerate(points):
        y = Inches(3.4 + i * 0.78)
        rect(s, Inches(0.55), y, Inches(0.12), Inches(0.65), YELLOW)
        box(s, Inches(0.85), y + Inches(0.08), Inches(11.8), Inches(0.55), p, 15, False, INK)
    footer(s, 7)

    # 8 bot
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "Conversación")
    title(s, "Un bot que agenda. No un bot que vende.")
    box(s, Inches(0.55), Inches(1.12), Inches(12.2), Inches(0.45),
        "El cierre industrial (stock, a-pedido, visita, IVA) lo hace el humano. El bot califica y enruta.",
        15, False, MUTED)
    steps = [
        ("1", "Empresa", "RUT o razón social. Crea o encuentra la cuenta."),
        ("2", "SKU o síntoma", "Soplado, secado, correa. Si hay código, cruza inventario en solo lectura."),
        ("3", "Planta / comuna", "Contexto de recambio, no un formulario eterno."),
        ("4", "Handoff", "Oportunidad en prospecto + actividad WhatsApp + vendedor dueño del hilo."),
    ]
    for i, (n, t, b) in enumerate(steps):
        x = Inches(0.55 + i * 3.15)
        card(s, x, Inches(1.75), Inches(3.0), Inches(2.55))
        box(s, x + Inches(0.22), Inches(1.95), Inches(0.6), Inches(0.4), n, 22, True, YELLOW)
        box(s, x + Inches(0.22), Inches(2.45), Inches(2.55), Inches(0.4), t, 16, True, NAVY)
        box(s, x + Inches(0.22), Inches(2.9), Inches(2.55), Inches(1.15), b, 13, False, MUTED)
    card(s, Inches(0.55), Inches(4.55), Inches(12.2), Inches(2.05))
    box(s, Inches(0.85), Inches(4.72), Inches(11.6), Inches(0.35), "Gobierno del bot", 16, True, NAVY)
    box(s, Inches(0.85), Inches(5.15), Inches(11.6), Inches(1.2),
        "Lista de no decir: descuento, plazo de importación, stock si el overlay falló. Fuera de horario: mensaje + ticket, no cotización fantasma.\n"
        "Arquitectura: Cloud API / BSP → webhook PHP → Empresas + Actividades. El modelo de lenguaje vive fuera de cPanel. El CRM no se reescribe en Node.\n"
        "Métricas: % handoff, % que llega a cotización, tiempo a primer humano.",
        13, False, MUTED)
    footer(s, 8)

    # 9 roadmap
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, BG)
    kicker(s, "Ejecución")
    title(s, "90 días. Capital de producto, no un social suite.")
    phases = [
        ("A", "Relación", "Sin APIs de Meta.", "Salud de cuenta, alta rápida, WhatsApp/LinkedIn como actividad real, consentimiento. Cero integraciones frágiles."),
        ("B", "WhatsApp + bot", "Acá se mueve plata.", "Cloud API, inbox en ficha, 4 preguntas, crea empresa/oportunidad, handoff al vendedor."),
        ("C", "Publicaciones", "Cuando A+B viven.", "Calendario LinkedIn + Instagram, aprobación comercial, UTM → cotizador, plantillas por marca."),
        ("D", "Atribución", "El loop cerrado.", "Comentarios/DM a la misma cola. Informe campaña → lead → COT → ganada."),
    ]
    for i, (n, t, tag, b) in enumerate(phases):
        x = Inches(0.4 + i * 3.22)
        card(s, x, Inches(1.25), Inches(3.08), Inches(5.3))
        rect(s, x, Inches(1.25), Inches(3.08), Inches(0.12), YELLOW)
        box(s, x + Inches(0.22), Inches(1.55), Inches(2.6), Inches(0.45), "FASE " + n, 12, True, YELLOW)
        box(s, x + Inches(0.22), Inches(2.05), Inches(2.6), Inches(0.7), t, 20, True, NAVY)
        box(s, x + Inches(0.22), Inches(2.75), Inches(2.6), Inches(0.4), tag, 12, True, MUTED)
        box(s, x + Inches(0.22), Inches(3.3), Inches(2.6), Inches(2.8), b, 14, False, INK)
    footer(s, 9)

    # 10 close
    s = prs.slides.add_slide(blank)
    rect(s, 0, 0, W, H, NAVY)
    rect(s, 0, 0, Inches(0.18), H, YELLOW)
    box(s, Inches(0.55), Inches(0.4), Inches(12), Inches(0.3), "LA APUESTA", 12, True, YELLOW)
    box(s, Inches(0.55), Inches(0.85), Inches(12.2), Inches(1.2),
        "En 90 días, más de la mitad\nde los leads WhatsApp/web nacen solos en el CRM.",
        28, True, WHITE)
    metrics = [
        ("Lead nativo", "Empresa + canal + vendedor, sin copiar/pegar."),
        ("Respuesta en ficha", "El vendedor ve el hilo y actúa desde la cuenta."),
        ("Post que cotiza", "Un SKU publicado genera clic a cotizar o hilo de bot — no un like."),
    ]
    for i, (t, b) in enumerate(metrics):
        x = Inches(0.55 + i * 4.2)
        sh = rect(s, x, Inches(2.4), Inches(4.0), Inches(2.15), NAVY2, round=True)
        box(s, x + Inches(0.28), Inches(2.6), Inches(3.45), Inches(0.7), t, 16, True, YELLOW)
        box(s, x + Inches(0.28), Inches(3.35), Inches(3.45), Inches(0.95), b, 14, False, WHITE)
    box(s, Inches(0.55), Inches(4.8), Inches(12.2), Inches(1.5),
        "LPAEZsis no construye un Meta Business Suite.\nConstruye el sistema operativo de la venta industrial: planta, SKU, precio y seguimiento.\n\nNo likes. No SPA. No seis redes el primer mes. Fase A + B primero.",
        15, False, RGBColor(0xD6, 0xE0, 0xEA))
    footer(s, 10, dark=True)

    os.makedirs(os.path.dirname(out), exist_ok=True)
    prs.save(out)


def build_pptx_from_pngs(out, png_paths):
    prs = Presentation()
    prs.slide_width = W
    prs.slide_height = H
    blank = prs.slide_layouts[6]
    for path in png_paths:
        s = prs.slides.add_slide(blank)
        s.shapes.add_picture(path, 0, 0, W, H)
    os.makedirs(os.path.dirname(out), exist_ok=True)
    prs.save(out)


def build_pngs(dir_out, logo_path):
    os.makedirs(dir_out, exist_ok=True)
    logo = Image.open(logo_path).convert("RGBA").resize((72, 72), Image.LANCZOS)

    def save(img, n):
        path = os.path.join(dir_out, "slide-%02d.png" % n)
        img.save(path, "PNG", optimize=True)
        return path

    paths = []

    # 1
    img, d = png_base(dark=True)
    d.rectangle((0, 0, 14, SLIDE_H), fill=YELLOW_HEX)
    img.paste(logo, (80, 58), logo)
    d.text((170, 78), "LPAEZSIS", font=font(22, True), fill=YELLOW_HEX)
    d.text((80, 240), "PROPUESTA DE PRODUCTO  ·  10 SLIDES", font=font(20, True), fill=YELLOW_HEX)
    d.text((80, 300), "El sistema operativo", font=font(64, True), fill=WHITE_HEX)
    d.text((80, 380), "de la venta industrial", font=font(64, True), fill=WHITE_HEX)
    d.text((80, 640), "Cómo el CRM LPAEZsis pasa de cotizar SKU a gestionar la cuenta de planta,", font=font(24), fill=(214, 224, 234))
    d.text((80, 680), "incorporar RRSS, controlar publicaciones y trabajar con un bot que califica — no que vende.", font=font(24), fill=(214, 224, 234))
    d.text((80, 820), "Clientes  ·  RRSS  ·  Publicaciones  ·  Bot          Noviembre 2026          crm.lpaezsis.cl",
           font=font(20), fill=YELLOW_HEX)
    png_footer(d, 1, dark=True)
    paths.append(save(img, 1))

    # 2
    img, d = png_base()
    png_kicker(d, "El problema")
    png_title(d, "El cierre industrial no pasa por un like")
    d.text((80, 160), "LPAEZsis vende a jefes de mantención, packing y planta. El canal que cierra es WhatsApp + visita + cotización.",
           font=font(22), fill=MUTED_HEX)
    probs = [
        ("01", "La relación vive afuera", "El vendedor opera entre WhatsApp, feria y memoria. El lead no nace en el CRM."),
        ("02", "MKT y comercial no se hablan", "Se publica un SKU o un plazo que el cotizador no puede honrar."),
        ("03", "El bot genérico rompe la venta", "Inventa precio, stock o IVA. En industrial eso quema la cuenta."),
        ("04", "No hay atribución", "No se sabe qué anuncio, feria o post abrió la cotización ganada."),
    ]
    for i, (n, t, b) in enumerate(probs):
        col, row = i % 2, i // 2
        x, y = 80 + col * 910, 250 + row * 350
        png_card(d, (x, y, x + 880, y + 320))
        d.text((x + 36, y + 36), n, font=font(26, True), fill=YELLOW_HEX)
        d.text((x + 36, y + 90), t, font=font(28, True), fill=NAVY_HEX)
        for j, line in enumerate(wrap(b, font(20), 800, d)):
            d.text((x + 36, y + 150 + j * 32), line, font=font(20), fill=MUTED_HEX)
    png_footer(d, 2)
    paths.append(save(img, 2))

    # 3
    img, d = png_base()
    png_kicker(d, "Producto en producción")
    png_title(d, "No es un pitch de vaporware")
    d.text((80, 155), "crm.lpaezsis.cl ya opera el ciclo comercial. La propuesta extiende esa base; no la reemplaza.",
           font=font(22), fill=MUTED_HEX)
    live = [
        ("Cuenta y venta", "Empresa → contacto → oportunidad → cotización PDF, con precio por cliente y lista."),
        ("Inventario vivo", "Stock por SKU en solo lectura. Un SKU nuevo entra al catálogo CRM con stock 0. Nunca se pisa prod.db."),
        ("Pipeline real", "Etapas, motivo de pérdida, mis oportunidades, agenda hoy/vencidas, comisiones."),
        ("Stack que escala barato", "PHP en BlueHosting, sin SPA, sin Composer obligatorio. El mismo árbol que ya está en cPanel."),
    ]
    for i, (t, b) in enumerate(live):
        y = 230 + i * 175
        png_card(d, (80, y, 1840, y + 155))
        d.rectangle((80, y, 96, y + 155), fill=YELLOW_HEX)
        d.text((130, y + 28), t, font=font(24, True), fill=NAVY_HEX)
        d.text((130, y + 78), b, font=font(20), fill=MUTED_HEX)
    png_footer(d, 3)
    paths.append(save(img, 3))

    # 4
    img, d = png_base()
    png_kicker(d, "Tesis")
    png_title(d, "Tres capas. Un CRM.")
    d.text((80, 155), "RRSS y bot alimentan el CRM. No lo reemplazan. El riesgo es un Meta Business Suite genérico y perder planta, SKU, precio y seguimiento.",
           font=font(20), fill=MUTED_HEX)
    layers = [
        ("01  Cuenta cliente", "Quién es, qué planta, qué SKU, qué trato.", "Dueño: vendedor."),
        ("02  Contenido", "Qué se publica, cuándo, con qué CTA y SKU.", "Dueño: MKT, con visto bueno comercial."),
        ("03  Conversación", "Inbox + bot que abre o califica, y pasa al humano.", "Dueño: bot + vendedor."),
    ]
    for i, (t, b, c) in enumerate(layers):
        x = 80 + i * 610
        png_card(d, (x, 250, x + 575, 780))
        d.rectangle((x, 250, x + 575, 266), fill=YELLOW_HEX)
        d.text((x + 36, 300), t, font=font(26, True), fill=NAVY_HEX)
        for j, line in enumerate(wrap(b, font(22), 500, d)):
            d.text((x + 36, 420 + j * 34), line, font=font(22), fill=MUTED_HEX)
        d.text((x + 36, 620), c, font=font(20, True), fill=INK_HEX)
    png_footer(d, 4)
    paths.append(save(img, 4))

    # 5
    img, d = png_base()
    png_kicker(d, "Gestión de clientes")
    png_title(d, "De listado a cuenta 360 de planta")
    items = [
        ("01", "Ficha que se usa", "Pestañas Resumen · Planta/SKU · Cotizaciones · Conversaciones · Contenido. Salud: activo, silencioso, en riesgo."),
        ("02", "El dato que cierra recambio", "Línea, marca, SKU habitual y último precio. El motor Precios ya existe; falta el 360."),
        ("03", "Alta de lead en 30 s", "RUT + un contacto + canal. Origen real: feria, UTM, bot, referido."),
        ("04", "Consentimiento", "Canal, fecha y quién autorizó (Ley 19.628). Sin eso, WhatsApp masivo quema marca y cuenta."),
    ]
    for i, (n, t, b) in enumerate(items):
        y = 170 + i * 185
        png_card(d, (80, y, 1840, y + 165))
        d.text((110, y + 55), n, font=font(28, True), fill=YELLOW_HEX)
        d.text((220, y + 28), t, font=font(24, True), fill=NAVY_HEX)
        d.text((220, y + 82), b, font=font(20), fill=MUTED_HEX)
    png_footer(d, 5)
    paths.append(save(img, 5))

    # 6
    img, d = png_base()
    png_kicker(d, "Redes")
    png_title(d, "RRSS con criterio B2B, no de moda")
    d.text((80, 155), "No las doce redes. Tres, en este orden, porque así se vende recambio en Chile.", font=font(22), fill=MUTED_HEX)
    rrss = [
        ("1", "WhatsApp Business", "Prioridad 1. Conversación, postventa y feria. El inbox vive en la ficha."),
        ("2", "LinkedIn", "Prioridad 2. Prospección a planta y ferias. Handle en contacto, no scraping."),
        ("3", "Instagram / Facebook", "Prioridad 3. Marca, video de línea y feria. CTA a WhatsApp o cotizar."),
    ]
    for i, (n, t, b) in enumerate(rrss):
        x = 80 + i * 610
        png_card(d, (x, 230, x + 575, 700))
        d.text((x + 36, 270), n, font=font(40, True), fill=YELLOW_HEX)
        d.text((x + 36, 350), t, font=font(26, True), fill=NAVY_HEX)
        for j, line in enumerate(wrap(b, font(20), 500, d)):
            d.text((x + 36, 430 + j * 32), line, font=font(20), fill=MUTED_HEX)
    d.text((80, 760), "Reglas no negociables", font=font(22, True), fill=NAVY_HEX)
    d.text((80, 810), "Inbox unificado (WhatsApp primero). UTM al cotizador. Cero scraping. Cero claves personales. YouTube/TikTok solo con video de producto.",
           font=font(20), fill=MUTED_HEX)
    png_footer(d, 6)
    paths.append(save(img, 6))

    # 7
    img, d = png_base()
    png_kicker(d, "Contenido")
    png_title(d, "MKT redacta. Comercial aprueba. El CRM registra.")
    flow = [
        ("01", "Borrador", "Copy, media, SKU, CTA"),
        ("02", "Revisión", "Precio y promesa técnica"),
        ("03", "Programada", "Fecha Chile, canal, UTM"),
        ("04", "Publicada", "Link + clics a cotizar"),
    ]
    for i, (n, t, b) in enumerate(flow):
        x = 80 + i * 460
        png_card(d, (x, 175, x + 420, 430))
        d.text((x + 28, 200), n, font=font(18, True), fill=YELLOW_HEX)
        d.text((x + 28, 250), t, font=font(24, True), fill=NAVY_HEX)
        d.text((x + 28, 310), b, font=font(18), fill=MUTED_HEX)
        if i < 3:
            d.text((x + 422, 280), "→", font=font(28, True), fill=YELLOW_HEX)
    points = [
        "Objeto Publicación en el CRM — no un Excel paralelo ni Canva desconectado.",
        "Cada post lleva SKU/marca y CTA Cotizar o WhatsApp. El KPI no es el like: es el lead y la COT.",
        "Vista vendedor: “este post menciona tu cuenta / tu SKU” + abrir lead.",
        "Empezar por LinkedIn + Instagram. No programar seis redes el primer mes.",
    ]
    for i, p in enumerate(points):
        y = 480 + i * 110
        d.rectangle((80, y, 96, y + 70), fill=YELLOW_HEX)
        d.text((120, y + 18), p, font=font(22), fill=INK_HEX)
    png_footer(d, 7)
    paths.append(save(img, 7))

    # 8
    img, d = png_base()
    png_kicker(d, "Conversación")
    png_title(d, "Un bot que agenda. No un bot que vende.")
    d.text((80, 155), "El cierre industrial (stock, a-pedido, visita, IVA) lo hace el humano. El bot califica y enruta.",
           font=font(22), fill=MUTED_HEX)
    steps = [
        ("1", "Empresa", "RUT o razón social. Crea o encuentra la cuenta."),
        ("2", "SKU o síntoma", "Si hay código, cruza inventario en solo lectura."),
        ("3", "Planta / comuna", "Contexto de recambio, no un formulario eterno."),
        ("4", "Handoff", "Oportunidad + WhatsApp + vendedor dueño del hilo."),
    ]
    for i, (n, t, b) in enumerate(steps):
        x = 80 + i * 460
        png_card(d, (x, 230, x + 430, 560))
        d.text((x + 28, 260), n, font=font(32, True), fill=YELLOW_HEX)
        d.text((x + 28, 330), t, font=font(22, True), fill=NAVY_HEX)
        for j, line in enumerate(wrap(b, font(18), 370, d)):
            d.text((x + 28, 390 + j * 28), line, font=font(18), fill=MUTED_HEX)
    png_card(d, (80, 590, 1840, 980))
    d.text((120, 620), "Gobierno del bot", font=font(24, True), fill=NAVY_HEX)
    gov = [
        "Lista de no decir: descuento, plazo de importación, stock si el overlay falló. Fuera de horario: mensaje + ticket.",
        "Arquitectura: Cloud API / BSP → webhook PHP → Empresas + Actividades. El LLM vive fuera de cPanel. El CRM no se reescribe en Node.",
        "Métricas: % handoff, % que llega a cotización, tiempo a primer humano.",
    ]
    for i, g in enumerate(gov):
        d.text((120, 690 + i * 50), g, font=font(18), fill=MUTED_HEX)
    png_footer(d, 8)
    paths.append(save(img, 8))

    # 9
    img, d = png_base()
    png_kicker(d, "Ejecución")
    png_title(d, "90 días. Capital de producto, no un social suite.")
    phases = [
        ("A", "Relación", "Sin APIs de Meta.",
         "Salud de cuenta, alta rápida, WhatsApp/LinkedIn como actividad real, consentimiento.",
         "Resultado: el vendedor ve cuentas en silencio y da de alta un lead en 30 s."),
        ("B", "WhatsApp + bot", "Acá se mueve plata.",
         "Cloud API, inbox en ficha, 4 preguntas, crea empresa/oportunidad, handoff al vendedor.",
         "Resultado: el lead nace solo en el CRM, con dueño y hilo."),
        ("C", "Publicaciones", "Cuando A+B viven.",
         "Calendario LinkedIn + Instagram, aprobación comercial, UTM → cotizador.",
         "Resultado: un SKU publicado abre clic a cotizar, no un like huérfano."),
        ("D", "Atribución", "El loop cerrado.",
         "Misma cola para DM. Informe campaña → lead → COT → ganada.",
         "Resultado: MKT y ventas miran el mismo número de cierre."),
    ]
    for i, (n, t, tag, b, res) in enumerate(phases):
        x = 55 + i * 465
        png_card(d, (x, 175, x + 445, 960))
        d.rectangle((x, 175, x + 445, 191), fill=YELLOW_HEX)
        d.text((x + 28, 220), "FASE " + n, font=font(16, True), fill=YELLOW_HEX)
        d.text((x + 28, 270), t, font=font(28, True), fill=NAVY_HEX)
        d.text((x + 28, 350), tag, font=font(18, True), fill=MUTED_HEX)
        yy = 420
        for line in wrap(b, font(20), 385, d):
            d.text((x + 28, yy), line, font=font(20), fill=INK_HEX)
            yy += 32
        yy += 36
        d.rectangle((x + 28, yy, x + 52, yy + 22), fill=YELLOW_HEX)
        yy += 8
        for line in wrap(res, font(18), 360, d):
            d.text((x + 64, yy), line, font=font(18), fill=NAVY_HEX)
            yy += 28
    png_footer(d, 9)
    paths.append(save(img, 9))

    # 10
    img, d = png_base(dark=True)
    d.rectangle((0, 0, 14, SLIDE_H), fill=YELLOW_HEX)
    d.text((80, 55), "LA APUESTA", font=font(18, True), fill=YELLOW_HEX)
    d.text((80, 115), "En 90 días, más de la mitad", font=font(40, True), fill=WHITE_HEX)
    d.text((80, 175), "de los leads WhatsApp/web nacen solos en el CRM.", font=font(40, True), fill=WHITE_HEX)
    metrics = [
        ("Lead nativo", "Empresa + canal + vendedor, sin copiar/pegar."),
        ("Respuesta en ficha", "El vendedor ve el hilo y actúa desde la cuenta."),
        ("Post que cotiza", "Un SKU publicado genera clic a cotizar o hilo de bot."),
    ]
    for i, (t, b) in enumerate(metrics):
        x = 80 + i * 610
        draw_round(d, (x, 330, x + 575, 620), 18, NAVY2_HEX)
        d.text((x + 36, 370), t, font=font(24, True), fill=YELLOW_HEX)
        for j, line in enumerate(wrap(b, font(20), 500, d)):
            d.text((x + 36, 450 + j * 32), line, font=font(20), fill=WHITE_HEX)
    d.text((80, 680), "LPAEZsis no construye un Meta Business Suite.", font=font(24, True), fill=WHITE_HEX)
    d.text((80, 730), "Construye el sistema operativo de la venta industrial: planta, SKU, precio y seguimiento.",
           font=font(22), fill=(214, 224, 234))
    d.text((80, 820), "No likes.  No SPA.  No seis redes el primer mes.  Fase A + B primero.",
           font=font(22, True), fill=YELLOW_HEX)
    png_footer(d, 10, dark=True)
    paths.append(save(img, 10))
    return paths


def main():
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    docs = os.path.join(root, "docs")
    artifacts = "/opt/cursor/artifacts"
    os.makedirs(docs, exist_ok=True)
    os.makedirs(artifacts, exist_ok=True)
    logo = os.path.join(artifacts, "lpaezsis-logo-ppt.png")
    make_logo(logo)
    pngs = build_pngs(os.path.join(artifacts, "ppt-slides"), logo)
    pptx = os.path.join(docs, "LPAEZsis_CRM_Propuesta_Inversionistas.pptx")
    build_pptx_from_pngs(pptx, pngs)
    dest = os.path.join(artifacts, "LPAEZsis_CRM_Propuesta_Inversionistas.pptx")
    with open(pptx, "rb") as src, open(dest, "wb") as out:
        out.write(src.read())
    print("PPTX", pptx, os.path.getsize(pptx))
    print("ART ", dest, os.path.getsize(dest))
    for p in pngs:
        print("PNG ", p, os.path.getsize(p))


if __name__ == "__main__":
    main()
