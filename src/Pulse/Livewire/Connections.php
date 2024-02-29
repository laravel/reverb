<?php

namespace Laravel\Reverb\Pulse\Livewire;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Reverb\Pulse\Recorders\ReverbConnections;
use Livewire\Attributes\Lazy;

class Connections extends Card
{
    use HasPeriod,
        RemembersQueries,
        Concerns\HasRate;

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
        $readings = $this->graph(["reverb_connections:{$this->app}"], 'avg')->first()->first()->dd();
        // [$readings, $time, $runAt] = $this->remember(fn () => [
        //     $connections = $this->graph(["reverb_connections:{$this->app}"], 'avg'),
        //     $connections->map->map($this->rate(...)),
        // ], key: $this->app);

        [$connections, $connectionsRate] = $readings;

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch('reverb-connections-chart-update', connections: $connections, connectionsRate: $connectionsRate);
        }

        return View::make('reverb::livewire.connections', [
            'connections' => $connections,
            'connectionsRate' => $connectionsRate,
            'rateUnit' => $this->rateUnit(),
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.ReverbConnections::class),
        ]);
    }
}
