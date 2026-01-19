## Qué Entiendo del Documento
- Es una propuesta para integrar facturación electrónica vía una **API externa (Greenter API)**, no Greenter “embebido” en Laravel.
- Se basa en:
  - `GREENTER_API_URL` en `.env`.
  - Resolver un **token por compañía** (por un modelo tipo `SunatConnection`).
  - Construir un **payload SUNAT** desde una `Sale` (company, client, details, totales).
  - Enviar por HTTP a endpoints tipo `invoices/send` o `notes/send`.
  - Guardar en la venta estado/respuesta: `sunat_status` + `sunat_response` (JSON), y evitar reenvíos.
  - Ejecutar el envío **asíncrono** con un Job (`SendSunatInvoice`) para no bloquear el POS.

## Qué Sí Calza con el Proyecto Actual
- `Company` ya tiene data útil para SUNAT (RUC, ubigeo/dirección) y un campo `logo_url`.
- `Journal` ya tiene campos alineados a SUNAT: `document_type_code` e `is_fiscal`.
- `Tax` ya tiene `affectation_type_code` (Catálogo 07) y `rate_percent`.
- `Sale` ya tiene serie/correlativo, journal, company, partner, warehouse, user y totales.
- Las líneas de venta existen en `productables` con `quantity/price/subtotal/tax_rate/tax_amount/total`.

## Qué NO Calza / Gaps Reales (hoy)
- La guía asume piezas que **no existen** en el repo:
  - No existe `app/Services/GreenterInvoiceService.php`.
  - No existe `app/Jobs/SendSunatInvoice.php`.
  - No existe `SunatConnection` ni migraciones relacionadas.
  - `sales` no tiene `sunat_status` ni `sunat_response`.
- Diferencias de modelo:
  - La guía habla de `PosOrder` / `pos_order_id`; en este proyecto no existe `PosOrder` y `Sale` tiene `pos_session_id`.
  - La guía usa `customer.identity`; aquí el cliente es `Partner` con `document_type` y `document_number`.
- El flujo actual de publicar venta (`SaleController@post`) solo mueve stock y cambia estado; no dispara envío SUNAT.

## Cómo Lo Haría en Laravel + Inertia (adaptado a tu sistema)
### 1) Persistencia y modelos
- Crear `sunat_connections`:
  - `id`, `company_id`, `token_ikoodev` (u otro nombre), opcional `client_id`, `client_secret`, `active`, timestamps.
  - Relación `Company->sunatConnection()`.
- Extender `sales`:
  - `sunat_status` (enum/string: draft|processing|sent|accepted|error|skipped).
  - `sunat_response` (json) para guardar `http_status`, `accepted`, `cdr_code`, `error`, etc.
  - (Opcional) campos para notas: `original_sale_id` o `original_serie/original_correlative` si vas a emitir NC/ND.

### 2) Servicio GreenterInvoiceService (real)
- Implementar `GreenterInvoiceService` usando `Http`:
  - `resolveTokenForSale(Sale $sale)` usando `sale->company->sunatConnection`.
  - `buildCompanyPayload()` mapeando `Company` a estructura Greenter.
  - `buildClientPayload()` mapeando `Partner` (document_type/document_number, nombres).
  - `buildDetailsAndTotalsPerLine()` usando `Sale->products` (productables) + `Tax` (`affectation_type_code`, `rate_percent`).
  - `sendInvoiceFromSale(Sale $sale)`:
    - validar `journal.is_fiscal` y `document_type_code` (01/03/07/08)
    - setear `sunat_status` y guardar `sunat_response`
    - evitar reenvío si ya está `accepted`
- Asegurar buenas prácticas:
  - No loguear token.
  - Capturar errores de red/validación y persistirlos.

### 3) Job asíncrono
- Crear `SendSunatInvoice` que reciba `sale_id` y llame al servicio.
- Despachar con `afterCommit()` donde corresponda.

### 4) Hook en el flujo de negocio
- En el punto donde una venta queda final (ej: `SaleController@post` o flujo POS cuando se procesa pago), disparar el Job:
  - Solo para `journal.is_fiscal = true` y tipos soportados.

### 5) UI/observabilidad mínima
- Mostrar en la vista de venta:
  - `sunat_status` + último mensaje.
  - Botón “Reintentar envío” si `error`.
- (Opcional) guardar y exponer enlaces de XML/CDR/PDF si la API lo devuelve.

## Puntos que Quiero que Me Corrijas (por si el sistema ya tiene reglas)
- ¿El token de Greenter realmente es por compañía (company_id) o global?
- ¿Van a emitir solo boleta/factura (03/01) o también notas (07/08) en esta etapa?
- ¿El evento de envío debe ocurrir al “postear” venta o al “cobrar” en POS?

Si confirmas, paso a implementarlo en el código (migraciones + modelos + servicio + job + disparo desde el flujo de ventas).