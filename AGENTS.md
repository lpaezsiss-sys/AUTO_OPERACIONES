# Agente — CRM LPAEZsis

- Producción (`crm.lpaezsis.cl`): **PHP 8.1** vía CloudLinux PHP Selector (`selectorctl -i php -b 8.1`). MultiPHP no está habilitado en la cuenta. El código sigue siendo **sintaxis PHP 7.4-safe**. Prohibido: `match()`, named arguments, `?->`, union types, constructor property promotion, `mixed`, `never`, `str_starts_with` nativo (usar polyfill). No usar `AddHandler` alt-php81 si el selector de la cuenta no es 8.1: CageFS no monta PDO y el sitio cae.
- PDO MySQL con prepared statements. Tablas CRM: prefijo `crm_`.
- Inventario: solo `SELECT` sobre `productos`. No duplicar lógica de stock.
- API en `/api/` con header JSON. Configuración en `/config/db.php`.
- Transacciones + `rollBack()` en escrituras multi-tabla.
- Manual de usuario (`MANUAL_USUARIO.md`, `manual.php`, `api/manual_pdf.php`, `docs/*.pdf`): **no desplegar a producción** hasta autorización explícita.
