<?php

namespace App\Http\Controllers;

use App\Actions\Reporting\BuildOperationalReport;
use App\Http\Requests\OperationalReportRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class OperationalReportController extends Controller
{
    public function __invoke(
        OperationalReportRequest $request,
        BuildOperationalReport $buildOperationalReport,
    ): Response {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return Inertia::render('reports/operational', [
            'report' => $buildOperationalReport->handle($actor, $request->reportingPeriod()),
        ]);
    }
}
