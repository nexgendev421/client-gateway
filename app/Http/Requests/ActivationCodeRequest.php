<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'expires_at' => ['required', 'date', 'after:now'],
            'max_uses' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'intended_device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
