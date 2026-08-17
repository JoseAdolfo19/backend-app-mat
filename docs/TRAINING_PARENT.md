# KawsayMath — Programa de Capacitación para Padres de Familia

---

## Objetivos

Al finalizar este programa, el padre de familia será capaz de:

1. Acceder a la plataforma y navegar por ella.
2. Ver el progreso académico de su hijo.
3. Interpretar los reportes de rendimiento.
4. Comprender cómo comunicarse con los docentes.

**Duración estimada:** 1.5 horas

**Prerrequisitos:** Cuenta de padre creada y vinculada a un estudiante por el administrador.

---

## Módulo 1: Acceso a la Plataforma (20 minutos)

### Temas

1. Iniciar sesión en KawsayMath.
2. Conocer el panel principal.
3. Cerrar sesión de forma segura.

### Práctica

1. Abre la aplicación KawsayMath en tu navegador.
2. Ingresa tu correo y contraseña.
3. Haz clic en **Iniciar Sesión**.
4. Verás la lista de tus hijos registrados.
5. Haz clic en el nombre de tu hijo para ver su progreso.

### Datos importantes

- Tu cuenta es creada por el administrador de la institución.
- Si no puedes iniciar sesión, contacta al administrador.
- Si olvidaste tu contraseña, usa la opción de recuperación.

### API

```
POST /api/v1/auth/login           → Iniciar sesión
GET  /api/v1/parent/children      → Ver hijos registrados
POST /api/v1/user/logout          → Cerrar sesión
```

---

## Módulo 2: Ver el Progreso del Hijo (30 minutos)

### Temas

1. Interpretar el resumen de progreso.
2. Revisar lecciones en curso y completadas.
3. Ver resultados de evaluaciones.

### Práctica

1. Selecciona a tu hijo en la lista.
2. Revisa las **estadísticas generales**:
   - Lecciones completadas vs. disponibles.
   - Puntaje promedio.
   - Racha de actividad.
   - Tiempo total de estudio.
3. Revisa las **lecciones en curso**:
   - ¿Qué está aprendiendo tu hijo?
   - ¿Cuánto ha avanzado en cada una?
4. Revisa las **evaluaciones recientes**:
   - ¿Qué evaluaciones ha completado?
   - ¿Cuáles han sido sus puntajes?

### API

```
GET /api/v1/parent/children/{studentId}/progress
```

### Datos que verás

| Campo | Descripción |
|-------|-------------|
| Lecciones en curso | Lecciones que tu hijo está realizando |
| Lecciones completadas | Lecciones terminadas con fecha |
| Evaluaciones recientes | Últimas 10 evaluaciones con puntaje |
| Tasa de finalización | Porcentaje de lecciones completadas |
| Promedio general | Promedio de todas las evaluaciones |
| Racha | Días consecutivos de actividad |

---

## Módulo 3: Interpretar Reportes (25 minutos)

### Temas

1. Entender el reporte detallado del hijo.
2. Conocer la escala de calificaciones.
3. Identificar fortalezas y debilidades.

### Práctica

1. Accede al reporte detallado de tu hijo.
2. Revisa el **resumen general**:
   - Total de evaluaciones realizadas.
   - Tasa de aprobación.
   - Mejor y peor puntaje.
3. Revisa el **rendimiento por materia**:
   - ¿En qué materias tiene mejor desempeño?
   - ¿En cuáles necesita mejorar?
4. Revisa las **recomendaciones**:
   - Lee las sugerencias personalizadas.

### Escala de calificaciones

| Puntaje | Significado | Acción sugerida |
|---------|-------------|-----------------|
| 18 - 20 | Excelente | Felicita a tu hijo y motívalo a continuar |
| 15 - 17 | Bueno | Reconoce su esfuerzo, puede mejorar más |
| 12 - 14 | Suficiente | Anímale a reforzar esa materia |
| 0 - 11 | Necesita mejorar | Habla con el docente para buscar apoyo |

### API

```
GET /api/v1/parent/children/{studentId}/report
```

---

## Módulo 4: Comunicación con Docentes (15 minutos)

### Temas

1. Cómo contactar al docente de tu hijo.
2. Información disponible para la comunicación.
3. Estrategias para apoyar el aprendizaje.

### Práctica

1. Identifica el docente de matemáticas de tu hijo.
2. Revisa qué lecciones y evaluaciones tiene el docente.
3. Prepara preguntas para una reunión docente-padre.

### Consejos para la comunicación

1. **Sé específico**: Menciona las lecciones o evaluaciones que te preocupan.
2. **Comparte datos**: Usa el reporte como punto de partida para la conversación.
3. **Sé constructivo**: Enfócate en cómo mejorar, no solo en los problemas.
4. **Sigue de cerca**: Revisa la plataforma al menos una vez por semana.

### Información disponible en la plataforma

- Lecciones que el docente ha publicado.
- Evaluaciones con sus puntajes.
- Fortalezas y debilidades identificadas.
- Tiempo de estudio del estudiante.
- Racha de actividad.

---

## Guía Rápida de Referencia

### Acciones frecuentes

| Acción | Cómo hacerlo |
|--------|--------------|
| Ver a mis hijos | Iniciar sesión > Panel principal |
| Ver progreso | Clic en el nombre del hijo |
| Ver reporte | Dentro del perfil del hijo > Reporte |
| Ver evaluaciones | Dentro del progreso > Evaluaciones recientes |
| Cerrar sesión | Avatar > Cerrar Sesión |

### Datos de contacto del administrador

Si tienes problemas con tu cuenta:
- Contacta al administrador de tu institución.
- El administrador puede crear, activar o desactivar cuentas.
- Si no puedes acceder, verifica que tu cuenta esté activa.

### Preguntas frecuentes

**¿Puedo ver a mis hijos si tengo más de uno?**
Sí, todos tus hijos vinculados aparecen en el panel principal.

**¿Qué hago si mi hijo no aparece?**
Contacta al administrador para que vincule la cuenta de tu hijo a la tuya.

**¿Con qué frecuencia debo revisar?**
Se recomienda al menos una vez por semana para mantener el seguimiento.

**¿Puedo acceder desde el celular?**
Sí, KawsayMath es responsive y funciona desde cualquier dispositivo.

**¿Puedo comunicarme directamente con el docente?**
La plataforma no incluye mensajería directa, pero puedes usar los datos del reporte para comunicarte por otros medios (correo, reuniones presenciales).
