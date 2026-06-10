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
        $this->runningStartedAt = $entry->started_at->toIso8601String();

        // Reflect the running session's kind so the display counts the right way.
        if (in_array($entry->type, ['pomodoro', 'stopwatch'], true)) {
            $this->mode = $entry->type;
        }
    }

    #[On('start-pomodoro')]
    public function start(int $taskId): void
    {
        // Only one timer runs at a time — close any open entry first. Switching
        // tasks is intentional, so we log it straight away without prompting.
        if ($this->runningEntryId) {
            $this->finalizeRunningEntry();
        }

        $task = Task::findOrFail($taskId);

        $entry = TimeEntry::create([
            'task_id' => $task->id,
            'type' => $this->mode,
            'started_at' => now(),
            'ended_at' => null,
            'seconds' => 0,
        ]);

        $this->runningEntryId = $entry->id;
        $this->runningTaskId = $task->id;
        $this->runningTaskName = $task->name;
        $this->runningStartedAt = $entry->started_at->toIso8601String();
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

        // Under 15s gets discarded; a pomodoro that already ran its full work
        // interval finished on its own. Neither needs an explanation.
        if ($seconds < 15 || ($this->mode === 'pomodoro' && $seconds >= $this->workSeconds)) {
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
        if ($this->openTaskId) {
            $this->start($this->openTaskId);
        }
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

    /** Toggled from the top-bar pill ({@see PomodoroPill}). */
    #[On('toggle-pomodoro')]
    public function toggleFromPill(): void
    {
        $this->togglePanel();
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

    public function render(): View
    {
        return view('livewire.pomodoro-timer');
    }
}
