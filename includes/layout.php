<?php

declare(strict_types=1);

/**
 * Shell visual alineado al CRM LPAEZsis (navy / yellow, sidebar, Bootstrap 5).
 * Encabezado y pie viven en layout_header.php y layout_footer.php.
 *
 * @param array<string, mixed> $user
 */
function crm_layout_start(string $title, string $page, array $user = []): void
{
    if ($user === []) {
        $ses = \Crm\Comex\Usuarios::sesion();
        if (is_array($ses)) {
            $user = $ses;
        }
    }
    require __DIR__ . '/layout_header.php';
}

function crm_layout_end(): void
{
    require __DIR__ . '/layout_footer.php';
}
