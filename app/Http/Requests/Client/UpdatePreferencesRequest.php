<?php

namespace App\Http\Requests\Client;

use App\Enums\RemoteMode;
use App\Outreach\Data\PreferenceCriteria;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a `Preferences` draft (camelCase keys). Used to save and to preview.
 */
class UpdatePreferencesRequest extends FormRequest
{
    private const LIST_FIELDS = ['titles', 'stack', 'locations', 'excludeWords'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];

        foreach (self::LIST_FIELDS as $field) {
            $value = $this->input($field);

            if (is_array($value)) {
                $cleaned[$field] = $this->cleanList($value);
            }
        }

        $seniorities = $this->input('seniorities');

        if (is_array($seniorities)) {
            $cleaned['seniorities'] = array_values(array_unique($seniorities, SORT_REGULAR));
        }

        $this->merge($cleaned);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'seniorities' => ['present', 'array'],
            'seniorities.*' => [Rule::in(PreferenceCriteria::allowedSeniorities())],
            'remoteMode' => ['required', Rule::enum(RemoteMode::class)],
        ];

        foreach (self::LIST_FIELDS as $field) {
            $rules[$field] = ['present', 'array', 'max:20'];
            $rules[$field.'.*'] = ['string', 'min:1', 'max:60'];
        }

        $rules['locations'][] = 'required_if:remoteMode,'.RemoteMode::LocationsOnly->value;

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locations.required_if' => __('preferences.locations.required'),
        ];
    }

    public function criteria(): PreferenceCriteria
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return PreferenceCriteria::fromDraft($validated);
    }

    /**
     * Trim string items, drop empties and dedupe case-insensitively (first spelling wins).
     * `null` items (empty strings after ConvertEmptyStringsToNull) are dropped too; other
     * non-scalar items are kept so the `string` rule rejects them.
     *
     * @param  array<mixed>  $values
     * @return list<mixed>
     */
    private function cleanList(array $values): array
    {
        $cleaned = [];
        $seen = [];

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (! is_scalar($value)) {
                $cleaned[] = $value;

                continue;
            }

            $value = trim((string) $value);
            $key = mb_strtolower($value);

            if ($value === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $cleaned[] = $value;
        }

        return $cleaned;
    }
}
