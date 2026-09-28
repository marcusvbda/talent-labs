<?php

namespace App\Http\Requests\Client;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadCvRequest extends FormRequest
{
    public const string NOT_PDF_MESSAGE = 'The CV must be a PDF file.';

    public const string TOO_LARGE_MESSAGE = 'The CV must not be larger than 5 MB.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Size is checked before type so an oversized file reports the size error.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cv' => [
                'bail',
                'required',
                'file',
                'max:5120',
                'mimetypes:application/pdf',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! self::hasPdfMagicBytes($value)) {
                        $fail(self::NOT_PDF_MESSAGE);
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
            'cv.file' => self::NOT_PDF_MESSAGE,
            'cv.mimetypes' => self::NOT_PDF_MESSAGE,
            'cv.max' => self::TOO_LARGE_MESSAGE,
            'cv.uploaded' => self::TOO_LARGE_MESSAGE,
        ];
    }

    private static function hasPdfMagicBytes(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return false;
        }

        return @file_get_contents($path, false, null, 0, 5) === '%PDF-';
    }
}
