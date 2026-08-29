<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $method = \Crm\Http::method();
    if ($method === 'GET') {
        $user = \Crm\Auth::user();
        if ($user === null) {
            \Crm\Http::fail('No autenticado', 401);
        }
        return array('user' => $user);
    }
    if ($method === 'POST') {
        $body = \Crm\Http::body();
        $action = isset($body['action']) ? (string) $body['action'] : 'login';
        if ($action === 'logout') {
            \Crm\Auth::logout();
            return array('logged_out' => true);
        }
        $user = \Crm\Auth::login(
            isset($body['email']) ? (string) $body['email'] : '',
            isset($body['password']) ? (string) $body['password'] : ''
        );
        return array('user' => $user);
    }
    if ($method === 'DELETE') {
        \Crm\Auth::logout();
        return array('logged_out' => true);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
