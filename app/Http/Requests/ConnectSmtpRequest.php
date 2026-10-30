<?php

namespace App\Http\Requests;

use App\Data\SmtpSettings;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConnectSmtpRequest extends FormRequest
{
    /**
     * Inactive users get a 403 before any validation runs.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isActive();
    }

    /**
     * Host, port and security are read only for the custom preset; the others use the preset's values.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preset' => ['required', 'string', Rule::in(array_keys(self::presets()))],
            'host' => ['exclude_unless:preset,custom', 'required', 'string', 'max:253', $this->notBlockedHost(...)],
            'port' => ['exclude_unless:preset,custom', 'required', 'integer', 'in:465,587'],
            'security' => [
                'exclude_unless:preset,custom',
                'required',
                'string',
                Rule::in([(int) $this->input('port') === 587 ? 'starttls' : 'tls']),
            ],
            'username' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
            'from_address' => ['required', 'string', 'email', 'max:254'],
            'display_name' => ['required', 'string', 'max:100', 'not_regex:/[\p{Cc}\p{Zl}\p{Zp}]/u'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errors = $validator->errors();

                if ($errors->has('username') || $errors->has('from_address')) {
                    return;
                }

                if (self::domain((string) $this->input('username')) !== self::domain((string) $this->input('from_address'))) {
                    $errors->add('from_address', __('integrations.smtp.from_domain'));
                }
            },
        ];
    }

    public function settings(): SmtpSettings
    {
        $preset = (string) $this->validated('preset');
        $values = self::presets()[$preset] ?? null;

        if (is_array($values)) {
            $host = (string) $values['host'];
            $port = (int) $values['port'];
            $security = (string) $values['security'];
        } else {
            $host = self::normalizeHost((string) $this->validated('host'));
            $port = (int) $this->validated('port');
            $security = (string) $this->validated('security');
        }

        return new SmtpSettings(
            preset: $preset,
            host: $host,
            port: $port,
            security: $security,
            username: (string) $this->validated('username'),
            password: (string) $this->validated('password'),
            fromAddress: (string) $this->validated('from_address'),
            displayName: (string) $this->validated('display_name'),
        );
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    private function notBlockedHost(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var list<string> $blocked */
        $blocked = config('connected-integrations.smtp.blocked_hosts', []);

        if (is_string($value) && in_array(self::normalizeHost($value), $blocked, true)) {
            $fail('validation.not_in')->translate();
        }
    }

    /**
     * @return array<string, array{host: string, port: int, security: string}|null>
     */
    private static function presets(): array
    {
        /** @var array<string, array{host: string, port: int, security: string}|null> */
        return config('connected-integrations.smtp.presets', []);
    }

    private static function normalizeHost(string $host): string
    {
        return rtrim(mb_strtolower(trim($host)), '.');
    }

    private static function domain(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '' : mb_strtolower(trim(substr($email, $at + 1)));
    }
}
