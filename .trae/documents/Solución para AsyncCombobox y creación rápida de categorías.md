# Plan para corregir y mejorar AsyncCombobox

He analizado los archivos y el problema principal es que faltan las importaciones necesarias en `CreateEdit.vue`, lo que impide que el componente se renderice. Además, realizaremos mejoras al componente `AsyncCombobox` para que sea más robusto y reutilizable para otros modelos como usuarios, atributos, etc.

## 1. Corregir importaciones en CreateEdit.vue
El componente `AsyncCombobox` y los componentes de `Dialog` (para la creación rápida) se están usando en el template pero no han sido importados en la sección `<script setup>`.

- Importar `AsyncCombobox` desde `@/components/AsyncCombobox.vue`.
- Importar los componentes de `Dialog` desde `@/components/ui/dialog`.
- Importar `CategoryForm` (referenciando a `@/pages/Categories/Form.vue`) para que el modal de creación funcione.

## 2. Mejorar el componente AsyncCombobox.vue
Para que el componente sea realmente reutilizable y funcione bien con búsquedas asíncronas:

- **Desactivar el filtrado interno**: El componente `Command` filtra internamente los resultados. En una búsqueda asíncrona, queremos que el servidor sea el único que filtre. Añadiremos `:filter-function="() => 1"` al componente `Command` para que siempre muestre los resultados que vienen del API.
- **Ancho configurable**: Añadir una prop `width` para que el popover pueda tener diferentes anchos (por defecto `w-[420px]`).
- **Soporte para etiquetas personalizadas**: Asegurar que las props `optionId` y `optionLabel` funcionen correctamente para cualquier tipo de objeto (usuarios, atributos, etc.).

## 3. Verificación y Pruebas
- Verificar que el selector de "Categoría" aparezca correctamente en el formulario de productos.
- Probar la búsqueda asíncrona y asegurar que los resultados se muestren sin ser filtrados erróneamente por el componente visual.
- Probar el botón "Crear" para asegurar que abre el modal y guarda la nueva categoría, seleccionándola automáticamente después.

## 4. (Opcional) Ejemplo de uso para otros campos
Si lo deseas, puedo mostrarte cómo reemplazar otros selectores (como el de atributos) para usar este mismo componente `AsyncCombobox`, manteniendo la consistencia en toda la aplicación.

¿Deseas que proceda con estos cambios?