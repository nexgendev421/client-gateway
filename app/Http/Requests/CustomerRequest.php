<?php

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $this->user()?->can($customer ? 'update' : 'create', $customer ?? Customer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => $this->isArchivedCustomer()
                ? ['prohibited']
                : ['sometimes', Rule::in([CustomerStatus::Active->value, CustomerStatus::Suspended->value])],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    private function isArchivedCustomer(): bool
    {
        return $this->route('customer') instanceof Customer
            && $this->route('customer')->status === CustomerStatus::Archived;
    }
}
