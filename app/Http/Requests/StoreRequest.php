<?php

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Enums\StoreStatus;
use App\Models\Customer;
use App\Models\Store;
use App\Rules\SafePosEndpoint;
use App\Services\PosEndpointGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $store = $this->route('store');

        return $this->user()?->can($store ? 'update' : 'create', $store ?? Store::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('store_code')) {
            $this->merge(['store_code' => Str::upper(trim((string) $this->input('store_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required', 'integer', 'exists:customers,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $customer = Customer::query()->find($value);
                    $store = $this->route('store');
                    $sameArchivedCustomer = $store instanceof Store && (int) $store->customer_id === (int) $value;

                    if ($customer?->status === CustomerStatus::Archived && ! $sameArchivedCustomer) {
                        $fail('Archived customers cannot be assigned to a store.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'store_code' => [
                'required', 'string', 'min:3', 'max:20', 'regex:/^[A-Z0-9]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $query = Store::query()->whereRaw('LOWER(store_code) = ?', [Str::lower((string) $value)]);
                    $store = $this->route('store');
                    if ($store instanceof Store) {
                        $query->where('id', '!=', $store->getKey());
                    }
                    if ($query->exists()) {
                        $fail('The store code has already been taken.');
                    }
                },
            ],
            'endpoint_url' => ['required', 'string', new SafePosEndpoint(app(PosEndpointGuard::class))],
            'status' => ['sometimes', 'required', 'in:'.implode(',', array_column(StoreStatus::cases(), 'value'))],
            'max_devices' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
