<?php

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class ActivationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $store = $this->route('store');

        return $store instanceof Store && ($this->user()?->can('generateActivationCode', $store) ?? false);
    }

    public function rules(): array
    {
        return [
            'expires_in_hours' => ['required', 'integer', 'in:1,24,168'],
            'max_uses' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'intended_device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
