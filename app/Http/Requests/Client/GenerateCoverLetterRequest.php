<?php

namespace App\Http\Requests\Client;

use App\Models\User;
use App\Plans\PlanCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateCoverLetterRequest extends FormRequest
{
    /**
     * Only plans with AI writing ask for letters. Read per request so plan changes apply immediately.
     */
    public function authorize(PlanCatalog $plans): bool
    {
        /** @var User $user */
        $user = $this->user();

        return $plans->for($user)->aiWriting;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jobId' => ['required', 'integer'],
            'regenerate' => ['sometimes', 'boolean'],
        ];
    }
}
