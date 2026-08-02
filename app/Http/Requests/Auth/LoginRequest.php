<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8'
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('validation_email_required'),
            'email.email' => __('validation_email_invalid'),
            'password.required' => __('validation_password_required'),
            'password.min' => __('validation_password_min'),
        ];
    }
}