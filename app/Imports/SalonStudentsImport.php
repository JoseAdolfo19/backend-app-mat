<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Role;
use App\Models\Salon;
use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SalonStudentsImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];
    public int $imported = 0;

    public function __construct(
        private Salon $salon,
        private ?string $byUserId = null,
        private string $defaultPassword = ''
    ) {}

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        $studentRoleId = Role::where('name', Role::STUDENT)->first()?->id;
        if (!$studentRoleId) {
            return;
        }

        $courseIds = $this->salon->courses()->pluck('id');

        $rowNumber = 1;
        foreach ($rows as $row) {
            $rowNumber++;

            $dni = trim((string) ($row['dni'] ?? ''));
            $fullName = trim((string) ($row['full_name'] ?? $row['nombre'] ?? ''));
            $email = trim((string) ($row['email'] ?? $row['correo'] ?? ''));
            $password = trim((string) ($row['password'] ?? $row['contraseña'] ?? ''));

            if ($dni === '' && $fullName === '' && $email === '') {
                continue;
            }

            if ($dni === '' || $fullName === '' || $email === '') {
                $this->errors[] = "Fila $rowNumber: datos incompletos (DNI, nombre y correo son obligatorios).";
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Fila $rowNumber: correo inválido ($email).";
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $this->errors[] = "Fila $rowNumber: el correo ya existe ($email).";
                continue;
            }

            if (User::where('dni', $dni)->exists()) {
                $this->errors[] = "Fila $rowNumber: el DNI ya existe ($dni).";
                continue;
            }

            $student = User::create([
                'id' => Str::uuid(),
                'dni' => $dni,
                'full_name' => $fullName,
                'email' => $email,
                'password' => Hash::make($password !== '' ? $password : $this->defaultPassword),
                'role_id' => $studentRoleId,
                'salon_id' => $this->salon->id,
                'grade' => $this->salon->grade,
                'is_active' => true,
                'institution' => $this->salon->display_name,
                'provider' => 'email',
            ]);

            foreach ($courseIds as $courseId) {
                Enrollment::firstOrCreate(
                    ['course_id' => $courseId, 'student_id' => $student->id],
                    ['enrolled_by' => $this->byUserId]
                );
            }

            $this->imported++;
        }
    }
}