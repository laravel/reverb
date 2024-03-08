<?php

namespace Laravel\Reverb\Pulse\Livewire;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
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
     * Render the component.
     */
    #[Lazy]
    public function render()
    {
        [$all, $time, $runAt] = $this->remember(fn () => [
            $readings = $this->graph(['reverb_message:sent', 'reverb_message:received'], 'count'),
            $readings->map->map(fn ($values) => $values->map($this->rate(...))),
        ]);

        [$messages, $messagesRate] = $all;

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch('reverb-messages-chart-update', messages: $messages, messagesRate: $messagesRate);
        }

        return View::make('reverb::livewire.messages', [
            'messages' => $messages,
            'messagesRate' => $messagesRate,
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.ReverbMessages::class),
        ]);
    }

    /**
     * Define any CSS that should be loaded for the component.
     *
     * @return string|\Illuminate\Contracts\Support\Htmlable|array<int, string|\Illuminate\Contracts\Support\Htmlable>|null
     */
    protected function css(): HtmlString
    {
        return new HtmlString(<<<'HTML'
        <style>
        .bg-\[\#ea4009\]{background-color:#ea4009}
        .bg-\[\#bc81f1\]{background-color:#bc81f1}
        .bg-\[\#10b981\]{background-color:#10b981}
        .bg-\[\#78d7b3\]{background-color:#78d7b3}
        </style>
        HTML);
    }
}
