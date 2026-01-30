# Alineación (qué está pasando hoy)

## Por qué ves esos textos (L607-610 y L775-778)
- Esos textos NO vienen del Form, están escritos explícitamente en [Products/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Products/Form.vue#L598-L611) y [Products/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Products/Form.vue#L766-L779) dentro de `<DialogHeader><DialogDescription>...`.
- El “Form” (ej. [Categories/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Categories/Form.vue) o [Attributes/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Attributes/Form.vue)) solo pinta los campos; el header del modal pertenece al contenedor (`Dialog`), no al Form.

## Cómo alimenta el API esa búsqueda
- `AsyncCombobox` hace `GET searchUrl` con params `{ q, limit, ...extraParams }` y pinta las opciones con la respuesta. Ver [AsyncCombobox.vue](file:///Users/wild/Herd/kraken_gym/resources/js/components/AsyncCombobox.vue#L84-L109).
- Para atributos, el backend ya está preparado: `GET /api/attributes?q=...&with_values=1` filtra en DB y devuelve `data`. Ver [AttributeApiController@index](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/Api/AttributeApiController.php#L12-L40).

# Objetivo (lo que tú describes)

## AsyncCombobox + Modal genérico + Form “inyectable”
- Estoy de acuerdo con tu idea: conviene tener un componente reutilizable que:
  1) muestre el combobox,
  2) muestre la opción “Crear …”,
  3) abra un modal genérico con botones (Cancelar/Crear),
  4) renderice dentro del modal *cualquier* Form,
  5) al guardar, cierre y setee el `modelValue` (y opcionalmente devuelva el objeto creado).

- Mantendría [AsyncCombobox.vue](file:///Users/wild/Herd/kraken_gym/resources/js/components/AsyncCombobox.vue) como componente “puro” de selección/búsqueda (sin conocer forms), y crearía un wrapper reutilizable que componga combobox + modal.

# Plan de implementación

## 1) Definir un “contrato” común para los Forms embebibles
- Para que un modal genérico pueda controlar botones, necesitamos consistencia:
  - El Form debe `defineExpose({ submit, processing })` (ya lo hacen Category/Attribute).
  - El Form debe emitir `saved(entity)`.
  - El Form debe poder recibir `initialName` (para precargar el texto escrito en el combobox).
- Ajuste necesario: [Attributes/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Attributes/Form.vue) hoy NO acepta `initialName`; habría que agregarlo y usarlo en modo create (igual que [Categories/Form.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Categories/Form.vue)).

## 2) Crear un componente reutilizable de modal para forms
- Crear `resources/js/components/FormDialog.vue` (o similar) que encapsule:
  - `<Dialog>` + `<DialogHeader>` + `<DialogFooter>`
  - Props: `title`, `description`, `open` (o v-model), `submitLabel`.
  - Slot default: el Form a renderizar.
  - Lógica: el botón “Crear” llama `formRef.submit()` y se deshabilita con `formRef.processing.value`.

## 3) Crear un wrapper: AsyncComboboxWithCreateDialog
- Crear `resources/js/components/AsyncComboboxWithCreateDialog.vue` (nombre a elección) que:
  - Renderiza `AsyncCombobox`.
  - Al `@create(query)`: abre `FormDialog`.
  - Pasa al slot del form: `query` (para initialName) y callbacks `onSaved`.
  - Al guardar:
    - cierra el modal,
    - emite `update:modelValue` con el ID creado,
    - opcional: emite `created(entity)`.

## 4) Refactor en Products/Form.vue
- Reemplazar los bloques duplicados de categoría y atributo (los `Dialog...DialogFooter` actuales) por el wrapper.
- Esto elimina esos textos “Crea una nueva categoría…” duplicados de cada pantalla, porque pasarían a props del wrapper.

## 5) Verificación en UI
- Probar en `/products/create`:
  - Buscar categoría, crear una nueva, que se seleccione automáticamente.
  - Buscar atributo, crear uno nuevo, que se agregue a la tabla y permita seleccionar valores.
- Revisar consola/TS diagnostics para asegurar que no hay props inválidas (especialmente `initialName` en AttributeForm).

Si confirmas este plan, implemento el wrapper + modal genérico y refactorizo Products/Form.vue para que categoría/atributo usen el componente reusable.