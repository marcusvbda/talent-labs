<?php

namespace App\Http\Requests\Client;

use App\Models\User;
use App\Outreach\Actions\CanSendApplications;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QueueRandomApplicationsRequest extends FormRequest
{
    /**
     * Random send is open to every plan; the route already requires an authenticated client.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Same eligibility as any send (Gmail, active profile, quota left). No pause check: queued rows park until Resume.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->user();

                if (! app(CanSendApplications::class)->check($user)->ok()) {
                    $validator->errors()->add('eligibility', __('queue.reject.not_eligible'));
                }
            },
        ];
    }
}
