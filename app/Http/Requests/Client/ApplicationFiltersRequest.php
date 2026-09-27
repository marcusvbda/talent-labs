<?php

namespace App\Http\Requests\Client;

use App\Client\ApplicationsPayload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationFiltersRequest extends FormRequest
{
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
            'status' => ['nullable', Rule::in(['all', 'in_progress', 'sent', 'attention'])],
            'language' => ['nullable', Rule::in(['en', 'pt', 'all'])],
            'q' => ['nullable', 'string', 'max:120'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    /**
     * The validated list filters, normalised for {@see ApplicationsPayload::build()}.
     *
     * @return array{status: string, language: string, q: string}
     */
    public function filters(): array
    {
        return [
            'status' => $this->string('status')->toString(),
            'language' => $this->string('language')->toString(),
            'q' => trim($this->string('q')->toString()),
        ];
    }
}
