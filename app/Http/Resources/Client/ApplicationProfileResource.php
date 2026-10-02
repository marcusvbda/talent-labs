<?php

namespace App\Http\Resources\Client;

use App\Models\ApplicationProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `ApplicationProfile` contract.
 *
 * @property ApplicationProfile $resource
 */
class ApplicationProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->resource;

        return [
            'language' => $profile->language->value,
            'active' => $profile->is_active,
            'cv' => $profile->hasOwnedCv() ? [
                'fileName' => $profile->cv_original_name ?? 'cv.pdf',
                'sizeBytes' => $profile->cv_size_bytes ?? 0,
                'uploadedAt' => $profile->cv_uploaded_at?->toIso8601String(),
            ] : null,
            'emailSubject' => $profile->email_subject,
            'emailBody' => $profile->email_body,
            'coverLetter' => $profile->cover_letter ?? '',
            'links' => $profile->links ?? [],
            'complete' => $profile->isComplete(),
            'missing' => $profile->missing(),
        ];
    }
}
