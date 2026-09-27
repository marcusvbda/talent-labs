<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\PreferencesPreviewPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdatePreferencesRequest;
use App\Http\Resources\Client\PreferencesPreviewResource;
use App\Http\Resources\Client\PreferencesResource;
use App\Models\JobPreference;
use App\Models\User;
use Illuminate\Http\Request;

class PreferencesController extends Controller
{
    public function show(Request $request): PreferencesResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PreferencesResource(JobPreference::forUser($user));
    }

    public function update(UpdatePreferencesRequest $request): PreferencesResource
    {
        /** @var User $user */
        $user = $request->user();

        $criteria = $request->criteria();

        $preference = JobPreference::forUser($user);
        $preference->fill([
            'titles' => $criteria->titles,
            'seniorities' => $criteria->seniorities,
            'stack' => $criteria->stack,
            'locations' => $criteria->locations,
            'remote_mode' => $criteria->remoteMode,
            'exclude_words' => $criteria->excludeWords,
            'saved_at' => now(),
        ])->save();

        return new PreferencesResource($preference);
    }

    /**
     * Count what a draft would match, with the same query the saved preferences use. Nothing is saved.
     */
    public function preview(UpdatePreferencesRequest $request, PreferencesPreviewPresenter $presenter): PreferencesPreviewResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PreferencesPreviewResource($presenter->forUser($user, $request->criteria()));
    }
}
