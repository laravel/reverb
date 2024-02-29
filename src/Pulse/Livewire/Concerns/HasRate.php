<?php

namespace Laravel\Reverb\Pulse\Livewire\Concerns;

use Carbon\CarbonInterval;

trait HasRate
{
    /**
     * The message send rate.
     */
    protected function rate(?float $count): ?float
    {
        return ! $count
            ? null
            : round($count * $this->rateMultiplier(), 2);
    }

    /**
     * The rate multiplier.
     */
    protected function rateMultiplier(): float
    {
        $secondsPerBucket = ($this->periodAsInterval()->totalSeconds / $maxDataPoints = 60);

        $period = match ($this->period) {
            '6_hours' => CarbonInterval::minute(),
            '24_hours' => CarbonInterval::hour(),
            '7_days' => CarbonInterval::day(),
            default => CarbonInterval::second(),
        };

        return $period->totalSeconds > $secondsPerBucket
            ? $secondsPerBucket / $period->totalSeconds
            : $period->totalSeconds / $secondsPerBucket;
    }

    /**
     * The rate unit.
     */
    protected function rateUnit(): string
    {
        return match ($this->period) {
            '6_hours' => 'minute',
            '24_hours' => 'hour',
            '7_days' => 'day',
            default => 'second',
        };
    }
}
