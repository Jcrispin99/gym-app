Entiendo perfectamente tu observación. El problema es que si un cliente compra **3 meses** de suscripción (cantidad 3), el sistema está creando **3 suscripciones separadas** que empiezan **todas hoy** (en paralelo), en lugar de crear una sola suscripción de 3 meses o 3 suscripciones consecutivas.

Si compro 3 meses, espero que mi suscripción dure desde hoy hasta dentro de 3 meses, no tener "triple acceso" por 1 mes.

### Análisis del Problema
En `PosController.php`, dentro del bucle que procesa el carrito:
```php
foreach ($cart as $item) {
    // ...
    // Si la cantidad es 3, el bucle se ejecuta una vez por item del carrito, pero no considera la cantidad para extender la duración.
    // O si el item está desglosado, crea suscripciones independientes con fecha start = NOW().
}
```
Actualmente el código itera sobre los items del carrito. Si en el carrito hay un item con `qty: 3`, necesitamos lógica para manejar eso.

### Plan de Solución

1.  **Modificar `PosController::processPayment`**:
    *   Detectar si el producto es una suscripción.
    *   Si `qty > 1`, en lugar de crear múltiples suscripciones paralelas, debemos:
        *   **Opción A (Mejor)**: Crear una **única suscripción** con la duración multiplicada (`duration_days * qty`).
        *   **Opción B**: Crear suscripciones consecutivas (la 2da empieza cuando acaba la 1ra). *La Opción A es más limpia para el usuario y el sistema.*

2.  **Lógica Propuesta (Opción A - Duración Extendida)**:
    *   Calcular la duración total: `Plan Duration * Quantity`.
    *   Fecha Fin = `Start Date + (Duration * Quantity)`.
    *   Crear una sola suscripción con esa fecha de fin extendida.
    *   Registrar el pago total.

3.  **Actualizar Test `TestPosFlow`**:
    *   Actualizar la validación del Caso 2.
    *   Esperar **1 sola suscripción** (no 3).
    *   Verificar que la `end_date` sea aprox 3 meses después de hoy (90 días).

¿Te parece bien ajustar el sistema para que **multiplique la duración** en lugar de duplicar suscripciones?