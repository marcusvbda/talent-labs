<?php

namespace App\Http\Requests\Client;

use App\Models\User;
use App\Plans\PlanCatalog;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewDraftsRequest extends FormRequest
{
    /**
     * Only the `review` plan mode previews drafts. Read per request so plan changes apply immediately.
     */
    public function authorize(PlanCatalog $plans): Response
    {
        /** @var User $user */
        $user = $this->user();

        return match ($plans->for($user)->mode) {
            'review' => Response::allow(),
            'select' => Response::deny(__('review.mode.denied')),
            default => Response::deny(__('queue.mode.select_plans_only')),
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jobIds' => ['required', 'array', 'min:1', 'max:20'],
            'jobIds.*' => ['integer', 'distinct'],
        ];
    }
}
