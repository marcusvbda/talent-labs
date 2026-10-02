<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\TemplatePreviewPresenter;
use App\Enums\ApplicationLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\PreviewProfileRequest;
use App\Models\User;

class ProfilePreviewController extends Controller
{
    /**
     * @return array{subject: string, body: string, sampleJob: array{company: string, title: string}}
     */
    public function __invoke(PreviewProfileRequest $request, ApplicationLanguage $language): array
    {
        /** @var User $user */
        $user = $request->user();

        return TemplatePreviewPresenter::forUser(
            $user,
            $language,
            (string) $request->validated('subject'),
            (string) $request->validated('body'),
            (string) $request->validated('coverLetter'),
            (array) ($request->validated('links') ?? []),
        );
    }
}
