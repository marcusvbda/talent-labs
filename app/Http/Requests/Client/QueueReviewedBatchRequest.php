<?php

namespace App\Http\Requests\Client;

use App\Models\User;
use App\Plans\PlanCatalog;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QueueReviewedBatchRequest extends FormRequest
{
    /**
     * Only the `review` plan mode queues reviewed emails. Read per request so plan changes apply immediately.
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
     * Shape only: each draft's subject/body is validated per draft in the controller, so one
     * invalid draft is rejected without failing the batch.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'drafts' => ['required', 'array', 'min:1', 'max:20'],
            'drafts.*' => ['required', 'array:jobId,subject,body'],
            'drafts.*.jobId' => ['required', 'integer', 'distinct'],
            'drafts.*.subject' => ['present', 'nullable', 'string'],
            'drafts.*.body' => ['present', 'nullable', 'string'],
        ];
    }
}
