<?php

namespace App\Actions\Reporting;

use App\Models\Visit;
use App\VisitStatus;
use stdClass;

final class AggregateVisitMeasures
{
    /** @return array{occurred: int, active: int, completed: int} */
    public function handle(ReportingPeriod $period): array
    {
        $visitMetrics = Visit::query()
            ->whereBetween('occurred_at', $period->bounds())
            ->toBase()
            ->selectRaw('COUNT(*) as occurred')
            ->selectRaw('COALESCE(SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END), 0) as active', [
                VisitStatus::Completed->value,
            ])
            ->first();

        return [
            'occurred' => $this->integer($visitMetrics, 'occurred'),
            'active' => $this->integer($visitMetrics, 'active'),
            'completed' => Visit::query()
                ->where('status', VisitStatus::Completed->value)
                ->whereBetween('completed_at', $period->bounds())
                ->count(),
        ];
    }

    private function integer(?stdClass $metrics, string $property): int
    {
        if (! $metrics instanceof stdClass || ! property_exists($metrics, $property)) {
            return 0;
        }

        return (int) $metrics->{$property};
    }
}
