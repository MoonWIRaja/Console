<?php

namespace Pterodactyl\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_token' => 'nullable|string',
            'pin' => 'required|digits:6',
            'password' => 'required|string|confirmed|min:8',
        ];
    }
}
