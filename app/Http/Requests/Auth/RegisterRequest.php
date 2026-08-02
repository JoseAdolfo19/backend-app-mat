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
            'full_name.required' => __('validation_full_name_required'),
            'email.required' => __('validation_email_required'),
            'email.unique' => __('email_already_registered'),
            'password.required' => __('validation_password_required'),
            'password.min' => __('validation_password_min'),
            'password.confirmed' => __('validation_password_confirmed'),
            'role.in' => __('validation_role_invalid'),
        ];
    }
}