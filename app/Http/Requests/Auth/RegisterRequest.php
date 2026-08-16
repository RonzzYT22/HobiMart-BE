<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    // request ini boleh dipakai siapa saja
    public function authorize(): bool
    {
        return true;
    }

    // aturan validasi untuk registrasi
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:phone', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required_without:email', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    // pesan error yang lebih ramah
    public function messages(): array
    {
        return [
            'email.required_without' => 'Either email or phone is required.',
            'phone.required_without' => 'Either email or phone is required.',
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This phone number is already registered.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
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
