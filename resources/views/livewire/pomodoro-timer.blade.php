@php
    $fmtDuration = function (int $s): string {
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        if ($h) {
            return "{$h}h {$m}m";
        }

        return $m ? "{$m}m" : '0m';
    };

    // Reusable Alpine countdown definition. `start` is an ISO string or null.
    $clock = fn (?string $start, int $work) => "{
        startedAt: " . ($start ? "'{$start}'" : 'null') . ",
        work: {$work},
        now: Math.floor(Date.now() / 1000),
        timer: null,
        init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
        destroy() { clearInterval(this.timer); },
        tick() { this.now = Math.floor(Date.now() / 1000); },
        get elapsed() {
            if (! this.startedAt) return 0;
            return Math.max(0, this.now - Math.floor(new Date(this.startedAt).getTime() / 1000));
        },
        get remaining() { return Math.max(0, this.work - this.elapsed); },
        fmt(s) {
            const m = Math.floor(s / 60).toString().padStart(2, '0');
            const sec = (s % 60).toString().padStart(2, '0');
            return m + ':' + sec;
        },
        get label() { return this.startedAt ? this.fmt(this.remaining) : this.fmt(this.work); },
    }";
@endphp

<div class="pointer-events-none fixed inset-0 z-[60]">
    {{-- Floating launcher / live countdown --}}
    <button
        type="button"
        wire:click="togglePanel"
        @class([
            'pointer-events-auto fixed bottom-5 right-5 flex items-center gap-2 rounded-full px-4 py-3 font-medium text-white shadow-lg transition',
            'bg-primary-600 hover:bg-primary-500' => ! $runningEntryId,
            'bg-red-600 hover:bg-red-500' => $runningEntryId,
        ])
        title="Pomodoro timer"
    >
        <x-heroicon-s-clock class="h-5 w-5" />
        @if ($runningEntryId)
            <span wire:key="clock-btn-{{ $runningEntryId }}" x-data="{{ $clock($runningStartedAt, $workSeconds) }}" class="font-mono text-sm tabular-nums">
                <span x-text="label"></span>
            </span>
        @else
            <span class="text-sm">Pomodoro</span>
        @endif
    </button>

    {{-- Slide-over --}}
    @if ($showPanel)
        <div
            class="pointer-events-auto fixed inset-0 bg-black/30"
            wire:click="togglePanel"
        ></div>

        <div class="pointer-events-auto fixed inset-y-0 right-0 flex w-full max-w-sm flex-col bg-white shadow-2xl dark:bg-gray-900">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                    <x-heroicon-s-clock class="h-5 w-5 text-primary-600" />
                    Pomodoro
                </h2>
                <button type="button" wire:click="togglePanel" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <x-heroicon-m-x-mark class="h-5 w-5" />
                </button>
            </div>

            {{-- TODAY summary --}}
            <div class="flex items-center gap-4 border-b border-gray-200 px-5 py-3 text-sm dark:border-gray-800">
                <span class="font-medium text-gray-900 dark:text-gray-100">TODAY</span>
                <span class="text-gray-600 dark:text-gray-300">{{ $fmtDuration($this->todaySeconds) }}</span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-600 dark:text-gray-300">{{ $this->todayPomodoros }} {{ \Illuminate\Support\Str::plural('Pomodoro', $this->todayPomodoros) }}</span>
            </div>

            {{-- Running timer --}}
            <div class="border-b border-gray-200 px-5 py-6 text-center dark:border-gray-800">
                @if ($runningEntryId)
                    <p class="mb-1 truncate text-sm text-gray-500 dark:text-gray-400">{{ $runningTaskName }}</p>
                    <div
                        wire:key="clock-panel-{{ $runningEntryId }}"
                        x-data="{{ $clock($runningStartedAt, $workSeconds) }}"
                        class="font-mono text-5xl font-semibold tabular-nums text-gray-900 dark:text-gray-100"
                    >
                        <span x-text="label"></span>
                    </div>
                    <button
                        type="button"
                        wire:click="stop"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-500"
                    >
                        <x-heroicon-m-stop class="h-4 w-4" />
                        Stop
                    </button>
                @else
                    <div class="font-mono text-5xl font-semibold tabular-nums text-gray-300 dark:text-gray-600">25:00</div>
                    <p class="mt-3 text-sm text-gray-400">No timer running — press ▶ on a task to start.</p>
                @endif
            </div>

            {{-- Session log --}}
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Session log</h3>
                @forelse ($this->todayEntries as $entry)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm dark:border-gray-800">
                        <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300">{{ $entry->task?->name ?? '—' }}</span>
                        <span class="ml-3 whitespace-nowrap font-mono text-xs text-gray-500">
                            {{ $entry->started_at->format('H:i') }} —
                            @if ($entry->ended_at)
                                {{ $entry->ended_at->format('H:i') }}
                            @else
                                <span class="text-red-500">pending</span>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-gray-400">No sessions yet today.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
