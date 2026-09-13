<?php

namespace App\Http\Controllers;

use App\Actions\Reporting\BuildFinancialReport;
use App\Http\Requests\FinancialReportRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function __invoke(
        FinancialReportRequest $request,
        BuildFinancialReport $buildFinancialReport,
    ): Response {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return Inertia::render('reports/financial', [
            'report' => $buildFinancialReport->handle($actor, $request->reportingPeriod()),
        ]);
    }
}
