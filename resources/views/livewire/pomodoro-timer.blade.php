@php
    use App\Support\Format;

    // Reusable Alpine clock. `start` is an ISO string or null; `mode` is
    // 'pomodoro' (count down to 0) or 'stopwatch' (count up indefinitely).
    $clock = fn (?string $start, int $work, string $mode = 'pomodoro') => "{
        startedAt: " . ($start ? "'{$start}'" : 'null') . ",
        work: {$work},
        mode: '{$mode}',
        notified: false,
        now: Math.floor(Date.now() / 1000),
        timer: null,
        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
            if (this.mode === 'pomodoro' && window.Notification && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },
        destroy() { clearInterval(this.timer); },
        tick() {
            this.now = Math.floor(Date.now() / 1000);
            // Fire the break notification once, the moment a pomodoro hits 0:00.
            if (this.mode === 'pomodoro' && this.startedAt && this.remaining <= 0 && ! this.notified) {
                this.notified = true;
                this.fireNotification();
            }
        },
        get elapsed() {
            if (! this.startedAt) return 0;
            return Math.max(0, this.now - Math.floor(new Date(this.startedAt).getTime() / 1000));
        },
        get remaining() { return Math.max(0, this.work - this.elapsed); },
        // A finished pomodoro sits at 0:00 and flashes until the user stops it.
        get finished() { return this.mode === 'pomodoro' && !! this.startedAt && this.remaining <= 0; },
        fireNotification() {
            try {
                if (window.Notification && Notification.permission === 'granted') {
                    new Notification('Pomodoro finished', { body: 'Time for a break — press Stop to log it.' });
                }
            } catch (e) {}
        },
        fmt(s) {
            const m = Math.floor(s / 60).toString().padStart(2, '0');
            const sec = (s % 60).toString().padStart(2, '0');
            return m + ':' + sec;
        },
        get label() {
            if (! this.startedAt) return this.mode === 'pomodoro' ? this.fmt(this.work) : this.fmt(0);
            return this.mode === 'pomodoro' ? this.fmt(this.remaining) : this.fmt(this.elapsed);
        },
    }";
@endphp

<div class="pointer-events-none fixed inset-0" style="z-index: 80;">
    {{-- The launcher now lives in the top bar (see PomodoroPill / USER_MENU_BEFORE). --}}

    {{-- Slow flash for a finished pomodoro (Tailwind animate-* utilities are absent in Filament CSS). --}}
    <style>
        @keyframes pomodoroFlash { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }
        .pomodoro-flash { animation: pomodoroFlash 1.6s ease-in-out infinite; }
    </style>

    {{--
        Panel position: when a task's detail modal is open it docks just left
        of the centred modal (clamped so it never slides off-screen); otherwise
        it anchors top-right, directly under the top-bar timer control.
    --}}
    @php
        // Docked beside the modal: the detail modal is nudged +140px right of
        // centre (see task-detail-modal), so the panel's left edge lands at
        // 50% + 140 − 613 = 50% − 473px, leaving a 16px gutter to the modal.
        $panelPosition = $openTaskId
            ? 'top: 50%; left: max(0.5rem, calc(50% - 473px)); transform: translateY(-50%);'
            : 'top: 3.5rem; right: 0.75rem;';
    @endphp
    @if ($showPanel)
        <div
            data-testid="pomodoro-panel"
            class="pointer-events-auto fixed flex flex-col overflow-hidden"
            style="{{ $panelPosition }} z-index: 80; width: 261px; border-radius: 4px; background-color: #3e3e3e; box-shadow: 0 10px 30px rgba(0,0,0,0.45); color: #ffffff;"
        >
        @if ($showStopReasons)
            {{-- "Why did you stop?" picker — shown when a session is stopped early. --}}
            <div data-testid="pomodoro-reason-picker">
                {{-- Header with a back arrow that cancels (timer keeps running). --}}
                <div class="flex items-center gap-2" style="padding: 12px 14px;">
                    <button type="button" wire:click="cancelStopReasons" title="Back" style="color: #cfcfcf;" class="hover:text-white">
                        <x-heroicon-m-chevron-left class="h-5 w-5" />
                    </button>
                    <span style="font-size: 15px; font-weight: 700; color: #ffffff;">Why did you stop?</span>
                </div>

                <div class="overflow-y-auto" style="max-height: 360px;">
                    @foreach ($this->stopReasons as $reason)
                        <button
                            type="button"
                            wire:click="chooseReason(@js($reason->label))"
                            data-testid="pomodoro-reason"
                            class="block w-full text-left hover:bg-white/10"
                            style="padding: 11px 14px; font-size: 14px; color: #ffffff; border-top: 1px solid rgba(255,255,255,0.06);"
                        >
                            {{ $reason->label }}
                        </button>
                    @endforeach

                    {{-- Add new reason: toggles an inline field, then stops with it. --}}
                    @if ($addingReason)
                        <div class="flex items-center gap-2" style="padding: 9px 14px; border-top: 1px solid rgba(255,255,255,0.06);">
                            <input
                                type="text"
                                wire:model="newReason"
                                wire:keydown.enter.prevent="addReason"
                                data-testid="pomodoro-reason-input"
                                placeholder="New reason"
                                autofocus
                                class="min-w-0 flex-1"
                                style="background-color: #2e2e2e; color: #ffffff; border: 1px solid rgba(255,255,255,0.15); border-radius: 3px; padding: 5px 8px; font-size: 13px;"
                            />
                            <button type="button" wire:click="addReason" style="background-color: #4ac26b; color: #ffffff; border-radius: 3px; padding: 5px 10px; font-size: 13px; font-weight: 700;">Save</button>
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="$set('addingReason', true)"
                            class="block w-full text-left hover:bg-white/10"
                            style="padding: 11px 14px; font-size: 14px; color: #b8b8b8; border-top: 1px solid rgba(255,255,255,0.06);"
                        >
                            Add new reason&hellip;
                        </button>
                    @endif

                    {{-- "Task done" — emphasized, logs the session as completed work. --}}
                    <button
                        type="button"
                        wire:click="chooseReason('Task done')"
                        data-testid="pomodoro-reason-done"
                        class="block w-full text-left hover:bg-white/10"
                        style="padding: 11px 14px; font-size: 14px; font-weight: 700; color: #ffffff; border-top: 1px solid rgba(255,255,255,0.12);"
                    >
                        Task done
                    </button>
                </div>
            </div>
        @else
            {{-- Header --}}
            <div class="flex items-center justify-between" style="padding: 12px 14px; background-color: #3e3e3e;">
                <span style="font-size: 17px; font-weight: 700; color: #ffffff;">{{ $mode === 'stopwatch' ? 'Stopwatch' : 'Pomodoro' }}</span>
                <button type="button" wire:click="togglePanel" title="Collapse" style="color: #cfcfcf;" class="hover:text-white">
                    <x-heroicon-m-chevron-up class="h-5 w-5" />
                </button>
            </div>

            @if ($runningEntryId)
                {{-- Countdown row: label, then big time + Stop button --}}
                <div style="padding: 4px 14px 12px;">
                    <p style="font-size: 12px; font-weight: 600; color: #c9c9c9; margin-bottom: 4px;">{{ $mode === 'stopwatch' ? 'Session time' : 'Time until break' }}</p>
                    <div class="flex items-center justify-between">
                        <div
                            wire:key="clock-panel-{{ $runningEntryId }}-{{ $mode }}"
                            x-data="{{ $clock($runningStartedAt, $workSeconds, $mode) }}"
                            class="font-mono tabular-nums"
                            :class="{ 'pomodoro-flash': finished }"
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
                    <p style="font-size: 12px; font-weight: 600; color: #c9c9c9; margin-bottom: 4px;">{{ $mode === 'stopwatch' ? 'Session time' : 'Time until break' }}</p>
                    <div class="flex items-center justify-between">
                        <div class="font-mono tabular-nums" style="font-size: 40px; line-height: 1; font-weight: 700; color: #6f6f6f;">{{ $mode === 'stopwatch' ? '00:00' : '25:00' }}</div>
                        <button
                            type="button"
                            wire:click="startSelected"
                            data-testid="pomodoro-start-button"
                            class="inline-flex items-center gap-2 transition hover:opacity-90"
                            style="background-color: #4d4d4d; border-radius: 3px; padding: 6px 10px;"
                            title="Start"
                        >
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 2px; background-color: #4ac26b;">
                                <x-heroicon-m-play class="h-4 w-4" style="color: #ffffff;" />
                            </span>
                            <span style="font-size: 20px; font-weight: 700; color: #ffffff;">Start</span>
                        </button>
                    </div>
                    @if ($alert)
                        <p data-testid="pomodoro-alert" style="font-size: 12px; color: #f15a5a; margin-top: 8px;">{{ $alert }}</p>
                    @elseif ($openTaskId)
                        <p class="truncate" style="font-size: 12px; color: #9a9a9a; margin-top: 8px;" title="{{ $openTaskName }}">Start the timer for &ldquo;{{ $openTaskName }}&rdquo;.</p>
                    @else
                        <p style="font-size: 12px; color: #9a9a9a; margin-top: 8px;">Press Start, or &#9654; on a task.</p>
                    @endif
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
                {{-- Toggle: shows the other mode and switches to it (re-tags a live session). --}}
                @php $altMode = $mode === 'stopwatch' ? 'pomodoro' : 'stopwatch'; @endphp
                <button type="button" wire:click="setMode('{{ $altMode }}')" data-testid="pomodoro-mode-toggle" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Switch to {{ ucfirst($altMode) }}">
                    <x-heroicon-m-clock class="h-4 w-4" />
                    <span style="font-size: 10px;">{{ ucfirst($altMode) }}</span>
                </button>
                {{-- Manual "Add time" entry. --}}
                <button type="button" wire:click="openAddTime" data-testid="pomodoro-add-time-button" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Add time manually">
                    <x-heroicon-m-pencil-square class="h-4 w-4" />
                    <span style="font-size: 10px;">Add time</span>
                </button>
                <button type="button" wire:click="openLog" data-testid="pomodoro-log-button" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Log">
                    <x-heroicon-m-queue-list class="h-4 w-4" />
                    <span style="font-size: 10px;">Log</span>
                </button>
                {{-- TODO: timer settings (work/break length). --}}
                <button type="button" class="flex flex-1 flex-col items-center gap-1 hover:bg-white/5" style="padding: 8px 0; color: #dcdcdc;" title="Settings (coming soon)">
                    <x-heroicon-m-cog-6-tooth class="h-4 w-4" />
                    <span style="font-size: 10px;">Settings</span>
                </button>
            </div>
        @endif
        </div>
    @endif

    {{-- Timer log modal (Log button in the footer toolbar) --}}
    @if ($showLog)
        @php
            // Same accent colours as the board palette (task-board.blade.php).
            $dots = [
                'white' => '#9ca3af', 'yellow' => '#f59e0b', 'green' => '#22c55e',
                'blue' => '#3b9fd6', 'purple' => '#8b5cf6', 'red' => '#ef4444',
                'orange' => '#f97316', 'magenta' => '#ec4899', 'cyan' => '#06b6d4',
                'brown' => '#8d6e63',
            ];
        @endphp
        <div
            data-testid="timer-log-modal"
            class="pointer-events-auto fixed inset-0 flex items-start justify-center overflow-y-auto"
            style="z-index: 90; background-color: rgba(0, 0, 0, 0.45); padding: 24px 16px; overscroll-behavior: contain;"
            wire:click.self="closeLog"
            x-on:keydown.escape.window="$wire.closeLog()"
            {{-- Lock the page behind the modal so the wheel scrolls the log, not the board. --}}
            x-data="{
                prevHtml: '',
                prevBody: '',
                init() {
                    this.prevHtml = document.documentElement.style.overflow;
                    this.prevBody = document.body.style.overflow;
                    document.documentElement.style.overflow = 'hidden';
                    document.body.style.overflow = 'hidden';
                },
                destroy() {
                    document.documentElement.style.overflow = this.prevHtml;
                    document.body.style.overflow = this.prevBody;
                },
            }"
        >
            <div class="flex w-full flex-col bg-white" style="max-width: 640px; max-height: calc(100vh - 48px); border-radius: 6px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); color: #1f2937;">
                {{-- Header --}}
                <div class="relative flex-none" style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb;">
                    <h2 class="text-center" style="font-size: 16px; font-weight: 700;">Timer log</h2>
                    <button type="button" wire:click="closeLog" class="absolute hover:text-gray-700" style="top: 14px; right: 14px; color: #9ca3af;" title="Close">
                        <x-heroicon-m-x-mark class="h-5 w-5" />
                    </button>
                </div>

                {{-- Filters --}}
                <div class="flex flex-none flex-wrap items-center gap-2" style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb;">
                    <select
                        wire:model.live="logPeriod"
                        data-testid="timer-log-period"
                        style="border: 1px solid #d1d5db; border-radius: 4px; padding: 5px 28px 5px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff;"
                    >
                        <option value="today">Period: Today</option>
                        <option value="this_week">Period: This week</option>
                        <option value="this_last_week">Period: This + Last week</option>
                        <option value="this_month">Period: This month</option>
                        <option value="all">Period: All time</option>
                    </select>
                    <select
                        wire:model.live="logType"
                        data-testid="timer-log-type"
                        style="border: 1px solid #d1d5db; border-radius: 4px; padding: 5px 28px 5px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff;"
                    >
                        <option value="all">Entry type: All</option>
                        <option value="pomodoro">Entry type: Pomodoro</option>
                        <option value="stopwatch">Entry type: Stopwatch</option>
                    </select>
                </div>

                {{-- Day groups --}}
                <div class="min-h-0 flex-1 overflow-y-auto" style="padding: 12px 16px; background-color: #f3f4f6; overscroll-behavior: contain;">
                    @forelse ($this->logDays as $day)
                        <div class="bg-white" style="border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 12px;">
                            {{-- Day header: label + totals --}}
                            <div class="flex items-baseline gap-4" style="padding: 10px 14px; border-bottom: 1px solid #e5e7eb;">
                                <span style="font-size: 16px; font-weight: 700;">{{ $day['label'] }}</span>
                                <span style="font-size: 12px; font-weight: 600; color: #6b7280;">{{ Format::seconds($day['seconds']) }}</span>
                                <span style="font-size: 12px; font-weight: 600; color: #6b7280;">{{ $day['pomodoros'] }} {{ \Illuminate\Support\Str::plural('Pomodoro', $day['pomodoros']) }}</span>
                            </div>

                            @foreach ($day['entries'] as $entry)
                                @php
                                    $successful = $entry->type === 'pomodoro' && $entry->seconds >= $workSeconds;
                                    $sublabel = $successful
                                        ? 'Successful Pomodoro'
                                        : ($entry->reason ?? ($entry->type === 'stopwatch' ? 'Stopwatch' : null));
                                @endphp
                                <div class="group flex items-start gap-2" style="padding: 9px 14px; border-top: 1px solid #f3f4f6;" wire:key="log-entry-{{ $entry->id }}">
                                    <span style="display: inline-block; width: 10px; height: 10px; margin-top: 4px; border-radius: 9999px; flex: none; background-color: {{ $dots[$entry->task?->color] ?? '#9ca3af' }};"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate" style="font-size: 13px; font-weight: 700;">{{ $entry->task?->name ?? '—' }}</p>
                                        @if ($sublabel)
                                            <p style="font-size: 12px; font-style: italic; color: {{ $successful ? '#15803d' : '#6b7280' }};">{{ $sublabel }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-none items-center gap-3" style="padding-top: 1px;">
                                        <span class="font-mono" style="font-size: 12px; color: #374151;">{{ $entry->started_at->format('g:i A') }} - {{ $entry->ended_at->format('g:i A') }}</span>
                                        <span class="font-mono" style="font-size: 12px; color: #374151; min-width: 32px; text-align: right;">{{ Format::seconds($entry->seconds) }}</span>
                                        <button
                                            type="button"
                                            wire:click="deleteLogEntry({{ $entry->id }})"
                                            wire:confirm="Delete this time entry?"
                                            class="opacity-0 hover:!text-red-600 group-hover:opacity-100"
                                            style="color: #9ca3af;"
                                            title="Delete entry"
                                        >
                                            <x-heroicon-m-trash class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="bg-white text-center" style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 24px; font-size: 13px; color: #6b7280;">No time entries in this period.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- Add time manually modal (Add time button in the footer toolbar) --}}
    @if ($showAddTime)
        <div
            data-testid="add-time-modal"
            class="pointer-events-auto fixed inset-0 flex items-start justify-center overflow-y-auto"
            style="z-index: 90; background-color: rgba(0, 0, 0, 0.45); padding: 48px 16px; overscroll-behavior: contain;"
            wire:click.self="closeAddTime"
            x-on:keydown.escape.window="$wire.closeAddTime()"
            x-data="{
                taskId: @js($manualTaskId),
                date: @js($manualDate),
                from: @js($manualFrom),
                to: @js($manualTo),
                fromText: '',
                toText: '',
                init() {
                    this.fromText = this.fmt12(this.from);
                    this.toText = this.fmt12(this.to);
                },
                {{-- Parse free text (1401, 14:01, 2:01pm, 9, 930a) into 24h 'HH:MM', or null. --}}
                parse(raw) {
                    if (! raw) return null;
                    const s = String(raw).trim().toLowerCase();
                    let ampm = null;
                    if (s.includes('am') || /a$/.test(s)) ampm = 'am';
                    if (s.includes('pm') || /p$/.test(s)) ampm = 'pm';
                    let h, m;
                    if (s.includes(':')) {
                        const parts = s.split(':');
                        h = parseInt(parts[0].replace(/[^0-9]/g, ''), 10);
                        m = parseInt((parts[1] || '').replace(/[^0-9]/g, ''), 10) || 0;
                    } else {
                        const d = s.replace(/[^0-9]/g, '');
                        if (! d) return null;
                        if (d.length <= 2) { h = parseInt(d, 10); m = 0; }
                        else if (d.length === 3) { h = parseInt(d.slice(0, 1), 10); m = parseInt(d.slice(1), 10); }
                        else { h = parseInt(d.slice(0, d.length - 2), 10); m = parseInt(d.slice(-2), 10); }
                    }
                    if (Number.isNaN(h) || Number.isNaN(m)) return null;
                    if (ampm === 'pm' && h < 12) h += 12;
                    if (ampm === 'am' && h === 12) h = 0;
                    if (h > 23) h = 23;
                    if (m > 59) m = 59;
                    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                },
                {{-- 24h 'HH:MM' → '02:01 PM' for display. --}}
                fmt12(hhmm) {
                    if (! hhmm) return '';
                    const [h, m] = hhmm.split(':').map(Number);
                    if (Number.isNaN(h) || Number.isNaN(m)) return '';
                    let hh = h % 12; if (hh === 0) hh = 12;
                    const ap = h < 12 ? 'AM' : 'PM';
                    return String(hh).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ' ' + ap;
                },
                {{-- On blur: normalize the typed text, keeping the last valid value if it can't be parsed. --}}
                norm(which) {
                    if (which === 'from') {
                        const v = this.parse(this.fromText);
                        if (v) this.from = v;
                        this.fromText = this.fmt12(this.from);
                    } else {
                        const v = this.parse(this.toText);
                        if (v) this.to = v;
                        this.toText = this.fmt12(this.to);
                    }
                },
                get durationLabel() {
                    const [fh, fm] = (this.from || '').split(':').map(Number);
                    const [th, tm] = (this.to || '').split(':').map(Number);
                    if ([fh, fm, th, tm].some((n) => Number.isNaN(n))) return '0h';
                    let mins = (th * 60 + tm) - (fh * 60 + fm);
                    if (mins <= 0) return '0h';
                    const h = Math.floor(mins / 60), m = mins % 60;
                    return [h ? h + 'h' : '', m ? m + 'm' : ''].filter(Boolean).join(' ') || '0h';
                },
                save() {
                    this.norm('from');
                    this.norm('to');
                    $wire.set('manualTaskId', this.taskId ? Number(this.taskId) : null, false);
                    $wire.set('manualDate', this.date, false);
                    $wire.set('manualFrom', this.from, false);
                    $wire.set('manualTo', this.to, false);
                    $wire.saveManualEntry();
                },
            }"
        >
            <div class="flex w-full flex-col bg-white" style="max-width: 360px; border-radius: 6px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); color: #1f2937;">
                {{-- Header --}}
                <div class="relative flex-none" style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb;">
                    <h2 class="text-center" style="font-size: 16px; font-weight: 700;">Add time manually</h2>
                    <button type="button" wire:click="closeAddTime" class="absolute hover:text-gray-700" style="top: 14px; right: 14px; color: #9ca3af;" title="Close">
                        <x-heroicon-m-x-mark class="h-5 w-5" />
                    </button>
                </div>

                {{-- Form --}}
                <div style="padding: 16px;">
                    {{-- Task --}}
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Task</label>
                    <select
                        x-model="taskId"
                        data-testid="add-time-task"
                        style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 7px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff; margin-bottom: 12px;"
                    >
                        <option value="">Choose a task&hellip;</option>
                        @foreach ($this->manualTaskOptions as $task)
                            <option value="{{ $task->id }}">{{ $task->name }}</option>
                        @endforeach
                    </select>

                    {{-- Date --}}
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Date</label>
                    <input
                        type="date"
                        x-model="date"
                        data-testid="add-time-date"
                        style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 7px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff; margin-bottom: 12px;"
                    />

                    {{-- From / To: free text (1401, 14:01, 2:01pm) normalized to "02:01 PM" on blur. --}}
                    <div class="flex gap-3" style="margin-bottom: 12px;">
                        <div class="flex-1">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">From</label>
                            <input
                                type="text"
                                x-model="fromText"
                                x-on:blur="norm('from')"
                                x-on:keydown.enter.prevent="norm('from')"
                                inputmode="numeric"
                                placeholder="e.g. 1401"
                                data-testid="add-time-from"
                                style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 7px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff;"
                            />
                        </div>
                        <div class="flex-1">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">To</label>
                            <input
                                type="text"
                                x-model="toText"
                                x-on:blur="norm('to')"
                                x-on:keydown.enter.prevent="norm('to')"
                                inputmode="numeric"
                                placeholder="e.g. 1430"
                                data-testid="add-time-to"
                                style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 7px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff;"
                            />
                        </div>
                    </div>

                    {{-- Duration (derived from From / To) --}}
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Duration</label>
                    <div
                        data-testid="add-time-duration"
                        class="text-center"
                        style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 7px 8px; font-size: 13px; color: #1f2937; background-color: #f9fafb;"
                        x-text="durationLabel"
                    ></div>

                    @if ($manualError)
                        <p data-testid="add-time-error" style="font-size: 12px; color: #dc2626; margin-top: 10px;">{{ $manualError }}</p>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex justify-end" style="padding: 12px 16px; border-top: 1px solid #e5e7eb;">
                    <button
                        type="button"
                        x-on:click="save()"
                        data-testid="add-time-save"
                        style="background-color: #4ac26b; color: #ffffff; border-radius: 4px; padding: 8px 24px; font-size: 14px; font-weight: 700;"
                        class="hover:opacity-90"
                    >
                        Add
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
