<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function (): array {
    $method = \Crm\Http::method();
    $action = strtolower((string) ($_GET['action'] ?? ''));
    $body = \Crm\Http::body();
    if ($action === '' && isset($body['action'])) {
        $action = strtolower((string) $body['action']);
    }

    if ($method === 'POST' && ($action === 'login' || $action === 'entrar')) {
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? $body['clave'] ?? '');
        $user = \Crm\Comex\Usuarios::autenticar($email, $password);
        return ['usuario' => $user];
    }

    if ($method === 'POST' && ($action === 'logout' || $action === 'salir')) {
        \Crm\Comex\Usuarios::cerrarSesion();
        return ['cerrado' => true];
    }

    if ($action === 'me' || $action === 'sesion') {
        $user = \Crm\Comex\Usuarios::sesion();
        return ['usuario' => $user, 'autenticado' => $user !== null];
    }

    return [
        'roles' => \Crm\Comex\Usuarios::roles(),
        'usuarios' => \Crm\Comex\Usuarios::listar(),
        'sesion' => \Crm\Comex\Usuarios::sesion(),
    ];
});
