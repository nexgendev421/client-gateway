<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('device');
    }

    public function rules(): array
    {
        return [
            'app_version' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._+ -]*$/D'],
            'os_version' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._+ -]*$/D'],
            'platform' => ['required', 'string', Rule::in(['windows', 'android'])],
        ];
    }
}
