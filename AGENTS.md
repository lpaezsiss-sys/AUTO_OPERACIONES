# Agente — CRM LPAEZsis

- PHP **7.4 LTS** en BlueHosting. Prohibido: `match()`, named arguments, `?->`, union types, constructor property promotion, `mixed`, `never`, `str_starts_with` nativo (usar polyfill).
- PDO MySQL con prepared statements. Tablas CRM: prefijo `crm_`.
- Inventario: solo `SELECT` sobre `productos`. No duplicar lógica de stock.
- API en `/api/` con header JSON. Configuración en `/config/db.php`.
- Transacciones + `rollBack()` en escrituras multi-tabla.
- Manual de usuario (`MANUAL_USUARIO.md`, `manual.php`, `api/manual_pdf.php`, `docs/*.pdf`): **no desplegar a producción** hasta autorización explícita.
