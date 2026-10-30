<?php

namespace App\Http\Requests\Auth;

use App\Invitations\InvitationTokens;
use App\Registration\RegistrationMode;
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

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            // Open mode never reveals whether an address has an account (no unique rule).
            'email' => RegistrationMode::isOpen()
                ? ['required', 'string', 'email', 'max:255']
                : ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'invite' => RegistrationMode::isOpen()
                ? ['nullable', 'string']
                : ['required', 'string', 'size:'.InvitationTokens::TOKEN_LENGTH],
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
                    // Open mode ignores a blank or unusable invite and registers without it.
                    if (RegistrationMode::isOpen()) {
                        return;
                    }

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
     * Closed page instead of back to the form (invite mode only; open mode
     * never adds an `invite` error).
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
