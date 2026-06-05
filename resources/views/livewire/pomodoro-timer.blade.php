@php
    use App\Support\Format;

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

<div class="pointer-events-none fixed inset-0" style="z-index: 80;">
    {{-- The launcher now lives in the top bar (see PomodoroPill / USER_MENU_BEFORE). --}}

    {{-- Panel anchored top-right, directly under the top-bar timer control. --}}
    @if ($showPanel)
        <div
            data-testid="pomodoro-panel"
            class="pointer-events-auto fixed flex flex-col overflow-hidden"
            style="top: 3.5rem; right: 0.75rem; z-index: 80; width: 261px; border-radius: 4px; background-color: #3e3e3e; box-shadow: 0 10px 30px rgba(0,0,0,0.45); color: #ffffff;"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between" style="padding: 12px 14px; background-color: #3e3e3e;">
                <span style="font-size: 17px; font-weight: 700; color: #ffffff;">Pomodoro</span>
                <button type="button" wire:click="togglePanel" title="Collapse" style="color: #cfcfcf;" class="hover:text-white">
                    <x-heroicon-m-chevron-up class="h-5 w-5" />
                </button>
            </div>

            @if ($runningEntryId)
                {{-- Countdown row: label, then big time + Stop button --}}
                <div style="padding: 4px 14px 12px;">
                    <p style="font-size: 12px; font-weight: 600; color: #c9c9c9; margin-bottom: 4px;">Time until break</p>
                    <div class="flex items-center justify-between">
                        <div
                            wire:key="clock-panel-{{ $runningEntryId }}"
                            x-data="{{ $clock($runningStartedAt, $workSeconds) }}"
                            class="font-mono tabular-nums"
                            style="font-size: 40px; line-height: 1; font-weight: 700; color: #ffffff;"
                        >
                            <span x-text="label"></span>
                        </div>
                        <button
                            type="button"
                            wire:click="stop"
                            data-testid="pomodoro-stop-button"
                            class="inline-flex items-center gap-2 transition hover:opacity-90"
                            style="background-color: #4d4d4d; border-radius: 3px; padding: 6px 10px;"
                            title="Stop"
                        >
                            <span style="display: inline-block; width: 22px; height: 22px; border-radius: 2px; background-color: #f15a5a;"></span>
                            <span style="font-size: 20px; font-weight: 700; color: #ffffff;">Stop</span>
                        </button>
                    </div>
                </div>

                {{-- Active task row --}}
                <div class="flex items-center gap-2" style="background-color: #4d4d4d; padding: 7px 14px;">
                    <span style="display: inline-block; width: 11px; height: 11px; border-radius: 9999px; background-color: #f15a5a; flex: none;"></span>
                    <span class="truncate" style="font-size: 13px; font-weight: 700; color: #ffffff;">{{ $runningTaskName }}</span>
                </div>

                {{-- Switch the timer onto whichever task's detail modal is open. --}}
                @if ($openTaskId && $openTaskId !== $runningTaskId)
                    <div class="text-center" style="padding: 8px 14px 0;">
                        <button
                            type="button"
                            wire:click="switchToOpenTask"
                            data-testid="pomodoro-change-task"
                            style="font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: underline; text-underline-offset: 2px;"
                            title="{{ $openTaskName }}"
                        >
                            Change task
                        </button>
                    </div>
                @endif
            @else
                {{-- Idle state, dark themed --}}
                <div style="padding: 4px 14px 14px;">
                    <p style="font-size: 12px; font-weight: 600; color: #c9c9c9; margin-bottom: 4px;">Time until break</p>
                    <div class="font-mono tabular-nums" style="font-size: 40px; line-height: 1; font-weight: 700; color: #6f6f6f;">25:00</div>
                    <p style="font-size: 12px; color: #9a9a9a; margin-top: 8px;">Press ▶ on a task to start.</p>
                </div>
            @endif

            {{-- TODAY summary --}}
            <div class="flex items-center gap-2" style="padding: 10px 14px;">
                <span style="font-size: 12px; font-weight: 700; letter-spacing: 0.03em; color: #ffffff;">TODAY</span>
                <span style="font-size: 12px; color: #dcdcdc;">{{ Format::seconds($this->todaySeconds) }}</span>
                <span style="color: #6f6f6f;">·</span>
                <span style="font-size: 12px; color: #dcdcdc;">{{ $this->todayPomodoros }} {{ \Illuminate\Support\Str::plural('Pomodoro', $this->todayPomodoros) }}</span>
            </div>

            {{-- Session log --}}
            <div class="overflow-y-auto" style="max-height: 176px; background-color: #4d4d4d;">
                @forelse ($this->todayEntries as $entry)
                    <div class="flex items-start gap-2" style="padding: 7px 14px;">
                        <span style="display: inline-block; width: 9px; height: 9px; margin-top: 4px; border-radius: 9999px; flex: none; background-color: {{ $entry->ended_at ? '#00bcd4' : '#f15a5a' }};"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate" style="font-size: 12px; font-weight: 600; color: #ffffff;">{{ $entry->task?->name ?? '—' }}</p>
                            <p class="font-mono" style="font-size: 11px; color: #b8b8b8;">
                                {{ $entry->started_at->format('g:i A') }}
                                @if ($entry->ended_at)
                                    — {{ $entry->ended_at->format('g:i A') }}
                                @else
                                    — <span style="color: #f15a5a;">pending</span>
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <p style="padding: 14px; text-align: center; font-size: 12px; color: #b8b8b8;">No sessions yet today.</p>
                @endforelse
            </div>

            {{-- Footer toolbar (Stopwatch / Add time / Log / Settings — stubs for a later pass) --}}
            <div data-testid="pomodoro-footer" class="flex items-stretch" style="background-color: #2e2e2e;">
                {{-- TODO: wire Stopwatch to a type=stopwatch time entry. --}}
                <button type="button" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Stopwatch (coming soon)">
                    <x-heroicon-m-clock class="h-4 w-4" />
                    <span style="font-size: 10px;">Stopwatch</span>
                </button>
                {{-- TODO: manual "Add time" entry. --}}
                <button type="button" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Add time (coming soon)">
                    <x-heroicon-m-pencil-square class="h-4 w-4" />
                    <span style="font-size: 10px;">Add time</span>
                </button>
                {{-- TODO: full time log view. --}}
                <button type="button" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Log (coming soon)">
                    <x-heroicon-m-queue-list class="h-4 w-4" />
                    <span style="font-size: 10px;">Log</span>
                </button>
                {{-- TODO: timer settings (work/break length). --}}
                <button type="button" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Settings (coming soon)">
                    <x-heroicon-m-cog-6-tooth class="h-4 w-4" />
                    <span style="font-size: 10px;">Settings</span>
                </button>
            </div>
        </div>
    @endif
</div>
