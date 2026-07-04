<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:student,teacher',
            'academic_level' => 'required_if:role,student|in:basic,intermediate,advanced',
            'institution' => 'nullable|string|max:255',
            'grade' => 'nullable|string|max:50',
            'department' => 'required_if:role,teacher|string|max:255',
            'specialization' => 'required_if:role,teacher|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'El nombre completo es obligatorio',
            'email.required' => 'El correo electrónico es obligatorio',
            'email.unique' => 'El correo electrónico ya está registrado',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'role.in' => 'El rol debe ser estudiante o docente'
        ];
    }
}