<?php

namespace App\Actions\Reporting;

final class AggregateOperationalMeasures
{
    public function __construct(
        private AggregateVisitMeasures $aggregateVisitMeasures,
        private AggregateClinicalProcedureMeasures $aggregateClinicalProcedureMeasures,
    ) {}

    /** @return array{visits: array{occurred: int, active: int, completed: int}, milestones: array{consultationsStarted: int, procedureRequired: int, noProcedure: int, proceduresCompleted: int, recoveriesStarted: int, dischargesCompleted: int}} */
    public function handle(ReportingPeriod $period): array
    {
        $clinicalMeasures = $this->aggregateClinicalProcedureMeasures->handle($period);

        return [
            'visits' => $this->aggregateVisitMeasures->handle($period),
            'milestones' => [
                'consultationsStarted' => $clinicalMeasures['consultationDecision']['consultationsStarted'],
                'procedureRequired' => $clinicalMeasures['consultationDecision']['procedureRequired'],
                'noProcedure' => $clinicalMeasures['consultationDecision']['noProcedure'],
                'proceduresCompleted' => $clinicalMeasures['procedure']['completed'],
                'recoveriesStarted' => $clinicalMeasures['recovery']['started'],
                'dischargesCompleted' => $clinicalMeasures['recovery']['discharged'],
            ],
        ];
    }
}
