<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\ProfilesDataPresenter;
use App\Enums\ApplicationLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreApplicationProfileRequest;
use App\Http\Requests\Client\UpdateApplicationProfileRequest;
use App\Http\Resources\Client\ApplicationProfileResource;
use App\Models\ApplicationProfile;
use App\Models\User;
use App\Outreach\Support\ApplicationTemplateRenderer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ProfilesController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return ProfilesDataPresenter::forUser($user);
    }

    public function store(StoreApplicationProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $language = $request->language();

        if ($user->applicationProfiles()->where('language', $language)->exists()) {
            abort(409, 'This language already has a profile.');
        }

        $defaults = ApplicationTemplateRenderer::defaultsFor($language);

        try {
            $profile = $user->applicationProfiles()->create([
                'language' => $language,
                'is_active' => true,
                'email_subject' => $defaults['subject'],
                'email_body' => $defaults['body'],
                'cover_letter' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This language already has a profile.');
        }

        return (new ApplicationProfileResource($profile))->response()->setStatusCode(201);
    }

    public function update(UpdateApplicationProfileRequest $request, ApplicationLanguage $language): ApplicationProfileResource
    {
        $profile = $this->profileFor($request, $language);

        Gate::authorize('update', $profile);

        $coverLetter = $request->validated('coverLetter');

        $profile->fill([
            'email_subject' => $request->validated('emailSubject'),
            'email_body' => $request->validated('emailBody'),
            'cover_letter' => trim((string) $coverLetter) === '' ? null : $coverLetter,
            'is_active' => $request->boolean('active'),
        ]);

        // Onboarding saves never send `links`; leave stored links untouched then.
        if ($request->exists('links')) {
            /** @var array<array-key, array{label: string, url: string}> $rows */
            $rows = $request->validated('links') ?? [];

            $links = array_values(array_map(
                fn (array $row): array => ['label' => $row['label'], 'url' => $row['url']],
                $rows,
            ));

            $profile->links = $links === [] ? null : $links;
        }

        $profile->save();

        return new ApplicationProfileResource($profile);
    }

    public function destroy(Request $request, ApplicationLanguage $language): Response
    {
        $profile = $this->profileFor($request, $language);

        Gate::authorize('delete', $profile);

        if (filled($profile->cv_path) && ApplicationProfile::isOwnCvPath($profile->cv_path, $profile->user_id)) {
            Storage::disk('local')->delete($profile->cv_path);
        }

        $profile->delete();

        return response()->noContent();
    }

    private function profileFor(Request $request, ApplicationLanguage $language): ApplicationProfile
    {
        /** @var User $user */
        $user = $request->user();

        return $user->applicationProfiles()->where('language', $language)->firstOrFail();
    }
}
