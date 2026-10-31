<?php

namespace App\Http\Requests\Client;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkApplicationRepliedRequest extends FormRequest
{
    /**
     * Ownership is checked in the controller through the `view` policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'replied' => ['required', 'boolean'],
        ];
    }
}
