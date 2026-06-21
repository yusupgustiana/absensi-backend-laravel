<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required'
            ],
            'new_password' => [
                'required',
                'min:5',
                'confirmed'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.confirmed' =>
                'Konfirmasi password tidak sesuai.'
        ];
    }
}