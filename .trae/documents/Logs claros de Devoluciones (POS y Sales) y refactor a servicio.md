## Qué pasa hoy (diagnóstico)
- El POS refund (`/pos/{session}/refund`) crea una **Nota de Crédito (Sale docType 07)** en `process()` con `user_id = Auth::id()` y `pos_session_id` ([PosRefundController.php](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/Pos/PosRefundController.php#L317-L631)).
- La validación de “no exceder lo original” en POS se hace en `calculateReturnTotal()` comparando `qty_requested` vs `(qty_vendida - qty_ya_devuelta)` ([PosRefundController.php](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/Pos/PosRefundController.php#L691-L738)). En Sales, lo valida al publicar (post) para docType 07 ([SaleController.php](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/SaleController.php#L353-L420)).
- Los “logs” actuales pueden verse vacíos o poco claros porque:
  - Spatie Activitylog por defecto registra eventos genéricos (“created/updated”) y solo con campos logueados.
  - Para tu necesidad (“quién hizo la devolución / qué devolución”), hace falta **un log manual con descripción humana** y propiedades útiles.

## Qué debería verse para ti (requisito)
- Cuando se crea una **Nota de Crédito** (desde Sales o desde POS), el historial debe mostrar algo como:
  - “Devolución POS creada” / “Nota de Crédito creada”
  - Usuario (causer) que la hizo
  - Origen: `B004-00000001` (sale_id)
  - Método de reembolso/pago (si aplica), monto, pos_session
  - Ítems devueltos (resumen)

## Refactor recomendado (para limpiar lógica repetida)
- Hoy hay lógica repetida entre Sales y POS:
  - Resolver journal 07 (BC/FC)
  - Calcular disponible por producto
  - Crear sale 07 con líneas y kardex entry
  - (POS) pagos/venta nueva intercambio
- Propuesta: crear un servicio único, por ejemplo `RefundService` (o `CreditNoteService`) con métodos:
  - `resolveCreditNoteJournal(companyId, originDocType, originJournalCode?, posConfig?)`
  - `calculateAvailableQty(originSale)` (reutilizable por Sales y POS)
  - `createCreditNote(originSale, journal, lines, context)`
  - `postCreditNote(creditSale)`
  - `logRefundEvent(originSale, creditSale, context)`

## Cambios concretos a implementar
1) **Logs manuales claros**
   - En POS `process()`: registrar `activity()` para:
     - `performedOn($creditSale)` con log “Devolución POS creada” + `withProperties({origin_sale_id,pos_session_id,to_refund,applied,to_pay,pay_method,refund_method,items})`.
     - opcional: también log sobre el `origin` (“Se creó NC #...”).
   - En Sales `createCreditNote()` y `post()` (cuando publicas NC): log similar “NC creada/publicada” con origen.

2) **Unificación en servicio**
   - Extraer el bloque de cálculo de qty disponible y creación de líneas a un servicio (el POS ya tiene `calculateReturnTotal()` y Sales tiene algo similar al publicar).
   - Hacer que tanto `SaleController@createCreditNote` como `PosRefundController@process` llamen al mismo servicio para:
     - obtener disponibilidad
     - validar
     - construir líneas

3) **UI: mostrar “Devolución/NC” con autor**
   - En `Sales/Edit.vue` usar `activities` para renderizar:
     - la descripción humana
     - el `causer.name` (“quién hizo”)
     - y opcionalmente un “detalle” si hay `properties.items`.

## Verificación
- Ejecutar una devolución desde POS (`/pos/1/refund`) y comprobar en la pantalla de la NC (Sales/Edit) que aparece:
  - “Devolución POS creada”
  - usuario que la hizo
  - referencia al origen
- Crear una NC desde Sales y confirmar logs equivalentes.

Voy a implementar primero los logs manuales (lo que más te importa), y luego el refactor a servicio para evitar duplicación.