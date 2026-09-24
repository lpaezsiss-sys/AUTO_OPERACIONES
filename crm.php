<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$tab = strtolower((string) ($_GET['tab'] ?? 'perfiles'));
if ($tab !== 'perfiles') {
    $tab = 'perfiles';
}

$usuarios = [];
try {
    $usuarios = \Crm\Comex\Usuarios::listar();
} catch (\Throwable) {
    $usuarios = [];
}
$sesion = \Crm\Comex\Usuarios::sesion();

crm_layout_start('CRM · Perfiles de usuario', 'crm', $sesion ?? []);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="page-title h3 mb-1">CRM</h1>
        <p class="text-secondary mb-0">Referencia de perfiles de usuario · roles <code>admin</code> y <code>comex</code></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="https://crm.lpaezsis.cl" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">CRM LPAEZsis</a>
        <a href="manual.php#modulo-perfiles" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-book" aria-hidden="true"></i> Ayuda</a>
    </div>
</div>

<div class="btn-group mb-3" role="group" aria-label="CRM">
    <a class="btn btn-navy" href="crm.php?tab=perfiles">Perfiles de usuario</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-soft p-3">
            <h2 class="h5 mb-3" style="color:#05294B">Usuarios registrados</h2>
            <div class="table-responsive">
                <table class="table table-sm table-landed align-middle mb-0" id="tablaPerfiles">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $u) : ?>
                        <tr>
                            <td><?php echo crm_h((string) $u['nombre']); ?></td>
                            <td><code><?php echo crm_h((string) $u['email']); ?></code></td>
                            <td>
                                <?php if (($u['rol'] ?? '') === 'admin') : ?>
                                <span class="badge-cat">Administrador</span>
                                <?php else : ?>
                                <span class="badge-eval">COMEX</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($u['activo']) ? 'Activo' : 'Inactivo'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($usuarios === []) : ?>
                        <tr><td colspan="4" class="text-secondary">Sin perfiles. Revise la migración de <code>usuarios</code>.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-soft p-3">
            <h2 class="h5 mb-2" style="color:#05294B">Acceso</h2>
            <p class="small text-secondary">El rol <strong>admin</strong> puede forzar el borrado de operaciones con stock. El rol <strong>comex</strong> crea, edita y consulta; no fuerza el 409.</p>
            <?php if ($sesion) : ?>
            <div class="alert alert-light border small mb-3">
                Sesión: <strong><?php echo crm_h((string) $sesion['nombre']); ?></strong><br>
                <span class="badge-cat"><?php echo crm_h((string) $sesion['rol_etiqueta']); ?></span>
                <code><?php echo crm_h((string) $sesion['email']); ?></code>
            </div>
            <button class="btn btn-outline-secondary w-100" type="button" id="btnSalir">Cerrar sesión</button>
            <?php else : ?>
            <form id="formLoginCrm">
                <div class="mb-2">
                    <label class="form-label" for="loginEmail">Email</label>
                    <input id="loginEmail" type="email" class="form-control" required placeholder="admin@comex.lpaezsis.cl">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="loginPassword">Contraseña</label>
                    <input id="loginPassword" type="password" class="form-control" required>
                </div>
                <button class="btn btn-yellow w-100" type="submit">Entrar</button>
                <p class="small text-secondary mt-2 mb-0">Pruebas: <code>admin@comex.lpaezsis.cl</code> o <code>comex@comex.lpaezsis.cl</code> · <code>Comex2026!</code></p>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="assets/js/crm-perfiles.js"></script>
<?php crm_layout_end(); ?>
