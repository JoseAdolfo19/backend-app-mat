# MathFlow — Programa de Capacitación para Administradores

---

## Objetivos

Al finalizar este programa, el administrador será capaz de:

1. Gestionar usuarios (crear, editar, importar, activar/desactivar).
2. Configurar la plataforma (institución, colores, notificaciones).
3. Administrar períodos académicos y copias de seguridad.
4. Monitorear el estado general del sistema.

**Duración estimada:** 3 horas

**Prerrequisitos:** Cuenta de administrador con acceso completo.

---

## Módulo 1: Administración de Usuarios (60 minutos)

### Temas

1. Ver, crear, editar y eliminar usuarios.
2. Activar y desactivar cuentas.
3. Importar usuarios desde CSV.
4. Exportar usuarios.

### Práctica

1. **Crear 3 usuarios de prueba:**
   - 1 docente: `profesor@escuela.com`
   - 1 estudiante: `alumno@escuela.com`
   - 1 padre: `padre@escuela.com`

2. **Importar usuarios desde CSV:**
   - Crea un archivo CSV con 5 usuarios:
     ```
     full_name,email,role,password
     Ana García,ana@test.com,student,Password123
     Pedro Martínez,pedro@test.com,teacher,Password123
     Laura Díaz,laura@test.com,student,Password123
     Carlos Ruiz,carlo@test.com,parent,Password123
     María López,maria@test.com,teacher,Password123
     ```
   - Importa el archivo desde la interfaz.

3. **Gestionar estados:**
   - Desactiva un usuario y verifica que no pueda iniciar sesión.
   - Reactívalo.

### Recursos de la API

```
GET    /api/v1/admin/users              → Listar usuarios
GET    /api/v1/admin/users/{id}         → Ver usuario
POST   /api/v1/admin/users              → Crear usuario
PUT    /api/v1/admin/users/{id}         → Editar usuario
DELETE /api/v1/admin/users/{id}         → Eliminar usuario
POST   /api/v1/admin/users/{id}/activate    → Activar
POST   /api/v1/admin/users/{id}/deactivate  → Desactivar
POST   /api/v1/admin/import             → Importar CSV
GET    /api/v1/admin/export             → Exportar CSV
```

### Consideraciones de seguridad

- No puedes desactivar tu propia cuenta.
- No puedes eliminar al último administrador activo.
- No se pueden eliminar cuentas de administrador.

---

## Módulo 2: Configuración del Sistema (40 minutos)

### Temas

1. Configurar información de la institución.
2. Gestionar períodos académicos.
3. Personalizar la apariencia.

### Práctica

1. **Configurar la institución:**
   - Nombre: "Instituto Tecnológico de Matemáticas"
   - Color primario: `#004AC6`
   - Color secundario: `#006C49`

2. **Crear períodos académicos:**
   - Crea "Primer Semestre 2025" (ene-jun) como período activo.
   - Crea "Segundo Semestre 2025" (jul-dic) como período inactivo.

3. **Actualizar configuración:**
   - Modifica el nombre de la institución.
   - Activa las notificaciones por correo para nuevas lecciones.

### Recursos de la API

```
GET  /api/v1/admin/config          → Ver configuración
PUT  /api/v1/admin/config          → Actualizar configuración
GET  /api/v1/admin/periods         → Listar períodos
POST /api/v1/admin/periods         → Crear período
PUT  /api/v1/admin/periods/{id}    → Editar período
DELETE /api/v1/admin/periods/{id}  → Eliminar período
```

---

## Módulo 3: Gestión de Backups (30 minutos)

### Temas

1. Crear copias de seguridad.
2. Verificar el último backup.
3. Descargar backups.

### Práctica

1. **Crear un backup:**
   - Genera una copia de la base de datos.
   - Verifica que se creó correctamente (fecha, tamaño).

2. **Descargar el backup:**
   - Descarga el archivo `.sql` generado.
   - Verifica que es un archivo válido.

3. **Plan de backups:**
   - Configura la frecuencia de backups en la configuración del sistema.

### Recursos de la API

```
POST /api/v1/admin/backup              → Crear backup
GET  /api/v1/admin/backup/last         → Último backup
GET  /api/v1/admin/backup/download/{filename} → Descargar
```

---

## Módulo 4: Monitoreo del Sistema (30 minutos)

### Temas

1. Interpretar el dashboard de administrador.
2. Revisar actividad reciente.
3. Identificar problemas comunes.

### Práctica

1. **Revisar el dashboard:**
   - Verifica el total de usuarios por rol.
   - Revisa las lecciones y evaluaciones publicadas.
   - Identifica el período académico activo.

2. **Monitorear actividad:**
   - Revisa los últimos 10 usuarios registrados.
   - Identifica si hay usuarios inactivos que necesiten activación.

3. **Health check:**
   - Verifica que la API esté funcionando correctamente.

### Recursos de la API

```
GET /api/v1/dashboard/admin    → Dashboard admin
GET /api/v1/health             → Health check de la API
GET /api/v1/admin/config       → Configuración actual
```

---

## Ejercicios Prácticos

### Ejercicio 1: Configuración inicial
Configura la plataforma para una nueva institución:
1. Actualiza el nombre y colores.
2. Crea el período académico actual.
3. Importa 10 usuarios desde CSV.
4. Crea 2 docentes manualmente.

### Ejercicio 2: Mantenimiento
Realiza un ciclo completo de mantenimiento:
1. Crea un backup de la base de datos.
2. Revisa el estado de los usuarios (activos/inactivos).
3. Desactiva 2 usuarios inactivos.
4. Exporta la lista de usuarios actualizada.

### Ejercicio 3: Resolución de problemas
Simula y resuelve los siguientes escenarios:
1. Un docente reporta que no puede publicar lecciones — verifica su estado.
2. Un estudiante no puede iniciar sesión — verifica que esté activo.
3. Se necesita restaurar datos — descarga el backup más reciente.

---

## Checklist de Certificación

- [ ] Puedes crear, editar y eliminar usuarios.
- [ ] Puedes importar usuarios desde CSV sin errores.
- [ ] Puedes configurar la institución (nombre, colores, logo).
- [ ] Puedes crear y gestionar períodos académicos.
- [ ] Puedes crear y descargar backups.
- [ ] Puedes interpretar el dashboard de administrador.
- [ ] Conoces las medidas de seguridad del sistema.
