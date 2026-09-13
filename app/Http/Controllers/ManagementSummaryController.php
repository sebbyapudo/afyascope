<?php

namespace App\Http\Controllers;

use App\Actions\Reporting\BuildManagementSummary;
use App\Http\Requests\ManagementSummaryRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ManagementSummaryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ManagementSummaryRequest $request,
        BuildManagementSummary $buildManagementSummary,
    ): Response {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return Inertia::render('reports/management', [
            'summary' => $buildManagementSummary->handle($actor, $request->reportingPeriod()),
        ]);
    }
}
