<?php

namespace App\Http\Controllers\Client;

use App\Client\ChartPresenter;
use App\Client\DashboardPresenter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardPageController extends Controller
{
    public function __invoke(Request $request, DashboardPresenter $dashboardPresenter, ChartPresenter $chartPresenter): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            'dashboard' => $dashboardPresenter->forUser($user, 'today'),
            'chart' => $chartPresenter->forUser($user, '14d'),
        ]);
    }
}
