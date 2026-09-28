<?php

namespace App\Http\Controllers\Client\Internal;

use App\Enums\ApplicationLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UploadCvRequest;
use App\Http\Resources\Client\ApplicationProfileResource;
use App\Models\ApplicationProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileCvController extends Controller
{
    public function store(UploadCvRequest $request, ApplicationLanguage $language): ApplicationProfileResource
    {
        $profile = $this->profileFor($request, $language);

        Gate::authorize('update', $profile);

        /** @var UploadedFile $file */
        $file = $request->file('cv');

        $oldPath = $profile->cv_path;

        $newPath = $file->storeAs("cvs/{$profile->user_id}/{$language->value}", Str::random(40).'.pdf', 'local');

        if ($newPath === false) {
            abort(500, 'The CV could not be stored.');
        }

        $profile->fill([
            'cv_path' => $newPath,
            'cv_original_name' => self::safeFileName($file->getClientOriginalName()),
            'cv_size_bytes' => $file->getSize(),
            'cv_uploaded_at' => now(),
        ])->save();

        if (filled($oldPath) && $oldPath !== $newPath && ApplicationProfile::isOwnCvPath($oldPath, $profile->user_id)) {
            Storage::disk('local')->delete($oldPath);
        }

        return new ApplicationProfileResource($profile);
    }

    public function destroy(Request $request, ApplicationLanguage $language): ApplicationProfileResource
    {
        $profile = $this->profileFor($request, $language);

        Gate::authorize('update', $profile);

        if (filled($profile->cv_path) && ApplicationProfile::isOwnCvPath($profile->cv_path, $profile->user_id)) {
            Storage::disk('local')->delete($profile->cv_path);
        }

        $profile->fill([
            'cv_path' => null,
            'cv_original_name' => null,
            'cv_size_bytes' => null,
            'cv_uploaded_at' => null,
        ])->save();

        return new ApplicationProfileResource($profile);
    }

    private function profileFor(Request $request, ApplicationLanguage $language): ApplicationProfile
    {
        /** @var User $user */
        $user = $request->user();

        return $user->applicationProfiles()->where('language', $language)->firstOrFail();
    }

    private static function safeFileName(?string $name): string
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '', $name));
        $name = mb_substr($name, 0, 255);

        return $name === '' ? 'cv.pdf' : $name;
    }
}
