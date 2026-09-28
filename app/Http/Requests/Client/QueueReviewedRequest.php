<?php

namespace App\Http\Requests\Client;

use App\Models\User;
use App\Plans\PlanCatalog;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QueueReviewedRequest extends FormRequest
{
    /**
     * Only the `review` plan mode queues reviewed emails. Read per request so plan changes apply immediately.
     */
    public function authorize(PlanCatalog $plans): Response
    {
        /** @var User $user */
        $user = $this->user();

        return $plans->for($user)->mode === 'review'
            ? Response::allow()
            : Response::deny(__('review.mode.denied'));
    }

    /**
     * The subject takes no variables; the body only takes `{{ job_url }}`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jobId' => ['required', 'integer'],
            'subject' => [
                'required', 'string', 'min:1', 'max:200',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && preg_match('/\{\{.*?\}\}/', $value) === 1) {
                        $fail(__('review.subject.variables'));
                    }
                },
            ],
            'body' => [
                'required', 'string', 'min:1', 'max:5000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    preg_match_all('/\{\{\s*(.*?)\s*\}\}/', $value, $matches);

                    if (array_diff($matches[1], ['job_url']) !== []) {
                        $fail(__('review.body.variables'));
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => __('review.subject.length'),
            'subject.string' => __('review.subject.length'),
            'subject.min' => __('review.subject.length'),
            'subject.max' => __('review.subject.length'),
            'body.required' => __('review.body.length'),
            'body.string' => __('review.body.length'),
            'body.min' => __('review.body.length'),
            'body.max' => __('review.body.length'),
        ];
    }
}
