<?php

namespace App\Http\Requests\Client;

use App\Ai\Agents\ExtractJobPostingProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobFiltersRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', Rule::in(['en', 'pt', 'all'])],
            'seniority' => ['nullable', 'array'],
            'seniority.*' => ['string', Rule::in(ExtractJobPostingProfile::SENIORITIES)],
            'remote' => ['nullable', Rule::in(['any', 'remote', 'not_remote'])],
            'today' => ['nullable', 'boolean'],
            'stack' => ['nullable', 'array'],
            'stack.*' => ['string', 'max:40'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    /**
     * The client sends `today=true` in the query string; the boolean rule only accepts 1/0.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('today') && is_string($this->input('today'))) {
            $today = filter_var($this->input('today'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($today !== null) {
                $this->merge(['today' => $today]);
            }
        }
    }
}
