<?php

namespace App\Http\Requests\Auth;

use App\Invitations\InvitationTokens;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'invite' => ['required', 'string', 'size:'.InvitationTokens::TOKEN_LENGTH],
            'timezone' => ['required', 'string', 'timezone'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('invite')) {
                    return;
                }

                $invitation = InvitationTokens::find($this->string('invite')->toString());

                if ($invitation === null || ! $invitation->isUsable()) {
                    $validator->errors()->add('invite', __('validation.custom.invite.unusable'));

                    return;
                }

                if (
                    $invitation->email !== null
                    && mb_strtolower($invitation->email) !== mb_strtolower($this->string('email')->toString())
                ) {
                    $validator->errors()->add('email', __('validation.custom.email.invite_mismatch'));
                }
            },
        ];
    }

    /**
     * A missing, unknown or no-longer-usable invite sends the visitor to the
     * Closed page instead of back to the form.
     *
     * @throws ValidationException
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        if ($validator->errors()->has('invite')) {
            throw (new ValidationException($validator))->redirectTo(route('register.closed'));
        }

        parent::failedValidation($validator);
    }
}
