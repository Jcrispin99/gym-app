## Por qué lo restauré
- Porque aún existían rutas web que apuntaban a `App\Http\Controllers\SaleController` en [web.php](file:///Users/wild/Herd/kraken_gym/routes/web.php), y al no existir la clase, comandos como `php artisan route:list` fallaban (Laravel intenta reflejar la clase del controller). Eso es señal de que también `route:cache`/deploy quedaría roto.
- Además, había un test que usa el endpoint web `POST /sales/{sale}/credit-note` ([RefundFlowTest.php](file:///Users/wild/Herd/kraken_gym/tests/Feature/Refunds/RefundFlowTest.php)), que depende del SaleController (respuesta 302).

## ¿Es necesario tener SaleController?
- No es necesario si el objetivo es “API-only”, pero entonces hay que **eliminar o redirigir** todas las rutas web que lo referencian y **migrar cualquier uso (tests/otros flujos)** a `/api/sales/...`.
- Mientras exista cualquier ruta que apunte a `SaleController`, el archivo/clase debe existir.

## Plan para dejar Sales 100% API (y poder borrar SaleController)
1. **Migrar el test que usa rutas web**: cambiar el flujo en `RefundFlowTest` de `POST /sales/{id}/credit-note` (302) a `POST /api/sales/{id}/credit-note` (201 JSON) y ajustar asserts.
2. **Limpiar rutas web de Sales**: en [web.php](file:///Users/wild/Herd/kraken_gym/routes/web.php), dejar solo las rutas GET que renderizan Inertia (`/sales`, `/sales/create`, `/sales/{id}/edit`) y remover `Route::resource('sales'...)` + `sales/{sale}/post|cancel|credit-note|sunat/retry` (ya no se usan en el front porque ahora pega a la API).
3. **Eliminar el controller web**: borrar `app/Http/Controllers/SaleController.php` y confirmar que no queda ninguna referencia.
4. **Verificación**: ejecutar build + suite de tests y confirmar que `route:list` funciona.

Si confirmas este plan, hago los cambios para que quede estrictamente API y `SaleController` se pueda eliminar sin romper nada.