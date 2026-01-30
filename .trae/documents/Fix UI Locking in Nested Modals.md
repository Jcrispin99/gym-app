## Corregir Bloqueo de Interacción en Modales Anidados

### 1. Ajustar AsyncCombobox.vue
- Cambiar la propiedad `:modal="true"` a `:modal="false"` en el componente `Popover`. Esto evitará que el selector bloquee el `body` de la página.
- Eliminar los estilos manuales `z-[100]` y `pointer-events: auto` que añadimos anteriormente, permitiendo que Radix gestione las capas automáticamente.

### 2. Ajustar FormDialog.vue
- Eliminar la clase `z-[100]` y el estilo `pointer-events: auto` del `DialogContent`.
- Al ser el `Dialog` un componente modal por naturaleza, ahora podrá tomar el control del puntero sin conflictos una vez que el selector (no-modal) se cierre.

### 3. Verificación
- Abrir el formulario de compras.
- Abrir el selector de productos.
- Hacer clic en "Crear producto".
- Verificar que el modal de creación sea totalmente interactivo y que, al cerrarlo, la página principal vuelva a la normalidad sin quedarse bloqueada.
