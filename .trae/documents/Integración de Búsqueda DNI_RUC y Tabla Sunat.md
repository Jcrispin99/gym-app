# Plan de Implementación Ajustado (Tabla Sunat Simplificada)

He actualizado el plan según tus indicaciones para simplificar la tabla `sunat`.

## 1. Base de Datos: Tabla Sunat
Crearemos la tabla `sunat` con los campos mínimos requeridos por ahora.

- **Modelo**: `App\Models\Sunat`
- **Migración**: `create_sunats_table`
- **Campos**:
    - `token_apiperu`: Token para consultas DNI/RUC.
    - `token_facturacion`: Token para facturación (quedará vacío/nullable por el momento).
    - `timestamps`: Para control de creación/edición.

## 2. Servicio: ApiPeruService
Implementación en `App\Services\ApiPeruService.php`.
- Obtendrá el token usando `Sunat::first()->token_apiperu`.
- Implementará la estrategia de doble proveedor (apis.net.pe y apiperu.dev) y caché por 24h.

## 3. Controlador: PartnerLookupController
Controlador en `App\Http\Controllers\Api\PartnerLookupController.php`.
- Manejará la validación de DNI (8) y RUC (11).
- Devolverá un JSON estructurado compatible con el formulario de Partners/Clientes (nombres, dirección, etc.).

## 4. Rutas
- Agregar ruta en `routes/web.php`: `Route::get('/api/sunat/lookup', ...)` protegida por `auth`.

## Pasos Siguientes
Una vez confirmado, procederé a crear la migración, el modelo, el servicio y el controlador.
