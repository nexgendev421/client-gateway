<?php

namespace App\Http\Requests;

use App\Enums\LicenceStatus;
use App\Models\Licence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $licence = $this->route('licence');

        return $user !== null && ($licence instanceof Licence
            ? $user->can('update', $licence)
            : $user->can('create', Licence::class));
    }

    public function rules(): array
    {
        $licence = $this->route('licence');
        $rules = [
            'plan' => ['required', 'string', 'max:100'],
            'plan_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/D'],
            'starts_at' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:starts_at'],
            'grace_until' => ['nullable', 'date', 'after:valid_until'],
            'max_stores' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_devices' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'metadata_json' => ['nullable', 'string', 'json', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($licence === null) {
            $rules['customer_id'] = ['required', 'integer', 'exists:customers,id', Rule::unique('licences', 'customer_id')];
            $rules['status'] = ['nullable', Rule::in(array_column(LicenceStatus::cases(), 'value'))];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->input('metadata_json');
            if ($raw !== null && $raw !== '') {
                if (! is_string($raw)) {
                    $validator->errors()->add('metadata_json', 'Features must be a JSON object or array.');

                    return;
                }
                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    $validator->errors()->add('metadata_json', 'Features must be a JSON object or array.');
                } elseif ($this->containsSensitiveKey($decoded)) {
                    $validator->errors()->add('metadata_json', 'Features cannot contain credentials, secrets, or personal access data.');
                }
            }
        });
    }

    private function containsSensitiveKey(array $values): bool
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/password|secret|token|credential|authorization|cookie|pin|private.?key/i', $key)) {
                return true;
            }
            if (is_array($value) && $this->containsSensitiveKey($value)) {
                return true;
            }
        }

        return false;
    }
}
