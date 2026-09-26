<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): Response|RedirectResponse
    {
        $locale = $request->string('locale')->toString();

        $request->user()?->update(['locale' => $locale]);

        Cookie::queue(cookie('locale', $locale, 60 * 24 * 365));

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back();
    }
}
