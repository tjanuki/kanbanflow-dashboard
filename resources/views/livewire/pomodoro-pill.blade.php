@php
    // Compact Alpine countdown shared with the popup; `start` is an ISO string or null.
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
        get label() { return this.fmt(this.remaining); },
    }";
@endphp

<div class="flex items-center">
    @if ($runningEntryId)
        {{-- Running: red countdown pill --}}
        <button
            type="button"
            wire:click="toggle"
            wire:key="pill-{{ $runningEntryId }}"
            x-data="{{ $clock($runningStartedAt, $workSeconds) }}"
            class="mr-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium shadow-sm transition hover:opacity-90"
            style="background-color: #dc2626; color: #ffffff;"
            title="Pomodoro running — click to open"
        >
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
            </span>
            <span class="font-mono tabular-nums" x-text="label"></span>
            <x-heroicon-m-chevron-down class="h-3.5 w-3.5 opacity-80" />
        </button>
    @else
        {{-- Idle: clock icon button that opens the popup --}}
        <button
            type="button"
            wire:click="toggle"
            class="mr-2 inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
            title="Pomodoro timer"
        >
            <x-heroicon-o-clock class="h-6 w-6" />
        </button>
    @endif
</div>
