<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    // request ini boleh dipakai siapa saja
    public function authorize(): bool
    {
        return true;
    }

    // aturan validasi untuk login
    public function rules(): array
    {
        return [
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ];
    }

    // pesan error
    public function messages(): array
    {
        return [
            'email.required_without' => 'Either email or phone is required.',
            'phone.required_without' => 'Either email or phone is required.',
            'password.required' => 'Password is required.',
        ];
    }

    // bersihkan nomor hp dari karakter selain angka
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\D/', '', $this->phone),
            ]);
        }
    }
}
