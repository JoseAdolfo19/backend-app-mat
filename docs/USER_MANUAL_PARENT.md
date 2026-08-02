# MathFlow — Manual del Padre de Familia

## 1. Inicio de Sesión

1. Abre la aplicación MathFlow en tu navegador o dispositivo móvil.
2. Ingresa tu correo electrónico y contraseña.
3. Haz clic en **Iniciar Sesión**.
4. Serás redirigido al panel principal donde verás tus hijos registrados.

> Tu cuenta debe ser creada por un administrador con el rol de "Padre". Si no tienes acceso, contacta al administrador de tu institución.

**API:**
```
POST /api/v1/auth/login
Body: { "email": "padre@email.com", "password": "tu_password" }
```

---

## 2. Ver Hijos Registrados

Al iniciar sesión, verás la lista de tus hijos vinculados a tu cuenta:

1. Cada hijo se muestra con su **nombre** e **información básica**.
2. Haz clic en el nombre de tu hijo para ver su progreso detallado.

**API:**
```
GET /api/v1/parent/children
Headers: Authorization: Bearer <token>
```

> Si tus hijos no aparecen, contacta al administrador para que vincule las cuentas.

---

## 3. Progreso del Hijo

### 3.1 Resumen general

Al seleccionar a un hijo, verás:

- **Estadísticas generales**:
  - Total de lecciones en progreso.
  - Lecciones completadas.
  - Tasa de finalización (%).
  - Puntaje promedio.
  - Racha de actividad actual (días consecutivos).
  - Tiempo total de estudio.

- **Lecciones en curso**: Lecciones que tu hijo está realizando actualmente.
- **Lecciones completadas**: Historial de lecciones finalizadas, con fecha de completado.
- **Últimas evaluaciones**: Los 10 resultados más recientes de evaluaciones.

**API:**
```
GET /api/v1/parent/children/{studentId}/progress
```

### 3.2 Lecciones

Revisa qué lecciones está cursando tu hijo:
- **En curso**: Lecciones que ha empezado pero no terminado, con su porcentaje de avance.
- **Completadas**: Lecciones terminadas con la fecha de finalización.

### 3.3 Evaluaciones

Consulta las evaluaciones que tu hijo ha realizado:
- **Título** de la evaluación.
- **Puntaje** obtenido (escala 0-20).
- **Correctas** vs. total de preguntas.
- **Fecha** de completado.

---

## 4. Reporte del Hijo

El reporte detallado proporciona un análisis completo del rendimiento académico:

### 4.1 Resumen

| Métrica | Descripción |
|---------|-------------|
| Total de evaluaciones | Cuántas evaluaciones ha completado |
| Evaluaciones aprobadas | Cuántas obtuvieron nota >= 12 |
| Tasa de aprobación | Porcentaje de evaluaciones aprobadas |
| Puntaje promedio | Promedio general de todas las evaluaciones |
| Mejor puntaje | La nota más alta obtenida |
| Peor puntaje | La nota más baja obtenida |
| Lecciones completadas | Total de lecciones finalizadas |
| Tasa de finalización | Porcentaje de lecciones completadas vs. disponibles |
| Tiempo total de estudio | Tiempo total invertido en la plataforma (minutos) |

### 4.2 Rendimiento por materia

Se muestra el rendimiento agrupado por tipo/tema de evaluación:
- Promedio por materia.
- Número de intentos.
- Mejor puntaje por materia.

### 4.3 Detalle de evaluaciones

Lista completa de todas las evaluaciones con:
- Título de la evaluación.
- Puntaje obtenido.
- Puntaje máximo posible.
- Respuestas correctas vs. total.
- Fecha de completado.

**API:**
```
GET /api/v1/parent/children/{studentId}/report
```

---

## 5. Evolución de Competencias

El reporte incluye un análisis de fortalezas y debilidades basado en los resultados de las evaluaciones:

- **Fortalezas**: Materias o tipos de evaluación donde tu hijo obtiene un promedio >= 15.
- **Debilidades**: Materias o tipos donde el promedio es < 12.
- **Recomendaciones**: Sugerencias personalizadas para mejorar en las áreas débiles.

### Cómo interpretar el reporte

| Puntaje | Significado |
|---------|-------------|
| 18 - 20 | Excelente |
| 15 - 17 | Bueno |
| 12 - 14 | Suficiente |
| 0 - 11 | Necesita mejorar |

### Consejos para padres

1. **Revisa el reporte regularmente** para estar al tanto del progreso de tu hijo.
2. **Identifica las áreas débiles** y busca apoyo adicional en esas materias.
3. **Celebra los logros** — las insignias y rachas son motivadores importantes.
4. **Mantén comunicación** con los docentes si notas dificultades persistentes.

---

## 6. Cerrar Sesión

1. Haz clic en tu avatar en la esquina superior derecha.
2. Selecciona **Cerrar Sesión**.

**API:**
```
POST /api/v1/user/logout
```
