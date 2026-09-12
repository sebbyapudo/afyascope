<?php

namespace App\Actions\Reporting;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReportingPeriod
{
    public CarbonImmutable $startsAt;

    public CarbonImmutable $endsAt;

    public function __construct(CarbonImmutable $fromDate, CarbonImmutable $throughDate)
    {
        $timezone = config('app.timezone');

        if (! is_string($timezone) || $timezone === '') {
            throw new InvalidArgumentException('The application timezone must be configured.');
        }

        $this->startsAt = $fromDate->setTimezone($timezone)->startOfDay();
        $this->endsAt = $throughDate->setTimezone($timezone)->endOfDay();

        if ($this->startsAt->isAfter($this->endsAt)) {
            throw new InvalidArgumentException('The reporting start date must not be after the end date.');
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(): array
    {
        return [$this->startsAt, $this->endsAt];
    }
}
