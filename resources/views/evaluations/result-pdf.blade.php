<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resultado de evaluación</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 22px; }
        .summary { margin: 16px 0 22px; }
        .summary p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 7px; text-align: left; vertical-align: top; }
        th { background: #f4f4f4; }
        .correct { color: #15803d; }
        .partial { color: #a16207; }
        .incorrect { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>{{ $evaluation->title }}</h1>
    <div class="summary">
        <p><strong>Estudiante:</strong> {{ $student->full_name }}</p>
        <p><strong>Correo:</strong> {{ $student->email }}</p>
        <p><strong>Docente:</strong> {{ $evaluation->lesson?->course?->teacher?->full_name ?? $evaluation->teacher?->full_name ?? 'No asignado' }}</p>
        <p><strong>Curso:</strong> {{ $evaluation->lesson?->course?->name ?? $evaluation->lesson?->title ?? 'No asignado' }}</p>
        <p><strong>Salón:</strong> {{ $evaluation->lesson?->course?->salon?->display_name ?? 'No asignado' }}</p>
        <p><strong>Colegio:</strong> {{ \App\Models\InstitutionConfig::first()?->institution_name ?? $student->institution ?? 'No especificado' }}</p>
        <p><strong>Puntaje:</strong> {{ number_format($result->score, 1) }}/20</p>
        <p><strong>Respuestas correctas:</strong> {{ $result->correct_answers }}/{{ $result->total_questions }}</p>
        <p><strong>Hora de inicio:</strong> {{ $result->started_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</p>
        <p><strong>Hora de fin:</strong> {{ $result->completed_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</p>
        <p><strong>Duración:</strong> {{ sprintf('%02d:%02d:%02d', intdiv((int) $result->time_taken, 3600), intdiv((int) $result->time_taken % 3600, 60), (int) $result->time_taken % 60) }}</p>
        <p><strong>Intento:</strong> {{ $result->attempt_number }}</p>
        <p><strong>Cambios de pestaña:</strong> {{ $result->tab_switch_count }}</p>
    </div>

    <h2>Detalle de respuestas</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Pregunta</th>
                <th>Tu respuesta</th>
                <th>Respuesta correcta</th>
                <th>Resultado</th>
                <th>Puntos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($result->studentAnswers as $index => $answer)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $answer->question?->question_text ?? '' }}</td>
                    <td>{{ $answer->question ? \App\Support\EvaluationAnswerFormatter::submittedAnswer($answer->question, $answer->answer) : $answer->answer }}</td>
                    <td>{{ $answer->question ? \App\Support\EvaluationAnswerFormatter::correctAnswer($answer->question) : '' }}</td>
                    <td class="{{ $answer->is_correct ? 'correct' : ((float) $answer->points_earned > 0 ? 'partial' : 'incorrect') }}">
                        {{ $answer->is_correct ? 'Correcta' : ((float) $answer->points_earned > 0 ? 'Parcialmente correcta' : 'Incorrecta') }}
                    </td>
                    <td>{{ number_format((float) $answer->points_earned, 2) }} / {{ number_format((float) ($answer->question?->points ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
