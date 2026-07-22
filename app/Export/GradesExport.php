<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class GradesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Estudiante', 'Email', 'Evaluación', 'Calificación', 'Fecha'];
    }

    public function map($row): array
    {
        return [
            $row->user->full_name ?? 'Sin nombre',
            $row->user->email ?? '',
            $row->evaluation->title ?? 'Sin título',
            number_format($row->score ?? 0, 1),
            optional($row->created_at)->format('d/m/Y'),
        ];
    }
}