# Diseño: Importar facturas PDF (offline / revisión)

**Estado:** prototipo UI — no toca producción ni guarda movimientos.  
**Ruta local:** `/documentos/importar-pdf`  
**Rama:** `cursor/pdf-import-design-fef4`

## Objetivo
Cargar facturas de **compra** y **venta** desde PDF sin digitar línea por línea, con revisión obligatoria antes de impactar stock/CUP.

## Flujo propuesto
1. **Subir PDF** y elegir tipo: Compra → Entrada / Venta → Salida.
2. **Revisar** N° documento, fecha, productos, cantidades y precios.
3. **Aprobar** → solo entonces se registrarían movimientos (en la versión real).

## Reglas de negocio
| Tipo | Movimiento | Precio | Stock | CUP |
|---|---|---|---|---|
| Compra | ENTRADA | Precio de compra | Sube | Se recalcula |
| Venta | SALIDA | Precio de venta (informativo) | Baja | No cambia |

- Líneas sin producto asignado no se pueden aprobar.
- En ventas, stock insuficiente bloquea la aprobación.
- OCR nunca escribe directo: siempre hay pantalla de revisión.

## Qué incluye este prototipo
- UI de 3 pasos con datos mock (OCR simulado).
- Matching de catálogo (OK / Revisar / Sin match).
- Vista de impacto stock/CUP por línea.
- Botón “Aprobar” solo demo (no API, no DB).

## Qué NO hace (aún)
- OCR real sobre el PDF.
- Guardar documentos en la base.
- Despliegue a `inventario.lpaezsis.cl`.

## Criterios para aprobar el diseño
- [ ] ¿El flujo compra/venta se entiende?
- [ ] ¿La tabla de revisión es usable?
- [ ] ¿Está claro que nada se guarda hasta aprobar?
- [ ] ¿Falta algo (p. ej. adjuntar PDF al movimiento, multi-PDF)?

Cuando apruebes, se implementa OCR + persistencia en una rama nueva y **solo se sube a producción con tu autorización**.
