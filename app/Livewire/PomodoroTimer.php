<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PomodoroTimer extends Component
{
    /** Default work interval (25 minutes), used by the Alpine countdown. */
    public int $workSeconds = 1500;

    public bool $showPanel = false;

    public ?int $runningEntryId = null;

    public ?int $runningTaskId = null;

    public ?string $runningTaskName = null;

    /** ISO-8601 start time handed to Alpine so the countdown resumes after a reload. */
    public ?string $runningStartedAt = null;

    /** Task whose detail modal is currently open on the board (if any). */
    public ?int $openTaskId = null;

    public ?string $openTaskName = null;

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
    }

    #[On('start-pomodoro')]
    public function start(int $taskId): void
    {
        // Only one timer runs at a time — close any open entry first.
        if ($this->runningEntryId) {
            $this->stop();
        }

        $task = Task::findOrFail($taskId);

        $entry = TimeEntry::create([
            'task_id' => $task->id,
            'type' => 'pomodoro',
            'started_at' => now(),
            'ended_at' => null,
            'seconds' => 0,
        ]);

        $this->runningEntryId = $entry->id;
        $this->runningTaskId = $task->id;
        $this->runningTaskName = $task->name;
        $this->runningStartedAt = $entry->started_at->toIso8601String();
        $this->showPanel = true;

        // Surface the new running state to the top-bar pill and board badges.
        $this->dispatch('pomodoro-updated');
    }

    public function stop(): void
    {
        $entry = $this->runningEntryId ? TimeEntry::find($this->runningEntryId) : null;

        if ($entry && $entry->ended_at === null) {
            $end = now();

            $entry->update([
                'ended_at' => $end,
                'seconds' => (int) $entry->started_at->diffInSeconds($end),
            ]);

            $entry->task?->recalculateSecondsSpent();

            // Tell the board to refresh its time-spent badges.
            $this->dispatch('pomodoro-updated');
        }

        $this->reset(['runningEntryId', 'runningTaskId', 'runningTaskName', 'runningStartedAt']);
    }

    /** Board tells us which task's detail modal just opened. */
    #[On('task-opened')]
    public function setOpenTask(int $taskId, ?string $name = null): void
    {
        $this->openTaskId = $taskId;
        $this->openTaskName = $name;

        // Keep the timer visible beside the task modal whenever one is running.
        // When the open task differs, the panel also surfaces its "Change task" link.
        if ($this->runningEntryId) {
            $this->showPanel = true;
        }
    }

    /** Board tells us the detail modal closed. */
    #[On('task-closed')]
    public function clearOpenTask(): void
    {
        $this->openTaskId = null;
        $this->openTaskName = null;
    }

    /** Move the running timer onto the task whose modal is open. */
    public function switchToOpenTask(): void
    {
        if ($this->openTaskId) {
            $this->start($this->openTaskId);
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
