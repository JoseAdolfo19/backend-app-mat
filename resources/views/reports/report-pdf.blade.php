<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Rendimiento</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.subtitle { color: #666; margin-top: 0; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f4f4f4; }
        .summary { display: table; width: 100%; margin-bottom: 20px; }
        .summary-item { display: table-cell; width: 25%; padding: 8px; }
        .summary-label { font-size: 10px; color: #666; }
        .summary-value { font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Reporte de Rendimiento</h1>
    <p class="subtitle">Período: {{ $period ?? 'current' }} — Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="summary">
        <div class="summary-item">
            <div class="summary-label">Promedio general</div>
            <div class="summary-value">{{ number_format($summary['average'] ?? 0, 1) }}/20</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Máximo</div>
            <div class="summary-value">{{ number_format($summary['max'] ?? 0, 1) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Mínimo</div>
            <div class="summary-value">{{ number_format($summary['min'] ?? 0, 1) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total evaluaciones</div>
            <div class="summary-value">{{ $summary['total'] ?? 0 }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Calificación literal</div>
            <div class="summary-value">
                @php
                    $avg = $summary['average'] ?? 0;
                    echo $avg >= 18 ? 'AD' : ($avg >= 15 ? 'A' : ($avg >= 12 ? 'B' : 'C'));
                @endphp
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Estudiante</th>
                <th>Email</th>
                <th>Evaluación</th>
                <th>Calificación</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->user->full_name ?? 'Sin nombre' }}</td>
                    <td>{{ $row->user->email ?? '—' }}</td>
                    <td>{{ $row->evaluation->title ?? 'Sin título' }}</td>
                    <td>{{ number_format($row->score ?? 0, 1) }}/20</td>
                    <td>{{ optional($row->created_at)->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No hay datos para este período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>