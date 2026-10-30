<?php

namespace App\Http\Requests;

use App\DeliveryStatus;
use App\Models\Merchant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreDeliveryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! is_numeric($this->input('merchant_id'))) {
            return $this->user()?->role === 'operator';
        }
        Gate::authorize('ingest', Merchant::findOrFail($this->integer('merchant_id')));

        return true;
    }

    public function rules(): array
    {
        $dataKeys = match ($this->input('type')) {
            'created' => [],
            'assigned' => ['courier_id'],
            'picked_up', 'delivered' => ['latitude', 'longitude'],
            default => [],
        };
        $rules = [
            'event_id' => ['required', 'uuid'],
            'delivery_id' => ['required', 'uuid'],
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'type' => ['required', Rule::enum(DeliveryStatus::class)],
            'occurred_at' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/'],
            'data' => ['present', 'array'],
        ];
        foreach ($dataKeys as $key) {
            $rules['data.'.$key] = match ($key) {
                'courier_id' => ['required', 'integer', Rule::exists('courier_merchant', 'courier_id')->where('merchant_id', $this->integer('merchant_id'))],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            };
        }

        if ($this->input('type') === 'created') {
            foreach (['planned_pickup', 'planned_destination'] as $key) {
                $rules['data.'.$key] = ['required', 'array:label,latitude,longitude', 'size:3'];
                $rules['data.'.$key.'.label'] = ['required', 'string', 'max:255'];
                $rules['data.'.$key.'.latitude'] = ['required', 'numeric', 'between:-90,90'];
                $rules['data.'.$key.'.longitude'] = ['required', 'numeric', 'between:-180,180'];
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['event_id', 'delivery_id', 'merchant_id', 'type', 'occurred_at', 'data'];
            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add('event', 'El evento contiene campos desconocidos.');
            }
            $keys = match ($this->input('type')) {
                'created' => ['planned_pickup', 'planned_destination'],
                'assigned' => ['courier_id'],
                'picked_up', 'delivered' => ['latitude', 'longitude'],
                default => [],
            };
            if (is_array($this->input('data')) && array_diff(array_keys($this->input('data')), $keys) !== []) {
                $validator->errors()->add('data', 'Los datos contienen campos no permitidos para este tipo.');
            }
        }];
    }
}
