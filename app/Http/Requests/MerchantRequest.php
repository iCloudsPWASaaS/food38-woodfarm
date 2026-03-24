<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'provider'       => ['required', 'string', Rule::in(['uber_eats', 'deliveroo', 'just_eat'])],
            'client_id'      => ['required', 'string', 'max:500'],
            'client_secret'  => ['required', 'string', 'max:500'],
            'api_key'        => ['nullable', 'string', 'max:500'],
            'store_id'       => ['nullable', 'string', 'max:190'],
            'webhook_secret' => ['nullable', 'string', 'max:500'],
            'is_live'        => ['required', 'boolean'],
        ];
    }
}
