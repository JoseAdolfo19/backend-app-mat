# MathFlow — Manual del Estudiante

## 1. Registro e Inicio de Sesión

### 1.1 Crear una cuenta

1. Abre la aplicación MathFlow.
2. Haz clic en **Registrarse**.
3. Completa los campos:
   - **Nombre completo**: Tus nombre y apellidos.
   - **Correo electrónico**: Será tu usuario para iniciar sesión.
   - **Contraseña**: Mínimo 8 caracteres. Anótala en un lugar seguro.
   - **Institución** (opcional): Nombre de tu escuela o colegio.
   - **Grado** (opcional): Tu grado o nivel actual.
4. Haz clic en **Crear Cuenta**.

**API:**
```
POST /api/v1/auth/register
Body: {
  "full_name": "Carlos Pérez",
  "email": "carlos@estudiante.com",
  "password": "MiPassword123",
  "role": "student"
}
```

### 1.2 Iniciar sesión

1. Ingresa tu correo y contraseña.
2. Haz clic en **Iniciar Sesión**.

> Si tu institución usa Google, también puedes iniciar sesión con tu cuenta de Google.

### 1.3 ¿Olvidaste tu contraseña?

1. En la pantalla de login, haz clic en **¿Olvidaste tu contraseña?**
2. Ingresa tu correo electrónico.
3. Revisa tu bandeja de entrada y sigue las instrucciones.

---

## 2. Dashboard del Estudiante

Al iniciar sesión verás tu panel principal con:

- **Lecciones en curso**: Lecciones que empezaste pero no terminaste.
- **Lecciones completadas**: Las últimas lecciones que finalizaste.
- **Evaluaciones próximas**: Evaluaciones publicadas que aún no has realizado.
- **Últimas evaluaciones**: Tus resultados más recientes.
- **Estadísticas**:
  - Lecciones completadas vs. disponibles.
  - Promedio general.
  - Racha de actividad (días consecutivos).
  - Tiempo total de estudio.
  - Insignias obtenidas.

**API:**
```
GET /api/v1/dashboard/student
```

---

## 3. Lecciones

### 3.1 Navegar las lecciones

1. Haz clic en **Lecciones** en el menú lateral.
2. Se muestran todas las lecciones publicadas.
3. Usa los filtros para buscar por:
   - **Dificultad**: Básica, Intermedia, Avanzada.
   - **Unidad**: Ej. "Números", "Álgebra".
   - **Tema**: Ej. "Fracciones", "Ecuaciones".
   - **Búsqueda libre**: Por título o contenido.

**API:**
```
GET /api/v1/lessons?difficulty=basic&unit=Números
```

### 3.2 Completar una lección

1. Selecciona una lección de la lista.
2. Lee el contenido de la lección.
3. Tu progreso se guarda automáticamente al avanzar.
4. Al llegar al final, marca la lección como **completada**.
5. El sistema registrará tu tiempo invertido y actualizará tu racha.

**API:**
```
GET /api/v1/lessons/{id}
POST /api/v1/lessons/{id}/progress
Body: { "progress": 100, "time_spent": 120 }
```

### 3.3 Recursos de la lección

Algunas lecciones incluyen recursos adicionales como:
- Videos explicativos.
- Archivos PDF para descargar.
- Enlaces de referencia.
- Imágenes y diagramas.

**API:**
```
GET /api/v1/lessons/{id}/resources
```

---

## 4. Evaluaciones

### 4.1 Ver evaluaciones disponibles

1. Haz clic en **Evaluaciones** en el menú.
2. Se muestran las evaluaciones publicadas.
3. Puedes filtrar por tipo, dificultad o buscar por nombre.

> Las evaluaciones con fecha límite aparecen marcadas. Asegúrate de completarlas antes de que venzan.

**API:**
```
GET /api/v1/evaluations?available=true
```

### 4.2 Tomar una evaluación

1. Selecciona la evaluación que deseas tomar.
2. Lee las instrucciones: tiempo límite, número de intentos.
3. Haz clic en **Comenzar**.
4. Responde cada pregunta:
   - **Opción múltiple**: Selecciona una respuesta.
   - **Completar espacio en blanco**: Escribe tu respuesta.
   - **Fórmula**: Ingresa tu respuesta matemática.
5. Cuando termines, haz clic en **Enviar**.
6. Si la corrección es automática, verás tu puntaje inmediatamente.

**API:**
```
GET /api/v1/evaluations/{id}/questions
POST /api/v1/evaluations/{id}/submit
Body: {
  "answers": [
    { "question_id": "uuid-pregunta-1", "answer": "3/4" },
    { "question_id": "uuid-pregunta-2", "answer": "15" }
  ],
  "time_taken": 600
}
```

### 4.3 Ver tus resultados

1. Después de enviar, verás tu puntaje y respuestas correctas.
2. También puedes ver resultados anteriores en **Mis Evaluaciones**.
3. El puntaje es en escala de 0 a 20.
4. La nota mínima de aprobación es **12**.

**API:**
```
GET /api/v1/evaluations/{id}/results
```

---

## 5. Badges y Logros

MathFlow te recompensa con insignias por tu actividad:

| Insignia | Descripción | Cómo obtenerla |
|----------|-------------|----------------|
| Primera Lección | Completaste tu primera lección | Completa 1 lección |
| Maestro de Lecciones | Completaste 10 lecciones | Completa 10 lecciones |
| Puntuación Perfecta | Obtuviste 20/20 en una evaluación | Sacar puntaje perfecto |
| Racha de 7 Días | 7 días consecutivos de actividad | Estudia 7 días seguidos |
| Racha de 30 Días | 30 días consecutivos de actividad | Estudia 30 días seguidos |
| Genio Matemático | Promedio general >= 18 | Mantén un promedio alto |

1. Consulta tus insignias en **Mi Perfil > Logros**.
2. Recibirás una notificación cada vez que desbloquees una nueva insignia.

**API:**
```
GET /api/v1/progress/badges
```

---

## 6. Chat AI — Profesor Euler

MathFlow incluye un asistente de IA llamado **Profesor Euler** que te ayuda con matemáticas.

### 6.1 ¿Qué puede hacer Profesor Euler?

- Explicar conceptos matemáticos.
- Guiarte paso a paso para resolver problemas.
- Responder preguntas sobre álgebra, geometría, números, estadística y trigonometría.

### 6.2 ¿Cómo funciona?

1. Haz clic en el ícono de **Chat** (robot 🤖) en la esquina inferior.
2. Escribe tu pregunta o describe tu problema.
3. Profesor Euler te guiará con preguntas y explicaciones.

> **Importante:** Profesor Euler no te dará la respuesta directa. Te enseñará a resolverlo tú mismo. También solo puede ayudarte con temas de matemáticas.

**API:**
```
POST /api/v1/ai/chat
Body: { "message": "¿Cómo se resuelve una ecuación de segundo grado?" }
```

> Tienes un límite de 50 mensajes diarios con Profesor Euler.

---

## 7. Notificaciones

Recibe notificaciones cuando:
- Se publica una nueva lección.
- Se publica una nueva evaluación.
- Desbloqueas una insignia.
- Hay actualizaciones importantes.

1. Haz clic en el **icono de campana** para ver tus notificaciones.
2. Las notificaciones no leídas aparecen marcadas.
3. Puedes marcar todas como leídas o eliminar las que ya leíste.

**API:**
```
GET /api/v1/notifications
GET /api/v1/notifications/unread-count
PUT /api/v1/notifications/read-all
PUT /api/v1/notifications/{id}/read
DELETE /api/v1/notifications/{id}
```

---

## 8. Mi Perfil

### 8.1 Ver y editar tu perfil

1. Haz clic en tu avatar > **Mi Perfil**.
2. Puedes actualizar:
   - **Nombre completo**.
   - **Institución**.
   - **Grado**.
   - **Foto de perfil** (URL de imagen).

**API:**
```
GET /api/v1/user/profile
PUT /api/v1/user/profile
Body: { "full_name": "Carlos Pérez López", "grade": "6to" }
```

### 8.2 Cambiar contraseña

1. Ve a **Configuración > Seguridad**.
2. Ingresa tu contraseña actual y la nueva contraseña.
3. Haz clic en **Actualizar**.

> Se cerrarán todas tus sesiones activas excepto la actual.

**API:**
```
PUT /api/v1/user/change-password
Body: {
  "current_password": "contraseña_actual",
  "new_password": "nueva_contraseña",
  "new_password_confirmation": "nueva_contraseña"
}
```

### 8.3 Ver tus estadísticas

Consulta un resumen detallado de tu progreso:
- Lecciones completadas y en progreso.
- Evaluaciones realizadas.
- Promedio general.
- Tiempo total de estudio.
- Distribución de actividad reciente.

**API:**
```
GET /api/v1/progress/my-stats
```
