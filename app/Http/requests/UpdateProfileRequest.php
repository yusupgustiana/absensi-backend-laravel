<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => 'nullable|string|max:100',
            'username' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }
}