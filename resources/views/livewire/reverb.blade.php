<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Reverb"
        title="Peak Connections Time: {{ number_format($peakConnectionsTime) }}ms; Run at: {{ $peakConnectionsRunAt }}; Average Connections Time: {{ number_format($averageConnectionsTime) }}ms; Run at: {{ $averageConnectionsRunAt }}; Message Count Time: {{ number_format($messagesCountTime) }}ms; Run at: {{ $messagesCountRunAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-reverb::icons.megaphone />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($empty)
            <x-pulse::no-results />
        @else
            <div class="grid gap-3 mx-px mb-px">
                <div wire:key="connections">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-gray-700 dark:text-gray-300">
                            Connections
                        </h3>

                        <div class="flex flex-wrap gap-4">
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                                <div class="h-0.5 w-3 rounded-full bg-[rgba(147,51,234,0.5)]"></div>
                                Peak
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                                <div class="h-0.5 w-3 rounded-full bg-[#eab308]"></div>
                                Average
                            </div>
                        </div>
                    </div>

                    @php
                        $highest = $connections->flatten()->max();
                    @endphp

                    <div class="mt-3 relative">
                        <div class="absolute -left-px -top-2 max-w-fit h-4 flex items-center px-1 text-xs leading-none text-white font-bold bg-purple-500 rounded after:[--triangle-size:4px] after:border-l-purple-500 after:absolute after:right-[calc(-1*var(--triangle-size))] after:top-[calc(50%-var(--triangle-size))] after:border-t-[length:var(--triangle-size)] after:border-b-[length:var(--triangle-size)] after:border-l-[length:var(--triangle-size)] after:border-transparent">
                            @if ($config['sample_rate'] < 1)
                                <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($highest) }}">~{{ number_format($highest * (1 / $config['sample_rate'])) }}</span>
                            @else
                                {{ number_format($highest) }}
                            @endif
                        </div>

                        <div
                            wire:ignore
                            class="h-14"
                            x-data="connectionsChart({
                                type: 'Connections',
                                readings: @js($connections),
                                sampleRate: {{ $config['sample_rate'] }},
                            })"
                        >
                            <canvas x-ref="connectionsCanvas" class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                        </div>
                    </div>
                </div>

                <div wire:key="messages">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-gray-700 dark:text-gray-300">
                            Messages
                        </h3>

                        <div class="flex flex-wrap gap-4">
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                                <div class="h-0.5 w-3 rounded-full bg-[rgba(107,114,128,0.5)]"></div>
                                Sent
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                                <div class="h-0.5 w-3 rounded-full bg-[rgba(147,51,234,0.5)]"></div>
                                Per {{ $messageRateUnit }}
                            </div>
                        </div>
                    </div>

                    @php
                        $highest = $messages->flatten()->max();
                    @endphp

                    <div class="mt-3 relative">
                        <div class="absolute -left-px -top-2 max-w-fit h-4 flex items-center px-1 text-xs leading-none text-white font-bold bg-purple-500 rounded after:[--triangle-size:4px] after:border-l-purple-500 after:absolute after:right-[calc(-1*var(--triangle-size))] after:top-[calc(50%-var(--triangle-size))] after:border-t-[length:var(--triangle-size)] after:border-b-[length:var(--triangle-size)] after:border-l-[length:var(--triangle-size)] after:border-transparent">
                            @if ($config['sample_rate'] < 1)
                                <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($highest) }}">~{{ number_format($highest * (1 / $config['sample_rate'])) }}</span>
                            @else
                                {{ number_format($highest) }}
                            @endif
                        </div>

                        <div
                            wire:ignore
                            class="h-14"
                            x-data="messagesChart({
                                type: 'Messages',
                                readings: @js($messages),
                                sampleRate: {{ $config['sample_rate'] }},
                            })"
                        >
                            <canvas x-ref="messagesCanvas" class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>

@script
<script>
Alpine.data('connectionsChart', (config) => ({
    init() {
        let chart = new Chart(
            this.$refs.connectionsCanvas,
            {
                type: 'line',
                data: {
                    labels: this.labels(config.readings),
                    datasets: [
                        {
                            label: 'Peak',
                            borderColor: 'rgba(147,51,234,0.5)',
                            data: this.scale(config.readings.peak),
                            order: 2,
                        },
                        {
                            label: 'Average',
                            borderColor: '#eab308',
                            data: this.scale(config.readings.average),
                            order: 3,
                        },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        autoPadding: false,
                        padding: {
                            top: 1,
                        },
                    },
                    datasets: {
                        line: {
                            borderWidth: 2,
                            borderCapStyle: 'round',
                            pointHitRadius: 10,
                            pointStyle: false,
                            tension: 0.2,
                            spanGaps: false,
                            segment: {
                                borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: false,
                        },
                        y: {
                            display: false,
                            min: 0,
                            max: this.highest(config.readings),
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            mode: 'index',
                            position: 'nearest',
                            intersect: false,
                            callbacks: {
                                beforeBody: (context) => context
                                    .map(item => `${item.dataset.label}: ${config.sampleRate < 1 ? '~' : ''}${item.formattedValue}`)
                                    .join(', '),
                                label: () => null,
                            },
                        },
                    },
                },
            }
        )

        Livewire.on('reverb-chart-update', ({ connections }) => {
            if (chart === undefined) {
                return
            }

            chart.data.labels = this.labels(connections)
            chart.options.scales.y.max = this.highest(connections)
            chart.data.datasets[0].data = this.scale(connections.peak)
            chart.data.datasets[1].data = this.scale(connections.average)
            chart.update()
        })
    },
    labels(readings) {
        return Object.keys(readings.average)
    },
    scale(data) {
        return Object.values(data).map(value => value * (1 / config.sampleRate ))
    },
    highest(readings) {
        return Math.max(...Object.values(readings).map(dataset => Math.max(...Object.values(dataset)))) * (1 / config.sampleRate)
    }
}))

Alpine.data('messagesChart', (config) => ({
    init() {
        let chart = new Chart(
            this.$refs.messagesCanvas,
            {
                type: 'line',
                data: {
                    labels: this.labels(config.readings),
                    datasets: [
                        {
                            label: 'Sent',
                            borderColor: 'rgba(107,114,128,0.5)',
                            data: this.scale(config.readings.count),
                            order: 1,
                        },
                        {
                            label: 'Per {{ $messageRateUnit }}',
                            borderColor: 'rgba(147,51,234,0.5)',
                            data: this.scale(config.readings.rate),
                            order: 2,
                        },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        autoPadding: false,
                        padding: {
                            top: 1,
                        },
                    },
                    datasets: {
                        line: {
                            borderWidth: 2,
                            borderCapStyle: 'round',
                            pointHitRadius: 10,
                            pointStyle: false,
                            tension: 0.2,
                            spanGaps: false,
                            segment: {
                                borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: false,
                        },
                        y: {
                            display: false,
                            min: 0,
                            max: this.highest(config.readings),
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            mode: 'index',
                            position: 'nearest',
                            intersect: false,
                            callbacks: {
                                beforeBody: (context) => context
                                    .map(item => `${item.dataset.label}: ${config.sampleRate < 1 ? '~' : ''}${item.formattedValue}`)
                                    .join(', '),
                                label: () => null,
                            },
                        },
                    },
                },
            }
        )

        Livewire.on('reverb-chart-update', ({ messages }) => {
            if (chart === undefined) {
                return
            }

            chart.data.labels = this.labels(messages)
            chart.options.scales.y.max = this.highest(messages)
            chart.data.datasets[0].data = this.scale(messages.count)
            chart.data.datasets[1].data = this.scale(messages.rate)
            chart.update()
        })
    },
    labels(readings) {
        return Object.keys(readings.count)
    },
    scale(data) {
        return Object.values(data).map(value => value * (1 / config.sampleRate ))
    },
    highest(readings) {
        return Math.max(...Object.values(readings).map(dataset => Math.max(...Object.values(dataset)))) * (1 / config.sampleRate)
    }
}))

Alpine.data('messageRatesChart', (config) => ({
    init() {
        let chart = new Chart(
            this.$refs.messageRatesCanvas,
            {
                type: 'line',
                data: {
                    labels: this.labels(config.readings),
                    datasets: [
                        {
                            label: 'Average',
                            borderColor: 'rgba(107,114,128,0.5)',
                            data: this.scale(config.readings),
                            order: 4,
                        },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        autoPadding: false,
                        padding: {
                            top: 1,
                        },
                    },
                    datasets: {
                        line: {
                            borderWidth: 2,
                            borderCapStyle: 'round',
                            pointHitRadius: 10,
                            pointStyle: false,
                            tension: 0.2,
                            spanGaps: false,
                            segment: {
                                borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: false,
                        },
                        y: {
                            display: false,
                            min: 0,
                            max: this.highest(config.readings),
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            mode: 'index',
                            position: 'nearest',
                            intersect: false,
                            callbacks: {
                                beforeBody: (context) => context
                                    .map(item => `${item.dataset.label}: ${config.sampleRate < 1 ? '~' : ''}${item.formattedValue}`)
                                    .join(', '),
                                label: () => null,
                            },
                        },
                    },
                },
            }
        )

        // Livewire.on('reverb-connections-chart-update', ({ queues }) => {
        //     if (chart === undefined) {
        //         return
        //     }

        //     if (queues[config.queue] === undefined && chart) {
        //         chart.destroy()
        //         chart = undefined
        //         return
        //     }

        //     chart.data.labels = this.labels(queues[config.queue])
        //     chart.options.scales.y.max = this.highest(queues[config.queue])
        //     chart.data.datasets[0].data = this.scale(queues[config.queue].queued)
        //     chart.data.datasets[1].data = this.scale(queues[config.queue].processing)
        //     chart.data.datasets[2].data = this.scale(queues[config.queue].released)
        //     chart.data.datasets[3].data = this.scale(queues[config.queue].processed)
        //     chart.data.datasets[4].data = this.scale(queues[config.queue].failed)
        //     chart.update()
        // })
    },
    labels(readings) {
        return Object.keys(readings)
    },
    scale(data) {
        return Object.values(data).map(value => value * (1 / config.sampleRate ))
    },
    highest(readings) {
        return Math.max(...Object.values(readings)) * (1 / config.sampleRate)
    }
}))
</script>
@endscript
