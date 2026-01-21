Entiendo perfectamente. Quieres usar el campo `notes` (notas) de la tabla `Sale` para dejar constancia de que se usó una Nota de Crédito, en lugar de modificar la estructura de la base de datos. Es una solución práctica y menos invasiva.

### Ejemplo Práctico de tu Propuesta

Imagina que el cliente compra un producto de **S/ 100**.
Tiene una Nota de Crédito (NC-001) por **S/ 20**.
Paga los **S/ 20** con la nota y **S/ 80** en efectivo.

**Cómo funcionaría:**

1.  **Frontend**:
    *   Seleccionas la Nota de Crédito (NC-001).
    *   El sistema detecta que usaste esa nota.

2.  **Backend (Al procesar la venta)**:
    *   Se crea la venta por S/ 100.
    *   Se registran dos pagos:
        1.  Efectivo: S/ 80.
        2.  Método "Nota de Crédito": S/ 20.
    *   **En el campo `notes` de la Venta (Sale)**: Escribimos automáticamente:
        `"Pago con Nota de Crédito: NC-001 por S/ 20.00"`

**Resultado**:
*   En el historial de ventas, al ver esa venta, verás en las notas que se usó la NC-001.
*   No tocamos la estructura de tablas.
*   El cierre de caja sigue funcionando igual (suma por método de pago).

### Plan Ajustado

1.  **Frontend (`Payment.vue`)**:
    *   **Ocultar** los botones de diarios de notas de crédito (FC/BC).
    *   Enviar al backend la información de qué nota de crédito se usó (solo para generar el texto de la nota).

2.  **Backend (`PosController`)**:
    *   Recibir el dato de la nota usada.
    *   Al crear la venta (`Sale::create`), llenar el campo `notes` con el texto descriptivo: `"Nota de Crédito aplicada: [Documento] - S/ [Monto]"`.

¿Te parece bien este enfoque usando el campo `notes` existente?