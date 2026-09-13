<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\BuildRoleDashboard;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, BuildRoleDashboard $buildRoleDashboard): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return Inertia::render('dashboard', [
            'dashboard' => $buildRoleDashboard->handle($actor),
        ]);
    }
}
