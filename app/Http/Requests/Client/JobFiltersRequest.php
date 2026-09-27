<?php

namespace App\Http\Requests\Client;

use App\Ai\Agents\ExtractJobPostingProfile;
use App\Client\JobsPayload;
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
     * The validated list filters, normalised for {@see JobsPayload::build()}.
     *
     * @return array{q: string, language: string, seniority: array<mixed>, remote: string, today: bool, stack: array<mixed>}
     */
    public function filters(): array
    {
        return [
            'q' => $this->string('q')->toString(),
            'language' => $this->string('language')->toString(),
            'seniority' => $this->array('seniority'),
            'remote' => $this->string('remote')->toString(),
            'today' => $this->boolean('today'),
            'stack' => $this->array('stack'),
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
