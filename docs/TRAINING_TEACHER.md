# KawsayMath — Programa de Capacitación para Docentes

---

## Objetivos de la Capacitación

Al finalizar este programa, el docente será capaz de:

1. Navegar la plataforma y gestionar su perfil.
2. Crear, editar y publicar lecciones de matemáticas.
3. Crear evaluaciones con diferentes tipos de preguntas.
4. Monitorear el progreso de sus estudiantes.
5. Generar y exportar reportes de rendimiento.

**Duración estimada:** 4 horas (puede completarse en sesiones separadas)

**Prerrequisitos:** Cuenta de docente creada por el administrador.

---

## Módulo 1: Navegación y Perfil (30 minutos)

### Temas

1. Inicio de sesión y primeros pasos.
2. Explorar el dashboard docente.
3. Gestionar mi perfil.

### Práctica

- Inicia sesión con tus credenciales.
- Identifica cada sección del dashboard: estudiantes, lecciones, evaluaciones.
- Actualiza tu nombre y fotografía de perfil.
- Cambia tu contraseña.

### Recursos de la API

```
POST /api/v1/auth/login           → Iniciar sesión
GET  /api/v1/user/profile         → Ver perfil
PUT  /api/v1/user/profile         → Actualizar perfil
PUT  /api/v1/user/change-password → Cambiar contraseña
```

---

## Módulo 2: Gestionar Lecciones (60 minutos)

### Temas

1. Crear una lección paso a paso.
2. Formatear contenido con HTML/Markdown.
3. Agregar recursos (videos, PDFs, enlaces).
4. Publicar, despublicar y duplicar lecciones.

### Práctica

1. **Crear tu primera lección:**
   - Título: "Introducción a los Decimales"
   - Unidad: "Números"
   - Dificultad: Básica
   - Contenido: Escribe al menos 3 párrafos explicativos
   - Tiempo estimado: 30 minutos

2. **Agregar recursos:**
   - Agrega un video de YouTube como recurso.
   - Agrega un enlace de referencia.

3. **Publicar la lección:**
   - Verifica que tenga contenido.
   - Publica y confirma que los estudiantes la reciben.

4. **Duplicar la lección:**
   - Duplica la lección y modifica el título.

### Recursos de la API

```
POST   /api/v1/lessons             → Crear lección
PUT    /api/v1/lessons/{id}        → Editar lección
POST   /api/v1/lessons/{id}/publish    → Publicar
POST   /api/v1/lessons/{id}/unpublish  → Despublicar
POST   /api/v1/lessons/{id}/duplicate  → Duplicar
POST   /api/v1/lessons/{id}/resources  → Agregar recurso
DELETE /api/v1/lessons/{id}/resources/{resourceId} → Eliminar recurso
GET    /api/v1/lessons/{id}/stats      → Ver estadísticas
DELETE /api/v1/lessons/{id}            → Eliminar lección
```

---

## Módulo 3: Gestionar Evaluaciones (60 minutos)

### Temas

1. Crear una evaluación y configurar sus opciones.
2. Agregar diferentes tipos de preguntas.
3. Publicar la evaluación.
4. Revisar resultados y estadísticas.

### Práctica

1. **Crear una evaluación:**
   - Título: "Quiz de Decimales"
   - Tipo: Quiz
   - Dificultad: Básica
   - Tiempo límite: 20 minutos
   - Máximo de intentos: 3
   - Corrección automática: Sí

2. **Agregar preguntas:**
   - Crea 3 preguntas de opción múltiple.
   - Crea 2 preguntas de completar espacio en blanco.
   - Asigna 2 puntos a cada pregunta.
   - Agrega explicaciones a cada respuesta.

3. **Publicar la evaluación:**
   - Verifica que tenga preguntas.
   - Publica.

4. **Ver resultados:**
   - Cuando un estudiante envíe respuestas, revisa los resultados.
   - Consulta las estadísticas: promedio, tasa de aprobación.

### Recursos de la API

```
POST   /api/v1/evaluations                    → Crear evaluación
PUT    /api/v1/evaluations/{id}               → Editar evaluación
POST   /api/v1/evaluations/{id}/publish       → Publicar
POST   /api/v1/evaluations/{id}/unpublish     → Despublicar
POST   /api/v1/evaluations/{id}/duplicate     → Duplicar
POST   /api/v1/evaluations/{id}/questions     → Agregar pregunta
PUT    /api/v1/evaluations/questions/{id}     → Editar pregunta
DELETE /api/v1/evaluations/questions/{id}     → Eliminar pregunta
GET    /api/v1/evaluations/{id}/results       → Ver resultados
GET    /api/v1/evaluations/{id}/stats         → Ver estadísticas
GET    /api/v1/evaluations/{id}/result/{uid}  → Resultado de un estudiante
```

---

## Módulo 4: Monitorear Progreso (40 minutos)

### Temas

1. Interpretar el dashboard docente.
2. Ver progreso individual de estudiantes.
3. Analizar estadísticas de lecciones.

### Práctica

1. **Revisa el dashboard:**
   - Identifica el total de envíos pendientes.
   - Revisa los estudiantes más recientes.

2. **Reporte de un estudiante:**
   - Selecciona un estudiante.
   - Analiza sus lecciones completadas y promedio.
   - Identifica fortalezas y debilidades.

3. **Estadísticas de una lección:**
   - Selecciona una de tus lecciones.
   - Revisa cuántos estudiantes la han visto, iniciado y completado.

### Recursos de la API

```
GET /api/v1/dashboard/teacher          → Dashboard docente
GET /api/v1/reports/student/{userId}   → Reporte de estudiante
GET /api/v1/lessons/{id}/stats         → Estadísticas de lección
```

---

## Módulo 5: Generar Reportes (30 minutos)

### Temas

1. Reporte de rendimiento general.
2. Reporte de calificaciones.
3. Exportar en PDF y Excel.

### Práctica

1. **Reporte de rendimiento:**
   - Selecciona el período "mes actual".
   - Revisa el promedio general y tasa de aprobación.

2. **Reporte de calificaciones:**
   - Filtra por una evaluación específica.
   - Revisa la lista de calificaciones.

3. **Exportar:**
   - Exporta el reporte en PDF.
   - Exporta el reporte en Excel.

### Recursos de la API

```
GET /api/v1/reports/performance?period=month   → Rendimiento
GET /api/v1/reports/grades?evaluation_id=xxx   → Calificaciones
GET /api/v1/reports/export/pdf?period=month    → Exportar PDF
GET /api/v1/reports/export/excel?period=month  → Exportar Excel
```

---

## Ejercicios Prácticos

### Ejercicio 1: Lección completa
Crea una lección sobre "Ecuaciones de primer grado" con:
- Contenido de al menos 500 palabras.
- 2 recursos adjuntos.
- Tiempo estimado de 40 minutos.
- Publica la lección.

### Ejercicio 2: Evaluación completa
Crea una evaluación vinculada a la lección anterior con:
- 5 preguntas de opción múltiple.
- 3 preguntas de completar espacio en blanco.
- Tiempo límite de 25 minutos.
- Publica la evaluación.

### Ejercicio 3: Análisis de resultados
Cuando al menos 3 estudiantes completen la evaluación:
- Revisa el reporte de calificaciones.
- Identifica la pregunta con más errores.
- Exporta el reporte en PDF.

---

## Certificación

Para obtener la certificación de KawsayMath como docente, debes completar:

1. Los 5 módulos de capacitación.
2. Los 3 ejercicios prácticos.
3. Crear y publicar al menos 3 lecciones reales.
4. Crear y publicar al menos 2 evaluaciones reales.

Una vez completado, contacta al administrador para que active tu certificación en el sistema.
