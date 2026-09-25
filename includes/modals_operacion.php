<div class="modal fade" id="modalEditarOp" tabindex="-1" aria-labelledby="modalEditarOpTitulo">
    <div class="modal-dialog">
        <form class="modal-content" id="formEditarOp">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalEditarOpTitulo">Editar operación</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editOpId" value="">
                <div class="mb-2">
                    <label class="form-label" for="editOpNombre">Nombre de la operación</label>
                    <input id="editOpNombre" class="form-control" maxlength="255" placeholder="Ej. Importación bandas Q3">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="editOpProveedor">Proveedor</label>
                    <input id="editOpProveedor" class="form-control" maxlength="160" placeholder="Proveedor / shipper">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="editOpReferencia">Referencia / DIN / DUS</label>
                    <input id="editOpReferencia" class="form-control" maxlength="255" placeholder="DIN, DUS o referencia interna">
                </div>
                <div>
                    <label class="form-label" for="editOpMoneda">Moneda base</label>
                    <select id="editOpMoneda" class="form-select">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="CLP">CLP</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-yellow" type="submit">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEliminarOp" tabindex="-1" aria-labelledby="modalEliminarOpTitulo">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalEliminarOpTitulo">Eliminar operación</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="delOpId" value="">
                <p class="mb-2">Se eliminará <strong id="delOpFolio">esta operación</strong> junto con:</p>
                <ul class="small mb-3">
                    <li>Ítems de la operación (catálogo y evaluación)</li>
                    <li>Datos financieros y matrices de landed cost</li>
                    <li>Documentos adjuntos (factura, packing, BL/AWB, certificados, DIN/DUS)</li>
                    <li>Etapas del pipeline y bitácora</li>
                </ul>
                <div id="delOpStockBox" class="d-none">
                    <div class="alert alert-danger py-2 small" role="alert">
                        Esta operación ya generó movimientos de stock en <code>prod.db</code> (Entrega/Cierre). El borrado directo está bloqueado (HTTP 409) salvo revertir los movimientos o confirmar con perfil administrador.
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="delOpAdmin">
                        <label class="form-check-label" for="delOpAdmin">Soy administrador y confirmo el borrado sin revertir stock</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-outline-secondary d-none" type="button" id="btnDelRevertir">Revertir stock y eliminar</button>
                <button class="btn btn-danger" type="button" id="btnDelConfirmar">Eliminar</button>
            </div>
        </div>
    </div>
</div>
