## Diagnóstico
- `PosController` mezcla 4 responsabilidades distintas: ciclo de sesión (abrir/cerrar), vistas POS (dashboard/payment/orders), procesamiento de venta (DB + inventario + suscripciones), y endpoints API (clientes).
- El mayor “peso” está en `processPayment` (mucha lógica de dominio + side effects + logging + transacción).

## Objetivo
- Reducir complejidad y tamaño del controlador sin cambiar comportamiento.
- Aislar reglas de negocio en servicios/clases testeables.
- Tener un documento (`.md`) que explique la estructura y el flujo.

## Propuesta de separación (archivos)
### 1) Controladores (HTTP)
- `app/Http/Controllers/Pos/PosSessionController.php`
  - `open`, `storeOpen`, `dashboard`, `close`, `storeClose`
  - Solo validación de request, autorización y render/redirect.
- `app/Http/Controllers/Pos/PosSaleController.php`
  - `payment`, `processPayment`, `orders`
  - Mantener endpoints y rutas iguales (solo cambia la clase destino).
- `app/Http/Controllers/Pos/PosCustomerApiController.php`
  - `apiCustomers`, `apiPartnerLookup`, `apiUpsertCustomer`

### 2) Servicios de dominio
- `app/Services/Pos/PosSaleService.php`
  - Método principal tipo `processSale(PosSession $session, array $payload): Sale`.
  - Encapsula: journal lookup (con pivot), cálculo de totales, creación de sale + líneas, pagos, kardex.
- `app/Services/Pos/PosTaxCalculator.php`
  - Cálculo de subtotal/IGV/total por línea y por carrito.
- `app/Services/Pos/PosSubscriptionService.php`
  - Detectar planes y crear `MembershipSubscription`.
- `app/Services/Pos/PosJournalPolicy.php`
  - Regla Boleta/Factura según Partner document_type.

### 3) DTOs / objetos de entrada
- `app/Data/Pos/CartItemData.php` (o un simple validador)
- `app/Data/Pos/PaymentLineData.php`
- Esto evita “arrays sueltos” con keys mágicas en todo el flujo.

## Estrategia de migración (segura)
1) **Extraer lógica sin mover rutas todavía**
   - Crear servicios y hacer que `PosController@processPayment` delegue.
   - Mantener logs existentes (si los necesitas) pero concentrarlos.
2) **Mover endpoints API a `PosCustomerApiController`**
   - Actualizar rutas manteniendo URIs.
3) **Mover sesión y vistas a controladores dedicados**
   - Actualizar `routes/web.php` sin cambiar URLs.
4) **Reducir el controlador final**
   - `PosController` queda vacío o se elimina (según convenga).

## Tests / verificación
- Mantener y ampliar tests existentes:
  - Boleta/Factura por DNI/RUC.
  - “journal pertenece al POS config”.
  - Venta exitosa crea Sale + pagos + kardex (mock o sqlite).
- Correr `pest` + `npm run build` al final.

## Documento MD (lo que voy a crear)
Crear `docs/pos/pos-architecture.md` con:
- Diagrama simple (texto) del flujo:
  - Dashboard → Payment → processPayment → Sale + productables + payments + kardex + (suscripción opcional)
- Tabla de endpoints y controlador/método.
- Reglas de negocio: Boleta/Factura, cliente requerido (pendiente si decides), inventario, suscripciones.
- Convenciones de data (cart, payments) y dónde se valida.

## Entregables
- Nuevos controladores + servicios + rutas ajustadas (sin cambiar URLs).
- `.md` en `docs/pos/` describiendo la arquitectura.
- Suite de tests pasando.

Si confirmas, ejecuto el refactor de forma incremental (primero servicios, luego controllers/rutas) para minimizar riesgo.