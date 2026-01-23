# Diagnóstico del Backend

He inspeccionado tu código, específicamente `PosController.php` y la estructura general de `app/Http/Controllers`.

**Problemas identificados:**
1.  **"Fat Controllers" (Controladores Obesos):** `PosController.php` tiene más de 1000 líneas. Contiene lógica de negocio crítica (cálculo de impuestos, creación de ventas, gestión de inventario, lógica de suscripciones complejas) mezclada con la lógica de respuesta HTTP.
2.  **Acoplamiento:** La lógica de negocio está atada al framework de respuesta (Inertia/Redirecciones), lo que hace difícil reutilizarla para una API pura o una App Móvil.
3.  **Estructura Plana:** Muchos controladores en la raíz de `Controllers/` que deberían estar agrupados por dominio (ej. `SaleController`, `ProductController` deberían estar en módulos o carpetas específicas).
4.  **Lógica Repetida:** La transformación de datos (ej. convertir un modelo `Sale` a un array para el frontend) se hace manualmente dentro del controlador en lugar de usar `API Resources`.

---

# Plan de Refactorización: Hacia una Arquitectura API-First

Para lograr tu objetivo de "solo API" y ordenar el backend, propongo dividir la refactorización en 3 capas:

## 1. Capa de Dominio (Actions/Services)
Extraer la lógica de negocio fuera de los controladores. Usaremos el patrón **Actions** (una clase por acción de negocio).

**Archivos a crear (Ejemplos):**
*   `app/Actions/Pos/CreatePosSale.php`: Recibe los datos del carrito y crea la venta + líneas de producto.
*   `app/Actions/Pos/ProcessPosPayment.php`: Maneja el registro de pagos.
*   `app/Actions/Subscription/CreateSubscriptionFromSale.php`: Contiene toda esa lógica compleja de fechas de suscripción que vi en tu controlador.
*   `app/Actions/Inventory/UpdateStockAction.php`: Maneja el Kardex.

## 2. Capa de Transformación (API Resources)
Usar **Laravel Resources** para estandarizar cómo se devuelven los datos. Esto permite que tanto tu frontend actual (Inertia) como una futura App Móvil consuman los mismos datos.

**Archivos a crear:**
*   `app/Http/Resources/PosSessionResource.php`
*   `app/Http/Resources/SaleResource.php`
*   `app/Http/Resources/ProductResource.php`

## 3. Capa de Controladores (Estructura de Carpetas)
Reorganizar `app/Http/Controllers` para que refleje módulos en lugar de una lista plana.

**Nueva Estructura Propuesta:**
```
app/Http/Controllers/
├── Api/                 <-- Endpoints puramente JSON (para Apps/Integraciones)
│   ├── Pos/
│   │   ├── PosTransactionController.php
│   │   └── PosSessionController.php
│   ├── Partners/
│   │   └── PartnerController.php
│   └── ...
├── Web/                 <-- Controladores Inertia (Consumen las mismas Actions pero devuelven Vistas)
│   ├── Pos/
│   │   └── PosPageController.php
│   └── ...
└── ...
```

---

# Pasos de Ejecución Inmediata

Si estás de acuerdo, comenzaremos con lo más crítico: **Adelgazar el `PosController`**.

1.  **Paso 1:** Crear `CreatePosSaleAction` y mover la lógica de transacción (líneas 553-710 de `PosController`) allí.
2.  **Paso 2:** Crear `CreateSubscriptionFromSaleAction` y mover la lógica de fechas de suscripción (líneas 712-800+).
3.  **Paso 3:** Refactorizar el método `processPayment` del controlador para que simplemente llame a estas acciones.

¿Te parece bien comenzar con este plan?