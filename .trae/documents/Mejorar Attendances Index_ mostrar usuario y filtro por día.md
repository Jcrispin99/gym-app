**Objetivo**

* Mostrar en el listado quién registró cada asistencia (usuario staff).

* Aplicar por defecto el filtro de fecha al día actual, con la opción de cambiar fecha.

**Backend (Controller)**

* Cargar relación del usuario que registró la asistencia:

  * En index, agregar eager load de registeredBy: [AttendanceController.php](file:///Users/wild/Herd/kraken_gym/app/Http/Controllers/AttendanceController.php#L19-L21).

* Filtro de fecha por defecto "hoy":

  * Si no viene request->date, aplicar whereDate(check\_in\_time, Carbon::today()).

  * Enviar en props.filters la fecha aplicada para que el front la inicialice.

* Mantener paginación y estadísticas actuales sin cambios.

**Frontend (Index.vue)**

* Tipado y datos:

  * Extender interface Attendance para incluir registered\_by (id) y registered\_by usuario (objeto) y is\_manual\_entry (boolean). Archivo: [Attendances/Index.vue](file:///Users/wild/Herd/kraken_gym/resources/js/pages/Attendances/Index.vue#L37-L54).

* UI: nueva columna "Registrado por":

  * Mostrar attendance.registered\_by?.name. Si es manual\_override o is\_manual\_entry true, añadir Badge "Manual".

  * Ubicar columna entre "Estado" y "Acciones" para mejor lectura.

* Inicialización de filtros:

  * selectedDate = props.filters.date (que llegará con el día actual) y mantener applyFilters.

* Accesibilidad/robustez:

  * Asegurar render seguro con opcionales (encadenamiento opcional) donde pueda faltar el usuario.

**Verificación**

* Cargar /attendances y confirmar que:

  * La tabla muestra el nombre del usuario staff en cada fila.

  * El listado por defecto contiene solo las asistencias de hoy.

  * Cambiar la fecha en el input actualiza el listado correctamente.

**Notas**

* No cambiamos lógica de negocio (check-in/validaciones); solo presentación y filtro por defecto.

* Si prefieres mostrar avatar del staff, se puede agregar usando attendance.registered\_by.photo\_url si

