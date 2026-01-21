## Decisión de arquitectura (de acuerdo contigo)
- En vez de seguir cargando [PosController.php](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/Pos/PosController.php), se crea un controller nuevo dentro de `app/Http/Controllers/Pos/` dedicado a **reembolsos + notas + intercambio**.
- La idea es que `PosController` se quede para: abrir/cerrar sesión, dashboard, payment normal.

## Estructura propuesta (backend)
- Nuevo controller: `app/Http/Controllers/Pos/PosRefundController.php`
  - `index(PosSession $session)` → pantalla de reembolso (buscador de órdenes)
  - `lookupSale(PosSession $session, Request $request)` → buscar venta por doc (serie-correlativo) o por id
  - `preview(PosSession $session, Request $request)` → calcular `return_total`, `sale_total`, `net`, y validar cantidades disponibles
  - `process(PosSession $session, Request $request)` → ejecuta transacción (NC 07 + venta 01/03 opcional + kardex + pagos + jobs)

## Estructura propuesta (frontend)
- Reemplazar el botón “% desc.” por “Reembolso” en [Pos/Dashboard.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Pos/Dashboard.vue#L1003-L1046).
- Crear nueva página Inertia: `resources/js/pages/Pos/Refund.vue` (o `Pos/Refund/Index.vue`)
  - Selector de orden origen (puede reutilizar el layout de [Pos/Orders.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Pos/Orders.vue))
  - ReturnCart (ítems devueltos + qty)
  - SaleCart (ítems nuevos)
  - Resumen neto y pagos

## Flujo de negocio (claro y trazable)
- En una sola operación se pueden hacer 2 cosas:
  1) **Devolución** → Nota de Crédito 07 ligada a la boleta/factura origen.
  2) **Venta adicional** (intercambio) → nueva venta 01/03.
- Para que tú puedas “ver que pagaron con nota”, se registra un pago en la venta nueva con PaymentMethod “Nota de Crédito” por el monto aplicado.

## Lógica de transacción (process)
- DB::transaction:
  1) Crea NC 07 (`Sale`), `original_sale_id` apunta a la venta origen, líneas = qty devueltas.
  2) Publica NC 07 y registra Kardex **entry** (solo si `tracks_inventory=true`).
  3) Si hay productos nuevos, crea venta 01/03 y la publica, Kardex **exit** (solo si `tracks_inventory=true`).
  4) Pagos:
     - En venta nueva: pago “Nota de Crédito” por `min(return_total, sale_total)`.
     - Diferencia a cobrar: pagos normales positivos.
     - Si sobra devolución: pago negativo ligado a la NC.
  5) Jobs SUNAT `afterCommit` para ambos documentos.

## Rutas
- Agregar rutas separadas, por ejemplo:
  - GET `pos/{session}/refund` → `PosRefundController@index`
  - POST `pos/{session}/refund/lookup` → `lookupSale`
  - POST `pos/{session}/refund/preview` → `preview`
  - POST `pos/{session}/refund/process` → `process`

## Validaciones críticas
- No devolver más de lo vendido menos lo ya devuelto.
- Venta origen debe ser 01/03 con numeración.
- Si producto `tracks_inventory=false`, no registrar movimiento.

## Entregables
- Nuevo controller `PosRefundController` + rutas.
- Página POS de Reembolso.
- Transacción completa de devolución + venta + neteo de pagos.

Si esto te parece bien, procedo a implementarlo exactamente así (sin seguir cargando PosController).