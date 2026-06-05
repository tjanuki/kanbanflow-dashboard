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
        {{-- Running: dark KanbanFlow-style toolbar control --}}
        <button
            type="button"
            wire:click="toggle"
            wire:key="pill-{{ $runningEntryId }}"
            data-testid="pomodoro-pill-running"
            x-data="{{ $clock($runningStartedAt, $workSeconds) }}"
            class="mr-2 inline-flex items-center gap-1.5 transition hover:opacity-90"
            style="height: 34px; padding: 0 8px; border-radius: 3px; background-color: #484848; color: #ffffff;"
            title="Pomodoro running — click to open"
        >
            {{-- Solid red square = "stop indicator" --}}
            <span style="display: inline-block; width: 20px; height: 20px; border-radius: 2px; background-color: #f15a5a;"></span>
            <span class="font-mono tabular-nums" style="font-size: 20px; font-weight: 700; line-height: 1;" x-text="label"></span>
            <x-heroicon-m-chevron-down class="h-4 w-4" style="color: #cfcfcf;" />
        </button>
    @else
        {{-- Idle: clock icon button that opens the popup --}}
        <button
            type="button"
            wire:click="toggle"
            class="mr-2 inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
            title="Pomodoro timer"
        >
            <x-heroicon-o-clock class="h-6 w-6" />
        </button>
    @endif
</div>
