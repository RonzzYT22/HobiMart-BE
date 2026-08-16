<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    // hanya user yang sedang login yang boleh pakai request ini
    public function authorize(): bool
    {
        return true;
    }

    // aturan validasi untuk ubah profil
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $this->user()->id],
            'phone' => ['sometimes', 'string', 'max:20', 'unique:users,phone,' . $this->user()->id],
            'password' => ['sometimes', 'confirmed', Password::min(8)],
            'avatar' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
            'preferences' => ['sometimes', 'nullable', 'array'],
            'preferences.*' => ['nullable'],
        ];
    }

    // pesan error
    public function messages(): array
    {
        return [
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
