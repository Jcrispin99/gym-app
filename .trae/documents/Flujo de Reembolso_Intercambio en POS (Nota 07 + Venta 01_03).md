## Aclaración clave (tu duda)
- La **devolución** siempre se materializa como **Nota de Crédito 07** (otro `Sale`) que referencia la boleta/factura origen.
- La **venta “a partir de esa devolución”** es simplemente **una nueva venta 01/03** que se crea usando el “crédito” generado por la Nota de Crédito como **forma de pago** (total o parcial).
- Así tú puedes ver claramente:
  - que hubo una devolución (NC 07)
  - y que el cliente pagó/cobró diferencia
  - y si parte del pago fue “con Nota de Crédito” (aplicación de crédito).

## Cómo se ve contablemente en el sistema (2 documentos + pagos)
### Documento 1: Nota de Crédito (07)
- Se crea contra la venta origen (`original_sale_id`).
- Total de la NC = `return_total`.
- Si hay devolución física: Kardex **entrada** por qty devuelta.

### Documento 2: Nueva venta (01/03) (opcional)
- Se crea con los nuevos ítems (`sale_total`).
- Kardex **salida** por qty vendida.
- Pagos de esta venta se descomponen en:
  - **Pago “Nota de Crédito”** = `applied = min(return_total, sale_total)`
  - **Pago adicional** (efectivo/tarjeta) = `max(0, sale_total - return_total)`

### Si la devolución es mayor que la nueva venta
- Queda un saldo a devolver al cliente:
  - `refund_cash = max(0, return_total - sale_total)`
- Ese reembolso se registra como un pago **negativo** ligado a la NC 07 (o como un pago explícito “Reembolso” ligado a la NC).

## Flujo UX (POS)
1) Click botón **Reembolso** (reemplaza “% desc.”).
2) Buscar/seleccionar orden origen (reutilizar datos de Órdenes POS).
3) Seleccionar cantidades a devolver (ReturnCart).
4) (Opcional) Agregar productos a vender (SaleCart).
5) Confirmar:
   - Return total
   - Sale total
   - Aplicación de NC a la venta (automática)
   - Pago adicional o reembolso

## Flujo Backend (en una sola transacción)
1) Crear `Sale` NC (journal 07) + líneas devueltas + `original_sale_id`.
2) Publicar NC y registrar Kardex entry si `tracks_inventory=true`.
3) Si `sale_total > 0`, crear `Sale` nueva 01/03 + líneas vendidas.
4) Publicar venta nueva y registrar Kardex exit (respetar `tracks_inventory=true`).
5) Registrar pagos en `pos_session_payments`:
   - Para venta nueva:
     - `Nota de Crédito` (payment method) por `applied`.
     - + método real por diferencia si corresponde.
   - Para la NC:
     - si hay devolución de dinero: pago con `amount` negativo por `refund_cash`.
6) Encolar SUNAT:
   - NC 07 -> `notes/send`
   - Venta 01/03 -> `invoices/send`

## Cómo “ver” luego lo que pasó (trazabilidad)
- La venta nueva mostrará en su resumen de pagos:
  - “Nota de Crédito: S/ X.XX”
  - “Efectivo/Tarjeta: S/ Y.YY”
- La Nota de Crédito mostrará:
  - “Reembolso: S/ -Z.ZZ” si se devolvió dinero.
- Para vincular ambos documentos (NC ↔ venta nueva) propongo:
  - Mínimo viable: guardar en `notes` de ambos el documento relacionado.
  - Mejor (si quieres robustez): agregar `exchange_group_id` (UUID) en `sales` para agrupar intercambio.

## Cambios concretos a implementar
- UI: reemplazar botón “% desc.” por “Reembolso” y crear modal/página de reembolso en POS.
- Backend: endpoints para:
  - buscar ventas por documento/órdenes POS,
  - ejecutar reembolso/intercambio (creación 07 + opcional 01/03 + pagos + kardex).
- Pagos: agregar/usar PaymentMethod “Nota de Crédito” y permitir registrar `amount` negativo para reembolsos.

Si apruebas este flujo, lo implemento respetando tu idea de que el usuario pueda devolver y vender en una sola operación y que luego sea visible que parte del pago fue con Nota de Crédito.