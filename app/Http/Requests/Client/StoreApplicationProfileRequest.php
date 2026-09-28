<?php

namespace App\Http\Requests\Client;

use App\Enums\ApplicationLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationProfileRequest extends FormRequest
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
            'language' => ['required', Rule::enum(ApplicationLanguage::class)],
        ];
    }

    public function language(): ApplicationLanguage
    {
        return ApplicationLanguage::from((string) $this->validated('language'));
    }
}
