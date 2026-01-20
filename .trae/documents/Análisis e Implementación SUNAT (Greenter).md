## Qué Entiendo del Documento (en tu contexto)
- Vamos a integrar facturación electrónica vía una **API externa (Greenter API)**.
- Este proyecto tendrá **un solo token global** (no por compañía).
- Es requisito guardar en `Sale` el estado/respuesta (`sunat_status` + `sunat_response` JSON) y **evitar reenvíos** cuando ya está aceptado.

## Ajustes Clave vs la guía
- Se elimina el concepto `SunatConnection` por compañía.
- El token se resuelve desde `.env` (por ejemplo `GREENTER_API_URL` y `GREENTER_API_TOKEN`).

## Implementación propuesta
### 1) Persistencia en ventas
- Crear migración para `sales` agregando:
  - `sunat_status` (string/enum: `pending|processing|sent|accepted|error|skipped`)
  - `sunat_response` (json nullable)
  - (opcional) `sunat_sent_at` (timestamp) si quieres trazabilidad.
- Actualizar `Sale` model con `casts` para `sunat_response`.

### 2) Servicio GreenterInvoiceService (real)
- Implementar `app/Services/GreenterInvoiceService.php`:
  - `__construct`: lee `GREENTER_API_URL`.
  - `resolveToken()`: lee `GREENTER_API_TOKEN`.
  - `buildPayloadFromSale(Sale $sale)`: arma payload usando:
    - `Sale->journal` (`document_type_code`, `is_fiscal`)
    - `Sale->company` (RUC/dirección)
    - `Sale->partner` (document_type/document_number, nombres)
    - `Sale->products` (productables) + `Tax` (rate_percent, affectation_type_code)
  - `sendInvoiceFromSale(Sale $sale)`:
    - Si `!journal.is_fiscal` o docType no soportado: marcar `skipped`.
    - Si `sunat_status = accepted` o `sunat_response.accepted = true`: no reenviar.
    - Si envía: set `processing`, POST a `invoices/send` (y luego persistir `accepted/sent/error` con `http_status` y detalles).
  - No loguear token.

### 3) Job asíncrono
- Crear `app/Jobs/SendSunatInvoice.php`:
  - recibe `sale_id`
  - carga `Sale` con relaciones necesarias
  - llama a `GreenterInvoiceService->sendInvoiceFromSale($sale)`

### 4) Disparo desde el flujo de negocio
- En el punto donde una venta queda final (mínimo: `SaleController@post`), despachar el Job con `afterCommit()`.
- (Si el POS crea/postea ventas por otro flujo, también se engancha ahí.)

### 5) UI mínima (opcional pero recomendable)
- Mostrar `sunat_status` y un resumen de `sunat_response` en la vista de venta.
- Botón “Reintentar” que vuelva a despachar el Job solo si no está `accepted`.

## Verificación
- Tests + build.
- Caso de prueba manual:
  - Postear venta fiscal (01/03) → cambia `sunat_status` y guarda `sunat_response`.
  - Reintentar una aceptada → no dispara envío.

Si estás de acuerdo con estos puntos, paso a implementarlo tal cual (migración sales + service + job + dispatch y guardado de estado/respuesta).