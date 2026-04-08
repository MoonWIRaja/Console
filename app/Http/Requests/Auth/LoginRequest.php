<?php

namespace Pterodactyl\Http\Requests\Auth;

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
            'user' => 'required|string|min:1',
            'password' => 'required|string',
            'website' => 'nullable|string|max:191',
            'company' => 'nullable|string|max:191',
            'form_rendered_at' => 'nullable|numeric',
            'cf-turnstile-response' => 'nullable|string',
        ];
    }
}
