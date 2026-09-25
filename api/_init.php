<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$crmApiScript = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$crmApiAction = strtolower((string) ($_GET['action'] ?? ''));
$crmApiPublic = $crmApiScript === 'health.php'
    || ($crmApiScript === 'usuarios.php' && in_array($crmApiAction, ['login', 'entrar', 'logout', 'salir', 'me', 'sesion'], true));
if (!$crmApiPublic) {
    \Auth::requireApi();
}
