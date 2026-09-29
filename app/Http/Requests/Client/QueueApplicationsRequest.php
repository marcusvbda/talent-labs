<?php

namespace App\Http\Requests\Client;

use App\Models\User;
use App\Outreach\Actions\CanSendApplications;
use App\Plans\PlanCatalog;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QueueApplicationsRequest extends FormRequest
{
    /**
     * Only the `select` plan mode queues hand-picked postings. Read per request so plan changes apply immediately.
     */
    public function authorize(PlanCatalog $plans): Response
    {
        return match ($plans->for($this->client())->mode) {
            'select' => Response::allow(),
            'review' => Response::deny(__('queue.mode.review_only')),
            default => Response::deny(__('queue.mode.select_plans_only')),
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(CanSendApplications $eligibility): array
    {
        $remaining = $eligibility->check($this->client())->remaining;

        return [
            'jobIds' => ['required', 'array', 'min:1', 'max:'.$remaining],
            'jobIds.*' => ['integer', 'distinct'],
        ];
    }

    private function client(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
