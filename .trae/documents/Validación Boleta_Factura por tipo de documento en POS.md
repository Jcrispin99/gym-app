## Objetivo (solo lo pedido)
- En POS, al procesar venta:
  - Cliente con **DNI** → siempre **Boleta**
  - Cliente con **RUC** → siempre **Factura**
- Revisar los seeders para confirmar qué journals son Boleta/Factura.

## Lo que ya tienes (confirmado por seeders)
- `JournalSeeder` crea:
  - **F004** = FACTURA DE VENTA (`document_type_code=01`)
  - **B004** = BOLETA DE VENTA (`document_type_code=03`)
  - [JournalSeeder.php](file:///Users/wild/Herd/kraken_gym/database/seeders/JournalSeeder.php)
- `PosConfigSeeder` asocia al POS:
  - Factura → pivot `document_type=invoice`
  - Boleta → pivot `document_type=receipt`
  - [PosConfigSeeder.php](file:///Users/wild/Herd/kraken_gym/database/seeders/PosConfigSeeder.php)

## Plan de implementación
### 1) Exponer tipo de documento del journal al frontend
- En `PosController@payment`, en vez de mandar journals “crudos”, mapearlos a un DTO simple:
  - `{ id, code, name, document_type }` donde `document_type` viene del pivot (`invoice`/`receipt`).

### 2) Asegurar que el POS conozca el document_type del cliente
- En los props `customers` (y la respuesta de `apiUpsertCustomer`), incluir:
  - `document_type` y `document_number` (hoy solo mandan `dni`).
- Ajustar los tipos en `Pos/Payment.vue` para usar esos campos.

### 3) Forzar selección Boleta/Factura en el front (automático)
- En `Pos/Payment.vue`:
  - Si `currentClient.document_type === 'RUC'` → setear `selectedJournalId` al journal `invoice`.
  - Si no (DNI/CE/etc) → setear `selectedJournalId` al journal `receipt`.
- Con esto el cajero no tiene que pensar en el documento: se elige solo.

### 4) Validación dura en backend (para que no se “salte”)
- En `PosController@processPayment`:
  - Verificar que el `journal_id` elegido pertenece al POS (`posConfig->journals`), y leer su `pivot.document_type`.
  - Verificar que el cliente (`Partner`) tenga:
    - DNI → `pivot.document_type` debe ser `receipt`
    - RUC → `pivot.document_type` debe ser `invoice`
  - Si no cumple, retornar error claro (y no crear Sale).

### 5) Tests
- Test: cliente DNI + journal factura → falla.
- Test: cliente RUC + journal boleta → falla.
- Test: cliente RUC + journal factura → OK.

## Nota sobre “ventas sin cliente”
- Por ahora no lo tocaría en este cambio (porque dijiste “solo esto por el momento”). Cuando quieras, lo cerramos con validación `client_id required` en front/back.

Si confirmas, implemento los cambios en backend + frontend + tests siguiendo el estilo actual del repo (sin sobreescribir pantallas).