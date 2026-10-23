<?php

namespace App\Http\Requests;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'public_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]+$/', 'unique:devices,public_id'],
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'os_version' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:100'],
        ];
    }
}
