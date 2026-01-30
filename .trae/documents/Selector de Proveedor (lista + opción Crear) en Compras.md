## **Objetivo**
- En `/purchases/create`, reemplazar el `Select` de “Proveedor” por un selector reusable basado en búsqueda remota, con opción “Crear …” (sin modal todavía).

## **Estado actual**
- En [Purchases/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Purchases/Form.vue#L355-L374) el proveedor se elige con `Select` y se alimenta con `suppliers` desde `GET /api/purchases/form-options`.
- Los componentes reutilizables ya existentes:
  - [AsyncCombobox.vue](file:///Users/wild/Herd/kraken_gym/resources/js/components/AsyncCombobox.vue)
  - [AsyncComboboxWithCreateDialog.vue](file:///Users/wild/Herd/kraken_gym/resources/js/components/AsyncComboboxWithCreateDialog.vue)
  - [FormDialog.vue](file:///Users/wild/Herd/kraken_gym/resources/js/components/FormDialog.vue)
- Uso actual del wrapper en productos: [Products/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Products/Form.vue#L654-L676)
- Para proveedores ya existe API lista para búsqueda:
  - `GET /api/suppliers?q=...&limit=...` ([SupplierApiController@index](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/Api/SupplierApiController.php#L14-L54))
  - `GET /api/suppliers/{supplier}` ([SupplierApiController@show](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/Api/SupplierApiController.php#L69-L89))

## **Implementación (sin modal aún)**
### 1) Crear componente reusable `SupplierCombobox.vue`
- Archivo: `resources/js/components/SupplierCombobox.vue`
- Responsabilidad:
  - Encapsular `AsyncCombobox` con endpoints ya definidos:
    - `searchUrl="/api/suppliers"`
    - `getUrlTemplate="/api/suppliers/{id}"`
  - `optionLabel`: mostrar `display_name` (fallback a business_name/first_name/last_name si falta).
  - Emitir:
    - `update:modelValue` (id del supplier)
    - `create(query)` cuando el usuario elija “Crear …”
  - Props mínimas:
    - `modelValue`, `disabled`, `placeholder`, `limit`, `extraParams` (para filtrar por `company_id` o `status` si quieres).

### 2) Reemplazar el `Select` de proveedor en Purchases
- Cambiar el bloque [Purchases/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Purchases/Form.vue#L355-L374) por `SupplierCombobox`.
- Mantener el comportamiento actual:
  - `:disabled="!isEditable"`
  - errores `partner_id`
- Manejo del create (sin modal):
  - Por ahora solo manejar `@create="(q) => openSupplierCreate(q)"`.
  - Implementación mínima de `openSupplierCreate`:
    - abrir `/suppliers/create` en nueva pestaña (para no perder el form de compra), o dejarlo como stub mostrando mensaje.

### 3) (Opcional) Mantener form-options tal cual
- No toco `GET /api/purchases/form-options` por ahora.
- Simplemente dejaremos de depender de `suppliers` para el selector.

## **Verificación**
- Revisar diagnostics TS/Vue en Purchases/Form.
- Probar en `http://localhost:8010/purchases/create`:
  - buscar proveedor por nombre/documento
  - seleccionar uno y confirmar que `form.partner_id` se setea
  - usar “Crear …” y confirmar que dispara el handler (aunque todavía no haya modal)

## **Siguiente paso (cuando digas “ok, ahora sí modal”)**
- Sustituir `SupplierCombobox` por [AsyncComboboxWithCreateDialog.vue](file:///Users/wild/Herd/kraken_gym/resources/js/components/AsyncComboboxWithCreateDialog.vue) apuntando a `SupplierForm` como `formComponent`, igual que productos.
- Eso ya te daría creación rápida sin salir del form de compras.
