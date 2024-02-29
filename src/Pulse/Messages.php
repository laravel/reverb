<?php

namespace Laravel\Reverb\Pulse;

use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Reverb\Pulse\Recorders\Connections as RecordersConnections;
use Livewire\Attributes\Lazy;

class Messages extends Card
{
    use HasPeriod, RemembersQueries;

    /**
     * The application ID to scope metrics to.
     */
    public string $app;

    /**
     * Render the component.
     */
    #[Lazy]
    public function render()
    {
        [$messages, $messagesTime, $messagesRunAt] = $this->remember(fn () => $this->graph(
            ["reverb_message:{$this->app}"],
            'count'
        ), "messages:{$this->app}");

        // Flatten as we only care about the one app per card.
        $messages = $messages->map->first();

        $messagesRate = $messages->map->map(fn ($count) => $this->messageRate($count));

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch('reverb-messages-chart-update', messages: $messages, messagesRate: $messagesRate);
        }

        return View::make('reverb::livewire.messages', [
            'messages' => $messages,
            'messagesRate' => $messagesRate,
            'rateUnit' => $this->rateUnit(),
            'time' => $messagesTime,
            'runAt' => $messagesRunAt,
            'config' => Config::get('pulse.recorders.'.Recorders\ReverbMessages::class),
        ]);
    }

    /**
     * The message send rate.
     */
    protected function messageRate(?float $count): ?float
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
