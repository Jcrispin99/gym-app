## Diagnóstico
- El panel "Historial de Cambios" en [Edit.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Sales/Edit.vue) muestra vacío porque `SaleController@edit` no está enviando `activities` al frontend (a diferencia de otros módulos como Purchases/Products/PosConfigs).
- Además, aunque se envíen activities, el modelo [Sale.php](file:///Users/wild/Herd/kraken_gym/app/Models/Sale.php#L187-L200) solo registra cambios en `serie/correlative/partner_id/total/status/payment_status`; por eso cambios típicos de la pantalla (ej. `notes`, `warehouse_id`, líneas) no aparecen.

## Opciones
- Opción A (rápida): En `SaleController@edit`, consultar `Activity::forSubject($sale)->with('causer')->latest()->take(20)->get()` y pasarlo como `activities` (patrón usado en Purchases/Products).
- Opción B (mejor UX): Expandir `Sale::getActivitylogOptions()` para incluir `notes`, `warehouse_id`, `subtotal`, `tax_amount`, `journal_id`, `sunat_status` (manteniendo `logOnlyDirty()` y `dontSubmitEmptyLogs()`).
- Opción C (más “inteligente”): En acciones clave del controller (update/post/cancel/createCreditNote/retrySunat) registrar logs manuales `activity()->performedOn($sale)->withProperties([...])->log('...')` incluyendo un resumen de cambios de productos (qty/altas/bajas). Esto captura siempre “qué pasó” aunque no cambien campos logueados.

## Implementación propuesta
- 1) Actualizar `SaleController@edit` para enviar `activities` (mapeado al shape que espera el Vue: `description/event/properties/created_at/causer`).
- 2) Ajustar [Sale.php](file:///Users/wild/Herd/kraken_gym/app/Models/Sale.php#L187-L200) para loguear también `notes` y `warehouse_id` (mínimo), y opcionalmente `subtotal/tax_amount/journal_id/sunat_status`.
- 3) Agregar logs manuales en `SaleController` para eventos de negocio (publicar, cancelar, crear NC, enviar SUNAT, editar líneas) con descripción clara.

## Verificación
- Editar una venta draft (cambiar notes/warehouse y productos) y comprobar que el panel muestra entradas.
- Publicar/cancelar y confirmar que aparecen eventos dedicados.

¿Confirmas que implementemos la Opción C (manual + automática) como estándar, o prefieres quedarnos en A+B para algo más simple?