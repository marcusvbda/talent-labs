<?php

namespace App\Http\Requests\Client;

use App\Outreach\Actions\CanSendApplications;
use App\Outreach\Support\ApplicationTemplateRenderer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an `ApplicationProfile` edit (camelCase keys).
 */
class UpdateApplicationProfileRequest extends FormRequest
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
            'emailSubject' => ['required', 'string', 'max:'.CanSendApplications::MAX_SUBJECT_LENGTH, $this->knownVariables()],
            'emailBody' => ['required', 'string', 'max:'.CanSendApplications::MAX_BODY_LENGTH, $this->knownVariables()],
            'coverLetter' => [
                'nullable',
                'string',
                'max:6000',
                $this->knownVariables(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ApplicationTemplateRenderer::containsCoverLetterVariable($value)) {
                        $fail('The cover letter cannot contain {{ cover_letter }}.');
                    }
                },
            ],
            'active' => ['required', 'boolean'],
            'links' => ['sometimes', 'array', 'max:10'],
            'links.*' => ['array:label,url'],
            'links.*.label' => ['required', 'string', 'max:60', 'not_regex:/\{\{|\}\}/'],
            'links.*.url' => ['required', 'string', 'max:2048', 'url:http,https', 'not_regex:/\{\{|\}\}/'],
        ];
    }

    private function knownVariables(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $unknown = ApplicationTemplateRenderer::unknownVariables($value);

            if ($unknown !== []) {
                $fail("Unknown variable {{ {$unknown[0]} }}.");
            }
        };
    }
}
