# KawsayMath — Manual del Docente

## 1. Inicio de Sesión

1. Abre la aplicación KawsayMath.
2. Ingresa tu correo electrónico y contraseña.
3. Haz clic en **Iniciar Sesión**.
4. Serás redirigido al **Dashboard Docente**.

> También puedes iniciar sesión con tu cuenta de Google si fue vinculada por el administrador.

**API:**
```
POST /api/v1/auth/login
Body: { "email": "docente@escuela.com", "password": "tu_password" }
```

---

## 2. Dashboard Docente

Tu dashboard muestra un resumen de tu actividad:

- **Total de estudiantes** en la plataforma.
- **Mis lecciones**: total y publicadas.
- **Mis evaluaciones**: total y publicadas.
- **Envíos recibidos**: total de respuestas de estudiantes.
- **Puntaje promedio**: promedio general de tus evaluaciones.
- **Revisiones pendientes**: evaluaciones sin revisar.
- **Estudiantes recientes**: los últimos 10 estudiantes activos.
- **Evaluaciones recientes**: las últimas 5 evaluaciones creadas.

**API:**
```
GET /api/v1/dashboard/teacher
Headers: Authorization: Bearer <token>
```

---

## 3. Gestionar Lecciones

### 3.1 Ver mis lecciones

1. Navega a **Lecciones**.
2. Se muestra la lista de todas tus lecciones.
3. Filtra por **dificultad**, **unidad**, **tema** o usa la **búsqueda**.

**API:**
```
GET /api/v1/lessons?difficulty=intermediate&search=fracciones
```

### 3.2 Crear una lección

1. Haz clic en **Crear Lección**.
2. Completa los campos:
   - **Título**: Nombre de la lección (obligatorio).
   - **Descripción**: Resumen breve de la lección.
   - **Contenido**: Desarrolla el contenido en formato HTML/Markdown (obligatorio).
   - **Unidad**: Ej. "Unidad 1", "Álgebra".
   - **Tema**: Ej. "Fracciones equivalentes".
   - **Dificultad**: Básica, Intermedia o Avanzada.
   - **Tiempo estimado**: Tiempo en minutos (ej. 45).
   - **Etiquetas**: Tags para facilitar la búsqueda.
3. Haz clic en **Guardar**. La lección se guarda como **borrador**.

**API:**
```
POST /api/v1/lessons
Body: {
  "title": "Introducción a las Fracciones",
  "description": "Aprende qué son las fracciones y cómo representarlas",
  "content": "<h2>¿Qué es una fracción?</h2><p>Una fracción representa...</p>",
  "unit": "Números",
  "topic": "Fracciones",
  "difficulty": "basic",
  "estimated_time": 30,
  "tags": ["fracciones", "números"]
}
```

### 3.3 Editar una lección

1. Selecciona la lección que deseas editar.
2. Modifica los campos necesarios.
3. Haz clic en **Actualizar**.

**API:**
```
PUT /api/v1/lessons/{id}
Body: { "title": "Introducción a las Fracciones (Actualizado)" }
```

### 3.4 Agregar recursos

1. Dentro de la lección, ve a la pestaña de **Recursos**.
2. Haz clic en **Agregar Recurso**.
3. Selecciona el tipo: PDF, Video, Imagen, Enlace o Audio.
4. Ingresa la URL y el título del recurso.

**API:**
```
POST /api/v1/lessons/{id}/resources
Body: {
  "type": "video",
  "url": "https://www.youtube.com/watch?v=ejemplo",
  "title": "Video explicativo de fracciones"
}
```

### 3.5 Publicar una lección

1. Revisa que la lección tenga contenido completo.
2. Haz clic en **Publicar**.
3. Los estudiantes recibirán una notificación de la nueva lección disponible.

**API:**
```
POST /api/v1/lessons/{id}/publish
```

### 3.6 Despublicar una lección

Si necesitas ocultar temporalmente una lección:
```
POST /api/v1/lessons/{id}/unpublish
```

### 3.7 Duplicar una lección

Para crear una copia de una lección existente:
```
POST /api/v1/lessons/{id}/duplicate
```

### 3.8 Ver estadísticas de una lección

Consulta cuántos estudiantes han visto, iniciado o completado la lección.

**API:**
```
GET /api/v1/lessons/{id}/stats
```

### 3.9 Eliminar una lección

> **Nota:** No se puede eliminar una lección que tenga evaluaciones asociadas.

```
DELETE /api/v1/lessons/{id}
```

---

## 4. Gestionar Evaluaciones

### 4.1 Ver mis evaluaciones

1. Navega a **Evaluaciones**.
2. Filtra por **tipo**, **dificultad**, **estado** (publicada/borrador) o usa la **búsqueda**.

**API:**
```
GET /api/v1/evaluations?type=quiz&status=draft
```

### 4.2 Crear una evaluación

1. Haz clic en **Crear Evaluación**.
2. Completa los campos:
   - **Título**: Nombre de la evaluación (obligatorio).
   - **Descripción**: Instrucciones adicionales.
   - **Lección asociada** (opcional): Vincula la evaluación a una lección.
   - **Tipo**: Examen, Quiz, Tarea o Práctica.
   - **Dificultad**: Básica, Intermedia o Avanzada.
   - **Límite de tiempo**: Tiempo en minutos.
   - **Fecha límite**: Fecha de entrega (opcional).
   - **Corrección automática**: Actívala para que se corrija solo.
   - **Randomizar preguntas**: Actívala para mezclar el orden.
   - **Máximo de intentos**: Número de veces que el estudiante puede intentar.
3. Haz clic en **Guardar**. La evaluación se guarda como **borrador**.

**API:**
```
POST /api/v1/evaluations
Body: {
  "title": "Quiz de Fracciones",
  "description": "Evalúa tus conocimientos sobre fracciones",
  "lesson_id": "uuid-de-la-leccion",
  "type": "quiz",
  "difficulty": "basic",
  "time_limit": 20,
  "due_date": "2025-02-01T23:59:00",
  "auto_correct": true,
  "max_attempts": 3
}
```

### 4.3 Agregar preguntas

1. Dentro de la evaluación, ve a **Preguntas**.
2. Haz clic en **Agregar Pregunta**.
3. Selecciona el tipo de pregunta:
   - **Opción múltiple**: Agrega las opciones y marca la correcta.
   - **Completar espacio en blanco**: Escribe la respuesta correcta.
   - **Arrastrar y soltar**: Configura los elementos a ordenar.
   - **Fórmula**: Pregunta con respuesta matemática.
4. Asigna los **puntos** (por defecto 1).
5. Agrega una **explicación** (opcional) que se mostrará después de responder.

**API:**
```
POST /api/v1/evaluations/{evaluationId}/questions
Body: {
  "type": "multiple_choice",
  "question_text": "¿Cuál es el resultado de 1/2 + 1/4?",
  "options": [
    { "label": "A", "value": "3/4" },
    { "label": "B", "value": "2/6" },
    { "label": "C", "value": "1/3" },
    { "label": "D", "value": "3/6" }
  ],
  "correct_answer": "3/4",
  "explanation": "1/2 = 2/4, entonces 2/4 + 1/4 = 3/4",
  "points": 2
}
```

### 4.4 Editar / Eliminar preguntas

**Editar:**
```
PUT /api/v1/evaluations/questions/{questionId}
Body: { "question_text": "Texto actualizado" }
```

**Eliminar:**
```
DELETE /api/v1/evaluations/questions/{questionId}
```

### 4.5 Publicar una evaluación

1. Verifica que la evaluación tenga al menos una pregunta.
2. Haz clic en **Publicar**.
3. Los estudiantes recibirán una notificación.

**API:**
```
POST /api/v1/evaluations/{id}/publish
```

### 4.6 Ver resultados

Consulta las respuestas de los estudiantes y estadísticas generales:
- Total de envíos.
- Puntaje promedio, máximo y mínimo.
- Tasa de aprobación (puntaje >= 12).
- Distribución de puntajes.

**API:**
```
GET /api/v1/evaluations/{id}/results
GET /api/v1/evaluations/{id}/stats
GET /api/v1/evaluations/{id}/result/{userId}
```

### 4.7 Duplicar una evaluación

Para reutilizar una evaluación con sus preguntas:
```
POST /api/v1/evaluations/{id}/duplicate
```

---

## 5. Ver Progreso de Estudiantes

### 5.1 Progreso por lección

Consulta el progreso de los estudiantes en una lección específica:
```
GET /api/v1/lessons/{id}/stats
```

### 5.2 Reporte individual de estudiante

1. Navega a **Reportes > Estudiante**.
2. Selecciona un estudiante.
3. Verás su progreso completo: lecciones, evaluaciones, fortalezas y debilidades.

**API:**
```
GET /api/v1/reports/student/{userId}
```

---

## 6. Reportes y Exportaciones

### 6.1 Reporte de rendimiento

Consulta el rendimiento general por período:
```
GET /api/v1/reports/performance?period=month
```

### 6.2 Reporte de calificaciones

Filtra por evaluación o estudiante:
```
GET /api/v1/reports/grades?evaluation_id=xxx
```

### 6.3 Exportar

- **PDF**: Descarga un documento con los reportes.
- **Excel**: Descarga una hoja de cálculo con los datos.

**API:**
```
GET /api/v1/reports/export/pdf?period=month
GET /api/v1/reports/export/excel?period=month
```

---

## 7. Notificaciones

Recibe notificaciones cuando:
- Un estudiante envía una evaluación.
- Se alcanzan hitos importantes (rachas, insignias).
- Hay actualizaciones del sistema.

Consulta tus notificaciones en el icono de campana en la esquina superior derecha.

**API:**
```
GET /api/v1/notifications
PUT /api/v1/notifications/read-all
```

---

## 8. Mi Perfil

1. Haz clic en tu avatar > **Mi Perfil**.
2. Puedes actualizar tu nombre, institución y otros datos.
3. Cambia tu contraseña desde **Configuración de Seguridad**.

**API:**
```
GET /api/v1/user/profile
PUT /api/v1/user/profile
Body: { "full_name": "Prof. María López" }
```

**Cambiar contraseña:**
```
PUT /api/v1/user/change-password
Body: { "current_password": "actual", "new_password": "nueva123", "new_password_confirmation": "nueva123" }
```
