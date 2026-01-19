## Objetivo
- Reemplazar el sidebar hardcodeado en Vue por un menú **config-driven** (similar a tu `config('sidebar')` + composer), pero para **Laravel + Inertia/Vue**.

## Diseño (equivalente a tu Livewire)
- **Fuente de verdad**: `config/sidebar.php` (array con `type: header|link|group`, `title`, `route|url`, `active` y `can`).
- **Construcción en backend**: un `SidebarBuilder` transforma el config en un árbol “listo para renderizar” y:
  - Genera `url` con `route()`.
  - Calcula `isActive` con `request()->routeIs(...)` (igual que tu composer).
  - Filtra por permisos (`can`).
- **Entrega a Vue**: se comparte por Inertia en `HandleInertiaRequests::share()` como `props.sidebar`.
- **Render en Vue**: `AppSidebar.vue` solo itera el arreglo y renderiza según `type`.

## Permisos (estado actual del repo)
- En este repo no hay Spatie Permission ni gates/policies definidos; el control principal es `user_type` (`staff/customer/provider`) + middleware `staff`.
- Por eso, el plan contempla `can` inicialmente como “tipos” (ej. `['staff']`) y deja el hook para que luego puedas migrar a `user->can('read_products')` si implementas permisos finos.

## Implementación propuesta
1) **Crear** `config/sidebar.php`
- Definir estructura similar a la que pegaste:
  - `header`: `{ type: 'header', title: 'Principal', can?: [...] }`
  - `link`: `{ type: 'link', title, icon, route|url, active, can?: [...] }`
  - `group`: `{ type: 'group', title, icon, active, items: [...], can?: [...] }`

2) **Crear** builder en backend
- `app/Services/Sidebar/SidebarBuilder.php` (o `App\Services\Sidebar\SidebarService`).
- Entrada: `config('sidebar')`.
- Salida: arrays “limpios” para Vue:
  - `type`, `title`, `icon` (string key), `href` (string URL), `isActive` (bool), `items` (si group).
- Implementar:
  - `isActive`: `request()->routeIs($active)` donde `$active` puede ser string o array.
  - `authorize`: inicialmente soportar `can: ['staff']` validando `$user?->user_type`.

3) **Compartir** el sidebar por Inertia
- En `app/Http/Middleware/HandleInertiaRequests.php` agregar:
  - `'sidebar' => fn () => app(SidebarBuilder::class)->build($request)`

4) **Refactor** `resources/js/components/AppSidebar.vue`
- Eliminar arrays hardcodeados (`gymItems`, `inventoryItems`, etc.).
- Leer `const items = computed(() => page.props.sidebar)`.
- Render:
  - `header` → un label/separador.
  - `link` → `SidebarMenuButton` con `:is-active="item.isActive"`.
  - `group` → `Collapsible` + `SidebarMenuSub`, usando `group.isActive` para que abra por defecto.
- **Iconos**: mapear `icon: 'Package' | 'Users' | ...` a componentes de `lucide-vue-next` con un diccionario `{ Package, Users, ... }`.

5) **Typescript types**
- Agregar tipos `SidebarItem` (union) en `resources/js/types/sidebar.ts` o ampliar `resources/js/types/index.d.ts`.

## Verificación
- `npm run build`.
- Navegar por varias rutas para confirmar:
  - `isActive` correcto en links.
  - grupos se abren si una subruta está activa.
  - items se ocultan según `can`.

## Resultado
- Sidebar declarativo (solo config), fácil de mantener.
- Active state y permisos calculados en backend (como tu enfoque Livewire) y Vue queda “tonto” renderizando.

¿Confirmas que lo armemos con `can` basado en `user_type` por ahora (staff/customer/provider) y luego lo extendemos a permisos finos si añades Spatie/Gates?