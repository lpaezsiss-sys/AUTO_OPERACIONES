<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$title = 'Manual de Usuario';
$page = 'manual';
$user = [];
require __DIR__ . '/includes/layout_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Manual de operación</h1>
        <p class="text-secondary mb-0">Guía de usuario COMEX LPAEZsis · inventario.lpaezsis.cl · IVA aduanero 19%</p>
    </div>
    <a class="btn btn-yellow" href="operaciones.php">Ir al pipeline</a>
</div>

<div class="row g-4">
    <div class="col-lg-3">
        <nav class="manual-toc card card-soft p-3 sticky-top" aria-label="Secciones del manual">
            <div class="small text-uppercase text-secondary mb-2">Contenido</div>
            <a class="manual-toc-link" href="#modulo-catalogo">Catálogo y Productos</a>
            <a class="manual-toc-link" href="#modulo-pipeline">Pipeline de Operaciones</a>
            <a class="manual-toc-link" href="#modulo-landed">Calculadora Landed Cost</a>
            <a class="manual-toc-link" href="#modulo-documentos">Repositorio Documental</a>
        </nav>
    </div>
    <div class="col-lg-9">
        <section id="modulo-catalogo" class="manual-section card card-soft p-4 mb-4">
            <h2 class="h4" style="color:#05294B">Catálogo y Productos</h2>
            <p class="text-secondary">Las fichas COMEX no duplican el maestro de inventario: se vinculan por <strong>SKU</strong> a <code>prod.db</code> (Prisma <code>Product.code</code> en inventario.lpaezsis.cl).</p>
            <ul>
                <li>Ruta: <a href="fichas.php">Fichas</a>. El botón <strong>Sincronizar Inventario</strong> llama a <code>api/sync.php</code> y trae los SKU desde <code>prod.db</code>. La sincronización lee stock y costo promedio vivos del SQLite compartido (<code>INV_SQLITE_PATH</code>).</li>
                <li>Al <strong>confirmar</strong> una importación se escribe un movimiento <code>ENTRADA</code>; una exportación escribe <code>SALIDA</code>. La escritura usa WAL, <code>busy_timeout</code> y <code>BEGIN IMMEDIATE</code>.</li>
                <li>El costo unitario promedio (CUP/PMP) se actualiza solo vía <code>StockSync</code>. El landed cost no escribe <code>prod.db</code>.</li>
                <li>Si el SKU no existe o no hay stock suficiente en una exportación, la confirmación falla (409).</li>
            </ul>
            <p class="mb-0 small text-secondary">Imágenes de ficha e ítem se guardan en <code>uploads/comex/productos/</code> e <code>uploads/comex/items/</code> (755/775, sin PHP ejecutable).</p>
        </section>

        <section id="modulo-pipeline" class="manual-section card card-soft p-4 mb-4">
            <h2 class="h4" style="color:#05294B">Pipeline de Operaciones</h2>
            <p class="text-secondary">Al crear una Importación o Exportación se siembran <strong>13 etapas</strong>: 5 de Evaluación y 8 de Ejecución. Vista Kanban y lista en <a href="operaciones.php">Pipeline</a>.</p>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <h3 class="h6" style="color:#05294B">Evaluación</h3>
                    <ol class="mb-0">
                        <li>Solicitud</li>
                        <li>Cotización</li>
                        <li>Análisis de viabilidad</li>
                        <li>Evaluación financiera</li>
                        <li>Aprobación</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h3 class="h6" style="color:#05294B">Ejecución</h3>
                    <ol class="mb-0" start="6">
                        <li>Orden de compra (PO)</li>
                        <li>Pago</li>
                        <li>Embarque</li>
                        <li>Tracking</li>
                        <li>Documentos de transporte</li>
                        <li>Despacho aduanero <strong>DIN</strong> (importación) o <strong>DUS</strong> (exportación)</li>
                        <li>Entrega</li>
                        <li>Cierre operativo</li>
                    </ol>
                </div>
            </div>
            <p>Estados: <code>PENDING</code>, <code>IN_PROGRESS</code>, <code>COMPLETED</code>, <code>BLOCKED</code>. Solicitud inicia en curso; completar una etapa abre la siguiente.</p>
            <ul class="mb-0">
                <li><strong>Atraso:</strong> etapa no completada con fecha estimada anterior a hoy (tarjeta roja / badge Atrasada).</li>
                <li><strong>Bloqueo:</strong> estado <code>BLOCKED</code> (amarillo). Use la bitácora para dejar comentario, usuario y fecha.</li>
            </ul>
        </section>

        <section id="modulo-landed" class="manual-section card card-soft p-4 mb-4">
            <h2 class="h4" style="color:#05294B">Calculadora Landed Cost</h2>
            <p class="text-secondary">Hoja en el detalle de la operación: <code>operacion.php?id=&amp;tab=financials</code>. Recálculo en vivo, sin Composer.</p>
            <ul>
                <li><strong>Prorrateo por FOB:</strong> cada SKU recibe un factor igual a su FOB sobre el FOB total. Gastos de origen (flete internacional, seguro) entran al CIF; gastos locales en CLP (Aduana, Agencia, Flete interno, Bancarios) se prorratean y no van en el IVA.</li>
                <li><strong>IVA 19% CIF Chile:</strong> IVA aduanero = 19% sobre CIF (FOB + flete intl + seguro), configurable con <code>IVA_PCT</code>.</li>
                <li><strong>Estimada vs Real:</strong> dos versiones por operación. La matriz muestra delta en CLP y USD (unitario = CLP / tipo de cambio USD).</li>
                <li><strong>Exportación:</strong> Excel (.xlsx, ZipArchive OOXML) y PDF de la matriz Estimación vs Real.</li>
            </ul>
            <p class="mb-0 small text-secondary">Ejemplo de control: FOB 300 USD, TC 900 → FOB CLP 270.000, CIF 302.400, IVA 57.456, locales 33.000, landed 392.856.</p>
        </section>

        <section id="modulo-documentos" class="manual-section card card-soft p-4 mb-4">
            <h2 class="h4" style="color:#05294B">Repositorio Documental</h2>
            <p class="text-secondary">En <code>operacion.php?id=&amp;tab=documents</code> se suben y previsualizan los documentos de embarque. Cada archivo registra <strong>usuario</strong> y <strong>fecha</strong>.</p>
            <ul>
                <li>Tipos: Factura Comercial, Packing List, BL/AWB, Certificados y DIN/DUS (la etiqueta DIN o DUS sigue el tipo de operación).</li>
                <li>Formatos: PDF, JPG, PNG, WEBP · máximo 8 MB. Se rechazan PHP y ejecutables.</li>
                <li>Almacenamiento: <code>uploads/comex/docs/{operacion}/</code>, permisos 644, directorios 755/775, sin PHP ejecutable (WebDAV 2078 excluido).</li>
                <li>La previsualización usa iframe para PDF e <code>img</code> para imágenes, con enlace de descarga.</li>
            </ul>
            <p class="mb-0">Desde cualquier módulo, el botón <span class="btn btn-outline-secondary btn-sm disabled">Ayuda</span> abre la sección correspondiente de este manual.</p>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
