<?php

namespace Laravel\Reverb\Pulse\Livewire;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Reverb\Pulse\Recorders\ReverbMessages;
use Livewire\Attributes\Lazy;

class Messages extends Card
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
        [$readings, $time, $runAt] = $this->remember(fn () => [
            $messages = $this->graph(["reverb_message:{$this->app}"], 'count')->map->first(),
            $messages->map->map($this->rate(...)),
        ], key: $this->app);

        [$messages, $messagesRate] = $readings;

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch('reverb-messages-chart-update', messages: $messages, messagesRate: $messagesRate);
        }

        return View::make('reverb::livewire.messages', [
            'messages' => $messages,
            'messagesRate' => $messagesRate,
            'rateUnit' => $this->rateUnit(),
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.ReverbMessages::class),
        ]);
    }
}
