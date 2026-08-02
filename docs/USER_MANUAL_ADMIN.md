# MathFlow — Manual del Administrador

## 1. Inicio de Sesión

1. Abre la aplicación MathFlow en tu navegador.
2. Ingresa tu correo electrónico y contraseña.
3. Haz clic en **Iniciar Sesión**.
4. Serás redirigido al **Dashboard de Administrador**.

**API:**
```
POST /api/v1/auth/login
Body: { "email": "admin@mathflow.com", "password": "tu_password" }
Respuesta: { "access_token": "...", "user": { ... } }
```

> Si olvidaste tu contraseña, usa **¿Olvidaste tu contraseña?** en la pantalla de login. Se enviará un correo con las instrucciones para restablecerla.

---

## 2. Dashboard del Administrador

El dashboard muestra un resumen general del sistema:

- **Total de usuarios** (estudiantes, docentes, padres)
- **Total de lecciones** publicadas y borradores
- **Total de evaluaciones** publicadas y borradores
- **Período académico activo**
- **Usuarios recientes** (últimos 10 registros)

**API:**
```
GET /api/v1/dashboard/admin
Headers: Authorization: Bearer <token>
```

---

## 3. Gestión de Usuarios

### 3.1 Ver la lista de usuarios

1. Navega a **Administración > Usuarios**.
2. Usa el campo de **búsqueda** para filtrar por nombre o correo.
3. Filtra por **rol**: Todos, Estudiantes, Docentes, Padres.
4. Navega entre páginas con los controles de paginación.

**API:**
```
GET /api/v1/admin/users?role=student&search=Juan&per_page=20
Headers: Authorization: Bearer <token>
```

### 3.2 Crear un usuario

1. Haz clic en **Crear Usuario**.
2. Completa los campos obligatorios:
   - **Nombre completo**: Nombre y apellidos del usuario.
   - **Correo electrónico**: Debe ser único en el sistema.
   - **Contraseña**: Mínimo 8 caracteres.
   - **Rol**: Estudiante, Docente o Padre.
3. Opcionalmente, asigna **Institución** y **Grado** (para estudiantes).
4. Haz clic en **Guardar**.

**API:**
```
POST /api/v1/admin/users
Body: {
  "full_name": "Juan Pérez",
  "email": "juan@escuela.com",
  "password": "MiPassword123",
  "role": "student",
  "institution": "Escuela Nacional",
  "grade": "5to"
}
```

### 3.3 Editar un usuario

1. Haz clic en el usuario que deseas editar.
2. Modifica los campos que necesites.
3. Haz clic en **Actualizar**.

**API:**
```
PUT /api/v1/admin/users/{id}
Body: {
  "full_name": "Juan Pérez García",
  "grade": "6to"
}
```

### 3.4 Activar / Desactivar un usuario

1. En la lista de usuarios, haz clic en el botón de **estado** del usuario.
2. Confirma la acción.

> **Nota:** No puedes desactivar tu propia cuenta ni la del último administrador activo.

**API:**
```
POST /api/v1/admin/users/{id}/activate
POST /api/v1/admin/users/{id}/deactivate
```

### 3.5 Eliminar un usuario

1. Haz clic en **Eliminar** junto al usuario.
2. Confirma la eliminación.

> **Nota:** No se pueden eliminar administradores ni tu propia cuenta.

**API:**
```
DELETE /api/v1/admin/users/{id}
```

### 3.6 Importar usuarios desde CSV

1. Prepara un archivo CSV con las columnas: `full_name`, `email`, `role`, `password` (opcional).
2. Los nombres de columna también pueden estar en español: `nombre`, `email`, `rol`, `contraseña`.
3. Haz clic en **Importar CSV** y selecciona el archivo.
4. El sistema mostrará el número de usuarios importados y cualquier error encontrado.

**API:**
```
POST /api/v1/admin/import
Body: FormData { file: archivo.csv }
```

### 3.7 Exportar usuarios

1. Haz clic en **Exportar Usuarios**.
2. Se descargará un archivo CSV con todos los usuarios (o filtrados por rol).

**API:**
```
GET /api/v1/admin/export?role=student
```

---

## 4. Configuración del Sistema

1. Navega a **Administración > Configuración**.
2. Modifica los parámetros:
   - **Nombre de la institución**: Nombre que aparece en la plataforma.
   - **Color primario**: Color principal de la interfaz (formato HEX).
   - **Color secundario**: Color secundario de la interfaz.
   - **Logo**: URL del logo de la institución.
   - **Notificaciones por correo**: Configura qué notificaciones se envían por email.
   - **Frecuencia de backups**: Diario, semanal o mensual.
3. Haz clic en **Guardar**.

**API:**
```
GET /api/v1/admin/config
PUT /api/v1/admin/config
Body: {
  "institution_name": "Colegio Nacional",
  "primary_color": "#004AC6",
  "secondary_color": "#006C49"
}
```

---

## 5. Períodos Académicos

### 5.1 Ver períodos

1. Navega a **Administración > Períodos Académicos**.
2. Se muestra la lista de todos los períodos ordenados por fecha de inicio.

**API:**
```
GET /api/v1/admin/periods
```

### 5.2 Crear un período

1. Haz clic en **Crear Período**.
2. Completa:
   - **Nombre**: Ej. "Primer Semestre 2025".
   - **Fecha de inicio**: Primer día del período.
   - **Fecha de fin**: Último día del período.
   - **Descripción** (opcional).
   - **Activo**: Actívalo si deseas que sea el período vigente.
3. Haz clic en **Guardar**.

> Si activas un nuevo período, los demás se desactivarán automáticamente.

**API:**
```
POST /api/v1/admin/periods
Body: {
  "name": "Primer Semestre 2025",
  "start_date": "2025-01-15",
  "end_date": "2025-06-30",
  "description": "Período académico regular",
  "is_active": true
}
```

### 5.3 Editar / Eliminar un período

1. Selecciona el período y haz clic en **Editar** o **Eliminar**.
2. Modifica los campos necesarios y guarda.

**API:**
```
PUT /api/v1/admin/periods/{id}
DELETE /api/v1/admin/periods/{id}
```

---

## 6. Copias de Seguridad (Backups)

### 6.1 Crear un backup

1. Navega a **Administración > Backups**.
2. Haz clic en **Crear Backup**.
3. El sistema generará una copia de la base de datos en formato SQL.

**API:**
```
POST /api/v1/admin/backup
```

### 6.2 Ver el último backup

La sección muestra el último backup creado con su fecha, tamaño y nombre de archivo.

**API:**
```
GET /api/v1/admin/backup/last
```

### 6.3 Descargar un backup

1. Haz clic en **Descargar** junto al backup que deseas.
2. Se descargará el archivo `.sql` a tu computadora.

**API:**
```
GET /api/v1/admin/backup/download/{filename}
```

---

## 7. Reportes

### 7.1 Reporte de rendimiento

1. Navega a **Reportes > Rendimiento**.
2. Selecciona el **período**: semana, mes, trimestre, año, o todos los tiempos.
3. El reporte incluye:
   - Total de evaluaciones realizadas.
   - Puntaje promedio general.
   - Total de estudiantes activos.
   - Tasa de aprobación (puntaje >= 12).
   - Top 5 estudiantes con mejor promedio.
   - Áreas de dificultad por tipo de evaluación.

**API:**
```
GET /api/v1/reports/performance?period=month
```

### 7.2 Reporte de calificaciones

1. Navega a **Reportes > Calificaciones**.
2. Filtra por **evaluación**, **estudiante** o **período**.
3. Se muestra una tabla con todos los resultados.

**API:**
```
GET /api/v1/reports/grades?evaluation_id=xxx&student_id=yyy
```

### 7.3 Reporte de un estudiante

1. Selecciona un estudiante en la lista.
2. El reporte incluye:
   - Lecciones completadas y en progreso.
   - Promedio general y mejores/peores puntajes.
   - Racha de actividad.
   - Insignias obtenidas.
   - Análisis de fortalezas y debilidades.

**API:**
```
GET /api/v1/reports/student/{userId}
```

### 7.4 Exportar reportes

- **PDF**: Haz clic en **Exportar PDF** para descargar un documento con el reporte.
- **Excel**: Haz clic en **Exportar Excel** para descargar una hoja de cálculo.

**API:**
```
GET /api/v1/reports/export/pdf?period=month
GET /api/v1/reports/export/excel?period=month
```

---

## 8. Notificaciones del Sistema

El sistema envía notificaciones automáticas a los usuarios cuando:
- Se crea o publica una lección.
- Se publica una evaluación.
- Se obtiene una insignia.
- Se completan hitos importantes.

Los administradores pueden monitorear las notificaciones enviadas desde el panel de notificaciones.

---

## 9. Cerrar Sesión

1. Haz clic en tu avatar en la esquina superior derecha.
2. Selecciona **Cerrar Sesión**.

**API:**
```
POST /api/v1/user/logout
```
