Tienes toda la razón. Si seleccionas "Nota de Crédito" como método de pago, el sistema **debería** listar las notas de crédito disponibles del cliente para que puedas usarlas como saldo a favor. Actualmente, solo te deja poner un monto manual, lo cual es propenso a errores y no descuenta el saldo de la nota de crédito real.

### Plan de Implementación

Para corregir esto, propongo los siguientes cambios:

1.  **Frontend (Vue)**:
    *   Detectar cuando seleccionas el método de pago "Nota de Crédito".
    *   Mostrar un botón "Buscar Nota" (o abrir automáticamente un listado).
    *   **Validación**: Si no has seleccionado un cliente en la venta, pedirte que selecciones uno primero.
    *   **Listado**: Mostrar una ventana con las Notas de Crédito disponibles de ese cliente (que tengan saldo pendiente).
    *   Al seleccionar una, llenar automáticamente el monto a usar (máximo el saldo de la nota o el total de la venta).

2.  **Backend (Laravel)**:
    *   Crear un endpoint `/pos/{session}/credit-notes/{partner}` que devuelva las ventas tipo "Nota de Crédito" (Documento 07) con estado `posted` y que aún no se hayan consumido totalmente.
    *   (Opcional pero recomendado) Actualizar el controlador de pagos para validar que la nota de crédito existe y tiene saldo, y registrar su uso.

**¿Te parece bien este flujo?**
1. Seleccionas Cliente -> 2. Seleccionas Pago "Nota de Crédito" -> 3. Te muestra lista de sus notas -> 4. Eliges una -> 5. Se aplica el monto.

Si estás de acuerdo, procederé a implementarlo ahora mismo.