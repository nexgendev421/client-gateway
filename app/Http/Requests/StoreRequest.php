<?php

namespace App\Http\Requests;

use App\Enums\StoreStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'store_code' => ['required', 'string', 'max:255', Rule::unique('stores', 'store_code')->ignore($this->route('store'))],
            'endpoint_url' => ['required', 'url', 'max:2048'],
            'status' => ['sometimes', Rule::enum(StoreStatus::class)],
            'max_devices' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
