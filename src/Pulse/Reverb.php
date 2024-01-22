<?php

namespace Laravel\Reverb\Pulse;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Reverb\Pulse\Recorders\Connections as RecordersConnections;
use Livewire\Attributes\Lazy;

class Reverb extends Card
{
    use HasPeriod, RemembersQueries;

    public string $appId;

    #[Lazy]
    public function render()
    {
        [$averageConnections, $averageConnectionsTime, $averageConnectionsRunAt] = $this->remember(fn () => $this->graph(
            ["reverb_connections:{$this->appId}"],
            'avg'
        ), "reverb_connections_average:{$this->appId}");

        [$peakConnections, $peakConnectionsTime, $peakConnectionsRunAt] = $this->remember(fn () => $this->graph(
            ["reverb_connections:{$this->appId}"],
            'max'
        ), "reverb_connections_peak:{$this->appId}");

        $connections = $this->formatConnections(
            $averageConnections,
            $peakConnections,
        );

        [$messagesCount, $messagesCountTime, $messagesCountRunAt] = $this->remember(fn () => $this->graph(
            ["reverb_messages:{$this->appId}"],
            'count'
        ), "reverb_messages_count:{$this->appId}");

        $messagesCount = $this->formatReadings($messagesCount, "reverb_messages:{$this->appId}");

        [$messageRate, $messageRateUnit] = $this->calculateMessageRate($messagesCount);

        $messages = collect(['count' => $messagesCount, 'rate' => $messageRate]);

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch('reverb-chart-update', connections: $connections, messages: $messages);
        }

        return view('reverb::livewire.reverb', [
            'averageConnectionsTime' => $averageConnectionsTime,
            'averageConnectionsRunAt' => $averageConnectionsRunAt,
            'peakConnectionsTime' => $peakConnectionsTime,
            'peakConnectionsRunAt' => $peakConnectionsRunAt,
            'messagesCountTime' => $messagesCountTime,
            'messagesCountRunAt' => $messagesCountRunAt,
            'connections' => $connections,
            'messages' => $messages,
            'messageRateUnit' => $messageRateUnit,
            'empty' => $connections['average']->isEmpty() && $connections['peak']->isEmpty() && $messagesCount->isEmpty(),
            'config' => Config::get('pulse.recorders.'.RecordersConnections::class),
        ]);
    }

    /**
     * Format all the given connection objects for graphing
     */
    protected function formatConnections(Collection $average, Collection $peak): Collection
    {
        return collect([
            'average' => $this->formatReadings($average, "reverb_connections:{$this->appId}"),
            'peak' => $this->formatReadings($peak, "reverb_connections:{$this->appId}"),
        ]);
    }

    /**
     * Format the given readings for graphing
     */
    protected function formatReadings(Collection $readings, string $key): Collection
    {
        return $readings->get($key, collect())->get($key, collect());
    }

    /**
     * Calculate the message send rate for the period
     */
    protected function calculateMessageRate(Collection $sends): array
    {
        $unit = $this->periodForHumans() === 'hour' ? 'second' : 'minute';
        $interval = $this->periodAsInterval();
        $maxDataPoints = 60;
        $secondsPerPeriod = ($interval->totalSeconds / $maxDataPoints);

        [$unit, $period] = match ($this->period) {
            '6_hours' => ['minute', 60], // per minute
            '24_hours' => ['hour', 60 * 60], // per hour
            '7_days' => ['day', 60 * 60 * 24], // per day
            default => ['second', 1], // per second
        };

        $multiplier = $period > $secondsPerPeriod ? $secondsPerPeriod / $period : $period / $secondsPerPeriod;

        $sends = $sends->map(fn ($send) => $send === 0 || $send === null ? null : round($send * $multiplier));

        return [$sends, $unit];
    }
}
