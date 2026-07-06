<?php

namespace App\Livewire;

use App\Models\StopReason;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PomodoroTimer extends Component
{
    /** Default work interval (25 minutes), used by the Alpine countdown. */
    public int $workSeconds = 1500;

    /** Active timer mode: 'pomodoro' (count down) or 'stopwatch' (count up). */
    public string $mode = 'pomodoro';

    public bool $showPanel = false;

    public ?int $runningEntryId = null;

    public ?int $runningTaskId = null;

    public ?string $runningTaskName = null;

    /** ISO-8601 start time handed to Alpine so the countdown resumes after a reload. */
    public ?string $runningStartedAt = null;

    /** Task whose detail modal is currently open on the board (if any). */
    public ?int $openTaskId = null;

    public ?string $openTaskName = null;

    /** Transient warning shown in the idle panel (e.g. "pick a task first"). */
    public ?string $alert = null;

    /** When true, the "Why did you stop?" reason picker is showing. */
    public bool $showStopReasons = false;

    /** True while the inline "Add new reason..." field is open. */
    public bool $addingReason = false;

    /** Bound to the "Add new reason..." text field. */
    public string $newReason = '';

    /** Stop time captured when the picker opened, so a slow pick doesn't inflate the log. */
    public ?string $pendingStopAt = null;

    /** When true, the full "Timer log" modal is showing. */
    public bool $showLog = false;

    /** Timer log period filter. */
    public string $logPeriod = 'this_last_week';

    /** Timer log entry-type filter: 'all', 'pomodoro' or 'stopwatch'. */
    public string $logType = 'all';

    /** When true, the "Add time manually" modal is showing. */
    public bool $showAddTime = false;

    /** Task the manual entry is logged against. */
    public ?int $manualTaskId = null;

    /** Manual entry date (Y-m-d). */
    public string $manualDate = '';

    /** Manual entry start/end clock times (H:i, 24h from <input type="time">). */
    public string $manualFrom = '';

    public string $manualTo = '';

    /** Validation message surfaced inside the Add-time modal. */
    public ?string $manualError = null;

    /** When true, the "Edit color" dialog is showing. */
    public bool $showColorEditor = false;

    /** Row being edited: null = none, 0 = adding a new colour, >0 = project id. */
    public ?int $editColorId = null;

    /** Bound to the colour-row label field. */
    public string $editColorName = '';

    /** Bound to the colour-row swatch picker (a palette key). */
    public string $editColorSwatch = '';

    /** Validation message surfaced inside the Edit-color dialog. */
    public ?string $colorEditorError = null;

    public function mount(): void
    {
        $this->loadRunningEntry();
    }

    /**
     * Re-hydrate the running entry from the database so a page reload resumes
     * the live timer instead of losing it.
     */
    protected function loadRunningEntry(): void
    {
        $entry = TimeEntry::with('task')->running()->latest('started_at')->first();

        if (! $entry) {
            $this->reset(['runningEntryId', 'runningTaskId', 'runningTaskName', 'runningStartedAt']);

            return;
        }

        $this->runningEntryId = $entry->id;
        $this->runningTaskId = $entry->task_id;
        $this->runningTaskName = $entry->task?->name;
        // Resume the countdown from the block's anchor (set when the timer was
        // carried onto this task), falling back to the entry's own start.
        $this->runningStartedAt = ($entry->pomodoro_started_at ?? $entry->started_at)->toIso8601String();

        // Reflect the running session's kind so the display counts the right way.
        if (in_array($entry->type, ['pomodoro', 'stopwatch'], true)) {
            $this->mode = $entry->type;
        }
    }

    #[On('start-pomodoro')]
    public function start(int $taskId): void
    {
        $this->beginEntry($taskId);
    }

    /**
     * Open a running entry for the task. A fresh start anchors the countdown to
     * "now"; a task switch passes $countdownStart so the same pomodoro block
     * keeps ticking onto the new task instead of resetting to 25:00.
     */
    private function beginEntry(int $taskId, ?Carbon $countdownStart = null): void
    {
        // Only one timer runs at a time — close any open entry first. Switching
        // tasks is intentional, so we log it straight away without prompting.
        if ($this->runningEntryId) {
            $this->finalizeRunningEntry();
        }

        $task = Task::findOrFail($taskId);

        $startedAt = now();

        $entry = TimeEntry::create([
            'task_id' => $task->id,
            'type' => $this->mode,
            'started_at' => $startedAt,
            // Null on a fresh start (countdown = started_at); a carried anchor
            // when continuing a block onto a different task.
            'pomodoro_started_at' => $countdownStart,
            'ended_at' => null,
            'seconds' => 0,
        ]);

        $this->runningEntryId = $entry->id;
        $this->runningTaskId = $task->id;
        $this->runningTaskName = $task->name;
        $this->runningStartedAt = ($countdownStart ?? $startedAt)->toIso8601String();
        $this->showPanel = true;
        $this->alert = null;

        // Surface the new running state to the top-bar pill and board badges.
        $this->dispatch('pomodoro-updated');
    }

    /**
     * Start the timer from the panel itself. Uses the task whose detail modal
     * is open; if none is selected, nudges the user to pick one first.
     */
    public function startSelected(): void
    {
        if ($this->openTaskId) {
            $this->start($this->openTaskId);

            return;
        }

        $this->alert = 'Choose a task to start the timer.';
    }

    /**
     * Stop button. Short or naturally-finished sessions are handled silently;
     * a session stopped early pops the "Why did you stop?" picker first.
     */
    public function stop(): void
    {
        $entry = $this->runningEntryId ? TimeEntry::find($this->runningEntryId) : null;

        if (! $entry || $entry->ended_at !== null) {
            $this->resetRunning();

            return;
        }

        $seconds = (int) $entry->started_at->diffInSeconds(now());

        // The countdown is measured from the block's anchor, which predates
        // started_at when the timer was carried onto this task mid-block.
        $blockSeconds = (int) ($entry->pomodoro_started_at ?? $entry->started_at)->diffInSeconds(now());

        // Under 15s gets discarded; a pomodoro that already ran its full work
        // interval finished on its own. Neither needs an explanation.
        if ($seconds < 15 || ($this->mode === 'pomodoro' && $blockSeconds >= $this->workSeconds)) {
            $this->finalizeRunningEntry();

            return;
        }

        // Stopped early: remember when, then ask why before logging.
        $this->pendingStopAt = now()->toIso8601String();
        $this->showStopReasons = true;
    }

    /** A reason was chosen from the "Why did you stop?" picker. */
    public function chooseReason(string $label): void
    {
        if (! $this->showStopReasons) {
            return;
        }

        // "Wrong click" means the session should never have been logged.
        if ($label === 'Wrong click') {
            $entry = $this->runningEntryId ? TimeEntry::find($this->runningEntryId) : null;
            $entry?->delete();
            $this->dispatch('pomodoro-updated');
            $this->resetRunning();
        } else {
            $end = $this->pendingStopAt ? Carbon::parse($this->pendingStopAt) : now();
            $this->finalizeRunningEntry($label, $end);
        }

        $this->closeStopReasons();
    }

    /** Save the typed-in custom reason, then use it to stop the timer. */
    public function addReason(): void
    {
        $label = trim($this->newReason);

        if ($label === '') {
            return;
        }

        StopReason::firstOrCreate(
            ['label' => $label],
            ['position' => (int) StopReason::max('position') + 1],
        );

        $this->chooseReason($label);
    }

    /** Back arrow / dismiss: leave the timer running, just close the picker. */
    public function cancelStopReasons(): void
    {
        $this->closeStopReasons();
    }

    private function closeStopReasons(): void
    {
        $this->showStopReasons = false;
        $this->addingReason = false;
        $this->newReason = '';
        $this->pendingStopAt = null;
    }

    /**
     * Finalize whatever is running: discard if under 15s, otherwise log it with
     * the given reason. Always clears the running state and refreshes badges.
     */
    private function finalizeRunningEntry(?string $reason = null, ?Carbon $end = null): void
    {
        $entry = $this->runningEntryId ? TimeEntry::find($this->runningEntryId) : null;

        if ($entry && $entry->ended_at === null) {
            $end ??= now();
            $seconds = (int) $entry->started_at->diffInSeconds($end);

            // Discard accidental taps — anything under 15s never gets logged.
            if ($seconds < 15) {
                $entry->delete();
            } else {
                $entry->update([
                    'ended_at' => $end,
                    'seconds' => $seconds,
                    'reason' => $reason,
                ]);

                $entry->task?->recalculateSecondsSpent();
            }

            // Tell the board to refresh its time-spent badges.
            $this->dispatch('pomodoro-updated');
        }

        $this->resetRunning();
    }

    private function resetRunning(): void
    {
        $this->reset(['runningEntryId', 'runningTaskId', 'runningTaskName', 'runningStartedAt']);
    }

    /** Board tells us which task's detail modal just opened. */
    #[On('task-opened')]
    public function setOpenTask(int $taskId, ?string $name = null): void
    {
        $this->openTaskId = $taskId;
        $this->openTaskName = $name;
        $this->alert = null;

        // Surface the timer dialog beside the task modal whenever a task is
        // opened. When the open task differs from the running one, the panel
        // also surfaces its "Change task" link.
        $this->showPanel = true;
    }

    /** Board tells us the detail modal closed. */
    #[On('task-closed')]
    public function clearOpenTask(): void
    {
        $this->openTaskId = null;
        $this->openTaskName = null;

        // The panel was surfaced to accompany the modal — collapse it again
        // unless a timer is still running (then it returns to the top bar).
        if (! $this->runningEntryId) {
            $this->showPanel = false;
        }
    }

    /** Move the running timer onto the task whose modal is open. */
    public function switchToOpenTask(): void
    {
        if (! $this->openTaskId || ! $this->runningEntryId) {
            return;
        }

        // Continue the same pomodoro block on the new task: carry the current
        // countdown anchor so the display keeps ticking instead of resetting.
        $current = TimeEntry::find($this->runningEntryId);
        $anchor = $current?->pomodoro_started_at ?? $current?->started_at;

        $this->beginEntry($this->openTaskId, $anchor);
    }

    /** Switch between Pomodoro (count down) and Stopwatch (count up). */
    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['pomodoro', 'stopwatch'], true)) {
            return;
        }

        $this->mode = $mode;

        // Re-tag an in-progress session so its log entry and live display match.
        if ($this->runningEntryId) {
            TimeEntry::where('id', $this->runningEntryId)->update(['type' => $mode]);
            $this->dispatch('pomodoro-updated');
        }
    }

    public function togglePanel(): void
    {
        $this->showPanel = ! $this->showPanel;
    }

    public function openLog(): void
    {
        $this->showLog = true;
    }

    public function closeLog(): void
    {
        $this->showLog = false;
    }

    /**
     * Open the "Add time manually" modal, seeded with sensible defaults: the
     * task in context (open or running), today's date and the current time.
     */
    public function openAddTime(): void
    {
        $this->manualTaskId = $this->openTaskId ?? $this->runningTaskId;
        $this->manualDate = today()->toDateString();
        $this->manualFrom = now()->format('H:i');
        $this->manualTo = now()->format('H:i');
        $this->manualError = null;
        $this->showAddTime = true;
    }

    /**
     * "Add item" in a task's history modal: open the dialog with that task
     * pre-selected. An optional date (from a specific day group) overrides the
     * default of today.
     */
    #[On('open-add-time')]
    public function openAddTimeFor(int $taskId, ?string $date = null): void
    {
        $this->openAddTime();
        $this->manualTaskId = $taskId;

        if ($date) {
            $this->manualDate = $date;
        }
    }

    public function closeAddTime(): void
    {
        $this->showAddTime = false;
        $this->manualError = null;
    }

    /** Validate the modal inputs and log a completed, manually-entered session. */
    public function saveManualEntry(): void
    {
        $this->manualError = null;

        $task = $this->manualTaskId ? Task::find($this->manualTaskId) : null;

        if (! $task) {
            $this->manualError = 'Choose a task.';

            return;
        }

        // The date must be a real calendar day and can't be in the future —
        // you can only log time you've already spent. createFromFormat throws
        // on malformed/trailing input, so guard it and re-check the round-trip
        // to reject anything that isn't an exact Y-m-d.
        try {
            $day = Carbon::createFromFormat('!Y-m-d', $this->manualDate);
        } catch (\Throwable $e) {
            $day = null;
        }

        if (! $day || $day->format('Y-m-d') !== $this->manualDate) {
            $this->manualError = 'Enter a valid date.';

            return;
        }

        if ($day->isAfter(today())) {
            $this->manualError = "The date can't be in the future.";

            return;
        }

        try {
            $start = $day->copy()->setTimeFromTimeString($this->manualFrom);
            $end = $day->copy()->setTimeFromTimeString($this->manualTo);
        } catch (\Throwable $e) {
            $this->manualError = 'Enter a valid time.';

            return;
        }

        $seconds = (int) $start->diffInSeconds($end, false);

        if ($seconds <= 0) {
            $this->manualError = 'The "To" time must be after the "From" time.';

            return;
        }

        // Time is exclusive — refuse a window that overlaps an existing entry.
        // A still-running entry occupies [started_at, now], so coalesce its end.
        $overlaps = TimeEntry::query()
            ->where('started_at', '<', $end->toDateTimeString())
            ->whereRaw('COALESCE(ended_at, ?) > ?', [now()->toDateTimeString(), $start->toDateTimeString()])
            ->exists();

        if ($overlaps) {
            $this->manualError = 'That time range overlaps an existing entry.';

            return;
        }

        TimeEntry::create([
            'task_id' => $task->id,
            'type' => 'manual',
            'started_at' => $start,
            'ended_at' => $end,
            'seconds' => $seconds,
            'reason' => 'Added manually',
        ]);

        $task->recalculateSecondsSpent();
        $this->dispatch('pomodoro-updated');

        $this->showAddTime = false;
    }

    /** Remove an entry from the Timer log and roll its time off the task. */
    public function deleteLogEntry(int $entryId): void
    {
        $entry = TimeEntry::find($entryId);

        if (! $entry) {
            return;
        }

        $task = $entry->task;
        $entry->delete();
        $task?->recalculateSecondsSpent();

        // A running entry can be deleted from the log too — clear the live timer.
        if ($entryId === $this->runningEntryId) {
            $this->resetRunning();
        }

        $this->dispatch('pomodoro-updated');
    }

    /**
     * Completed entries for the Timer log, filtered by period/type and grouped
     * by day (newest day first, entries within a day in start order).
     *
     * @return Collection<int, array{date: Carbon, label: string, seconds: int, pomodoros: int, entries: Collection<int, TimeEntry>}>
     */
    #[Computed]
    public function logDays(): Collection
    {
        $start = match ($this->logPeriod) {
            'today' => today(),
            'this_week' => now()->startOfWeek(),
            'this_last_week' => now()->subWeek()->startOfWeek(),
            'this_month' => now()->startOfMonth(),
            default => null, // all time
        };

        return TimeEntry::with('task')
            ->whereNotNull('ended_at')
            ->when($start, fn ($query) => $query->where('started_at', '>=', $start))
            ->when($this->logType !== 'all', fn ($query) => $query->where('type', $this->logType))
            ->orderBy('started_at')
            ->get()
            ->groupBy(fn (TimeEntry $entry) => $entry->started_at->toDateString())
            ->map(function (Collection $entries, string $date) {
                $day = Carbon::parse($date);

                return [
                    'date' => $day,
                    'label' => $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('l, j F')),
                    'seconds' => (int) $entries->sum('seconds'),
                    'pomodoros' => $entries->where('type', 'pomodoro')->count(),
                    'entries' => $entries,
                ];
            })
            ->sortByDesc(fn (array $day) => $day['date'])
            ->values();
    }

    /** Toggled from the top-bar pill ({@see PomodoroPill}). */
    #[On('toggle-pomodoro')]
    public function toggleFromPill(): void
    {
        $this->togglePanel();
    }

    /** Tasks selectable in the "Add time manually" modal, by name. */
    #[Computed]
    public function manualTaskOptions(): Collection
    {
        return Task::orderBy('name')->get(['id', 'name']);
    }

    /** Configurable "Why did you stop?" reasons, in display order. */
    #[Computed]
    public function stopReasons(): Collection
    {
        return StopReason::orderBy('position')->orderBy('label')->get();
    }

    /** Today's entries, newest first, for the session log. */
    #[Computed]
    public function todayEntries(): Collection
    {
        return TimeEntry::with('task')->today()->latest('started_at')->get();
    }

    /** Total seconds tracked today (running entry excluded until stopped). */
    #[Computed]
    public function todaySeconds(): int
    {
        return (int) TimeEntry::today()->sum('seconds');
    }

    /** Count of completed pomodoros today. */
    #[Computed]
    public function todayPomodoros(): int
    {
        return TimeEntry::today()
            ->where('type', 'pomodoro')
            ->whereNotNull('ended_at')
            ->count();
    }

    // --- "Edit color" dialog (manages the Project colour list) ------------

    /** Projects shown in the Edit-color dialog, with their in-use task counts. */
    #[Computed]
    public function colorRows(): Collection
    {
        return \App\Models\Project::withCount('tasks')->orderBy('name')->get();
    }

    public function openColorEditor(): void
    {
        $this->showColorEditor = true;
        $this->cancelColorRow();
    }

    public function closeColorEditor(): void
    {
        $this->showColorEditor = false;
        $this->cancelColorRow();
    }

    /** Discard any in-progress add/edit and clear the buffer. */
    public function cancelColorRow(): void
    {
        $this->editColorId = null;
        $this->editColorName = '';
        $this->editColorSwatch = '';
        $this->colorEditorError = null;
    }

    /** Begin adding a new colour, pre-selecting the first unused swatch. */
    public function startAddColor(): void
    {
        $used = \App\Models\Project::pluck('color')->all();

        $this->editColorId = 0;
        $this->editColorName = '';
        $this->editColorSwatch = collect(\App\Support\Palette::keys())
            ->first(fn ($key) => ! in_array($key, $used, true)) ?? '';
        $this->colorEditorError = null;
    }

    /** Begin editing an existing colour row. */
    public function startEditColor(int $id): void
    {
        $project = \App\Models\Project::find($id);

        if (! $project) {
            return;
        }

        $this->editColorId = $project->id;
        $this->editColorName = $project->name;
        $this->editColorSwatch = $project->color;
        $this->colorEditorError = null;
    }

    /** Persist the add/edit currently in the buffer. */
    public function saveColorRow(): void
    {
        $name = trim($this->editColorName);

        if ($name === '') {
            $this->colorEditorError = 'Enter a name.';

            return;
        }

        if (! \App\Support\Palette::has($this->editColorSwatch)) {
            $this->colorEditorError = 'Pick a color.';

            return;
        }

        // The swatch doubles as the task ⇄ project join key, so it must be
        // unique across the other colours.
        $taken = \App\Models\Project::where('color', $this->editColorSwatch)
            ->when($this->editColorId, fn ($query) => $query->where('id', '!=', $this->editColorId))
            ->exists();

        if ($taken) {
            $this->colorEditorError = 'That swatch is already used by another color.';

            return;
        }

        if (! $this->editColorId) {
            \App\Models\Project::create([
                'name' => $name,
                'color' => $this->editColorSwatch,
                'is_default' => false,
            ]);
        } else {
            $project = \App\Models\Project::findOrFail($this->editColorId);

            \Illuminate\Support\Facades\DB::transaction(function () use ($project, $name) {
                // Re-point existing tasks so they stay attached when recolouring.
                if ($project->color !== $this->editColorSwatch) {
                    Task::where('color', $project->color)->update(['color' => $this->editColorSwatch]);
                }

                $project->update(['name' => $name, 'color' => $this->editColorSwatch]);
            });
        }

        $this->cancelColorRow();
        $this->dispatch('projects-updated');
    }

    /**
     * Delete a colour row. Tasks keep their colour string, so they still render
     * the correct swatch via the static palette — only their project label is
     * lost (falls back to the raw colour name).
     */
    public function deleteColorRow(int $id): void
    {
        \App\Models\Project::whereKey($id)->delete();

        if ($this->editColorId === $id) {
            $this->cancelColorRow();
        }

        $this->dispatch('projects-updated');
    }

    public function render(): View
    {
        return view('livewire.pomodoro-timer');
    }
}
