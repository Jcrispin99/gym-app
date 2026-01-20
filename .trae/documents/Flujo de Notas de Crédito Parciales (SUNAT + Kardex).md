## Qué Entiendo (según tu criterio)

- Una **Nota de Crédito** será otro registro en `sales` (mismo modelo `Sale`), asociado a un `Journal` con `document_type_code = '07'`.

- Se crea desde una venta original **ya aceptada** y se guarda una referencia `original_sale_id`.

- No habrá toggle “parcial/total”: el sistema determina **Motivo 06 vs 07** comparando el borrador con la venta original al momento de enviar a SUNAT.

## Flujo Propuesto (end-to-end)

### 1) Punto de entrada: “Crear Nota” desde una venta

- En una venta original (01/03) con estado adecuado (posted y/o sunat accepted), botón **Crear Nota de Crédito**.

### Acción backend:

Clona la venta original a un nuevo `Sale` en `draft`.

- Cambia `journal_id` al journal de Nota de Crédito (07) correspondiente.
- Setea `original_sale_id = venta_original.id`.
- Clona también `products` (productables) con las cantidades originales.

### 2) Edición del borrador (usuario define parcialidad)

- Se reutiliza el mismo módulo de edición de ventas (porque sigue siendo `Sale` en `draft`).

- El usuario puede:
    - quitar líneas,

    - reducir cantidades,

    - opcionalmente ajustar precios si el caso lo permite.

### 3) Publicación de la Nota (impacto stock)

- Al “publicar” una nota 07:
    - En vez de `registerExit` (como venta normal), se hace **`registerEntry`** **en Kardex** por cada línea del borrador (retorno de stock).

    - Se guarda con `status='posted'`.

- La venta original **no se cancela**: sigue posted; el retorno queda representado por la nota.

### 4) Envío SUNAT (Greenter)

- El Job (SendSunatInvoice) debe soportar:
    - `01/03` -> `invoices/send`

    - `07` -> `notes/send`

- Payload 07 debe incluir:
    - `tipDocAfectado` = docType de la venta original (01/03)

    - `numDocAfectado` = `original.serie-original.correlative`

    - `codMotivo/desMotivo`

### 5) Detección automática Total vs Parcial (motivo 06 vs 07)

- En el service, al preparar payload 07:
    - Construir un mapa `{product_product_id -> qty_total}` de la venta original.

    - Construir el mapa equivalente de la nota borrador.

    - **Total (06)** si los mapas son exactamente iguales (mismas keys y mismas cantidades).

    - **Parcial (07)** si falta alguna línea, hay qty menor en alguna, o hay cualquier diferencia.

## Reglas/validaciones mínimas recomendadas

- No permitir crear nota si la venta original no tiene `serie/correlative` o no es 01/03.

- Al guardar/publicar nota:
    - No permitir cantidades > original.

    - No permitir devolver más de lo ya devuelto (si existen varias notas contra la misma venta, sumar las ya publicadas/enviadas).

- Manejo `tracks_inventory`:
    - Solo registrar Kardex entry para productos que trackean inventario.

## Qué falta hoy en el repo (para implementar tu flujo)

- Campo `original_sale_id` en `sales` (no existe actualmente).

- Acciones/routes:
    - “Crear Nota” (clonar)

    - Publicar Nota (branch de Kardex: entry en vez de exit)

- Extender `GreenterInvoiceService` para 07 (notes/send + campos afectados + motivo 06/07 por comparación).

## Verificación

- Caso total:
    - Crear nota sin editar -> motivo 06 y entry de stock igual a la venta.

- Caso parcial:
    - Reducir qty/quitar línea -> motivo 07 y entry solo por lo devuelto.

- En ambos:
    - `sunat_status/sunat_response` en la nota.

Si este flujo calza con tu visión, el siguiente paso es implementarlo tal cual (sin tablas nuevas), añadiendo solo `original_sale_id` y el branching de Kardex + envío 07.
