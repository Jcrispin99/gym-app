## Estado Actual (lo que ya tenemos)
- CategoryApiController ya está 100% orientado a JSON: valida con `$request->validate`, usa route-model-binding, y responde con `response()->json(['data' => ...])` para index/show/store/update y `{ ok: true }` en delete. Ver [CategoryApiController](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/Api/CategoryApiController.php).
- Las rutas API “internas del panel” viven en [api.php](file:///Users/wild/Herd/kraken_gym/routes/api.php) bajo `middleware(['web','auth'])` (sesión/cookie).
- PurchaseController todavía es “web/Inertia”: renderiza páginas y hace `redirect()->route(...)` para store/update/destroy/post/cancel. Ver [PurchaseController](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/PurchaseController.php).
- El front de Categories ya funciona con patrón “web solo render + todo CRUD por axios”: páginas Inertia en web.php y llamadas a `/api/...` desde Vue (ej. [Categories/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Categories/Form.vue), [Categories/FormPage.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Categories/FormPage.vue)).
- El front de Purchases hoy todavía usa `useForm`/router Inertia para POST/PUT/DELETE y acciones (ej. [Purchases/Create.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Purchases/Create.vue), [Purchases/Edit.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Purchases/Edit.vue), [Purchases/Index.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Purchases/Index.vue)).

## Objetivo de Migración
- Mantener `routes/web.php` solo para renderizar páginas Inertia (navegación) como ya se hace en Categories.
- Mover CRUD + workflow (post/cancel) de Purchases a un `PurchaseApiController` que devuelva JSON y sea consumido por axios.

## Diseño del PurchaseApiController (endpoints mínimos)
- **Listado**: `GET /api/purchases`
  - Soportar filtros actuales: `search`, `status`, y opcional `page/per_page`.
  - Respuesta recomendada: `{ data: Purchase[], meta: { pagination... } }`.
- **Detalle**: `GET /api/purchases/{purchase}`
  - Cargar `productables.productProduct`, `productables.tax`, `partner`, `warehouse`.
  - Incluir `activities` (últimas 20) para reemplazar lo que hoy se arma en `PurchaseController::edit`.
- **Store**: `POST /api/purchases`
  - Copiar la lógica de `PurchaseController::store` (journal por defecto, SequenceService, productables, total, transaction) pero retornar JSON `201`.
- **Update**: `PUT /api/purchases/{purchase}`
  - Copiar `PurchaseController::update`, pero en vez de redirect, devolver `{ data: purchase_actualizada }`.
  - Si no es draft: responder 422 con `ValidationException::withMessages` (homogéneo con el resto de APIs).
- **Delete**: `DELETE /api/purchases/{purchase}`
  - Mantener regla “solo draft”. Responder `{ ok: true }`.
- **Workflow**:
  - `POST /api/purchases/{purchase}/post` (draft → posted + kardex + activity log)
  - `POST /api/purchases/{purchase}/cancel` (posted → cancelled + kardex revert + activity log)
  - Responder `{ data: purchase_actualizada }`.

## Datos auxiliares que hoy vienen por props (suppliers/warehouses/taxes)
Para que Purchases quede como Categories (web solo render):
- Agregar endpoint de “bootstrap” para formularios:
  - `GET /api/purchases/form-options` → `{ data: { suppliers, warehouses, taxes } }`
  - suppliers: `Partner::suppliers()->active()` (si aplica)
  - warehouses: ya existe `/api/warehouses`, pero este endpoint evita 3 llamadas.
  - taxes: no hay Tax API hoy; este endpoint lo resuelve sin crear otro controller.

## Cambios en rutas
- [routes/api.php](file:///Users/wild/Herd/kraken_gym/routes/api.php): agregar bloque Purchases siguiendo el mismo estilo que Categories.
- [routes/web.php](file:///Users/wild/Herd/kraken_gym/routes/web.php#L209-L216): reemplazar `Route::resource('purchases', PurchaseController::class)` por rutas tipo closure (como Categories):
  - `GET /purchases` → render `Purchases/Index`
  - `GET /purchases/create` → render `Purchases/FormPage` (o seguir con `Purchases/Create` si no se refactoriza aún)
  - `GET /purchases/{purchase}/edit` → render `Purchases/FormPage` con `purchase_id`
  - Remover rutas web de `post/cancel/store/update/destroy` cuando el front ya use API.

## Refactor Front (para llegar al patrón Categories)
- **Index**: cambiar acciones a axios:
  - load inicial `axios.get('/api/purchases')`
  - delete/post/cancel via axios y actualizar lista local (igual que Categories/Index).
- **Create/Edit**:
  - Crear `Purchases/Form.vue` + `Purchases/FormPage.vue` (idéntico enfoque a Categories) o adaptar las páginas existentes.
  - El form debe:
    - cargar `form-options` (suppliers/warehouses/taxes)
    - en edit: cargar purchase por `/api/purchases/{id}`
    - submit con axios (POST/PUT) y exponer `submit/processing` si se sigue el patrón `defineExpose` como Categories.

## Validación/compatibilidad
- Mantener payload de líneas igual al actual (`products.*.product_product_id/quantity/price/tax_id`) para no romper UI.
- Mantener la lógica de cálculo del backend como fuente de verdad (subtotal/tax/total), aunque el front muestre cálculos.

## Verificación
- Probar desde UI:
  - Crear borrador, editar borrador, publicar, cancelar, eliminar.
  - Revisar que Activity Log siga apareciendo (vía `activities` del show).
- Probar errores 422: publicar algo no-draft, cancelar algo no-posted, delete no-draft.

Si confirmas este plan, el primer entregable será: crear `PurchaseApiController` + rutas API + responses JSON consistentes; luego refactor del front para consumirlo y finalmente simplificar web routes a solo Inertia.