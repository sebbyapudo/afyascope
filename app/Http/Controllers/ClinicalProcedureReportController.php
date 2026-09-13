<?php

namespace App\Http\Controllers;

use App\Actions\Reporting\BuildClinicalProcedureReport;
use App\Http\Requests\ClinicalProcedureReportRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalProcedureReportController extends Controller
{
    public function __invoke(
        ClinicalProcedureReportRequest $request,
        BuildClinicalProcedureReport $buildClinicalProcedureReport,
    ): Response {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return Inertia::render('reports/clinical', [
            'report' => $buildClinicalProcedureReport->handle($actor, $request->reportingPeriod()),
        ]);
    }
}
