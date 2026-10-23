<?php

namespace App\Http\Requests;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'activation_code' => ['required', 'string', 'max:9', 'regex:/^\d{4}-?\d{4}$/'],
            'device_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]+$/'],
            'device_name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'app_version' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9.+_-]*$/'],
        ];
    }
}
