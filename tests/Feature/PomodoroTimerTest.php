<?php

use App\Livewire\PomodoroTimer;
use App\Models\Column;
use App\Models\StopReason;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
});

function pomodoroTask(): Task
{
    $column = Column::create(['name' => 'Today', 'position' => 1, 'type' => 'fixed']);

    return Task::create([
        'name' => 'Focus work',
        'color' => 'blue',
        'board_column_id' => $column->id,
        'position' => 0,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);
}

afterEach(fn () => Carbon::setTestNow());

it('starts a running time entry and opens the panel', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->call('start', $task->id)
        ->assertSet('showPanel', true)
        ->assertSet('runningTaskId', $task->id);

    $entry = TimeEntry::sole();

    expect($entry->task_id)->toBe($task->id)
        ->and($entry->type)->toBe('pomodoro')
        ->and($entry->ended_at)->toBeNull()
        ->and($entry->seconds)->toBe(0);
});

it('stops the entry, computes seconds and updates total_seconds_spent', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    // 25 minutes later.
    Carbon::setTestNow('2026-06-02 10:25:00');
    $component->call('stop')
        ->assertSet('runningEntryId', null)
        ->assertDispatched('pomodoro-updated');

    $entry = TimeEntry::sole();

    expect($entry->ended_at)->not->toBeNull()
        ->and($entry->seconds)->toBe(1500)
        ->and($task->fresh()->total_seconds_spent)->toBe(1500);
});

it('discards a session stopped within 15 seconds without asking why', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    // Only 10 seconds in — an accidental tap.
    Carbon::setTestNow('2026-06-02 10:00:10');
    $component->call('stop')
        ->assertSet('runningEntryId', null)
        ->assertSet('showStopReasons', false);

    expect(TimeEntry::count())->toBe(0)
        ->and($task->fresh()->total_seconds_spent)->toBe(0);
});

it('asks why when a pomodoro is stopped early, leaving the timer running', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    // 5 minutes in — interrupted before the break.
    Carbon::setTestNow('2026-06-02 10:05:00');
    $component->call('stop')
        ->assertSet('showStopReasons', true)
        ->assertSet('runningEntryId', fn ($v) => $v !== null);

    // The entry is still running until a reason is chosen.
    expect(TimeEntry::sole()->ended_at)->toBeNull();
});

it('does not ask why when a pomodoro runs its full interval', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    Carbon::setTestNow('2026-06-02 10:25:00');
    $component->call('stop')->assertSet('showStopReasons', false);

    expect(TimeEntry::sole()->ended_at)->not->toBeNull();
});

it('logs the chosen reason and the time captured when stop was pressed', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    Carbon::setTestNow('2026-06-02 10:05:00');
    $component->call('stop');

    // The user takes a few more seconds to pick — should not inflate the log.
    Carbon::setTestNow('2026-06-02 10:05:08');
    $component->call('chooseReason', 'Phone call')
        ->assertSet('showStopReasons', false)
        ->assertSet('runningEntryId', null);

    $entry = TimeEntry::sole();

    expect($entry->reason)->toBe('Phone call')
        ->and($entry->seconds)->toBe(300)
        ->and($task->fresh()->total_seconds_spent)->toBe(300);
});

it('discards the session when the reason is Wrong click', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    Carbon::setTestNow('2026-06-02 10:05:00');
    $component->call('stop')->call('chooseReason', 'Wrong click')
        ->assertSet('showStopReasons', false)
        ->assertSet('runningEntryId', null);

    expect(TimeEntry::count())->toBe(0)
        ->and($task->fresh()->total_seconds_spent)->toBe(0);
});

it('saves a custom reason and uses it to stop the timer', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    Carbon::setTestNow('2026-06-02 10:05:00');
    $component->call('stop')
        ->set('newReason', 'Lunch break')
        ->call('addReason')
        ->assertSet('showStopReasons', false);

    expect(StopReason::where('label', 'Lunch break')->exists())->toBeTrue()
        ->and(TimeEntry::sole()->reason)->toBe('Lunch break');
});

it('keeps the timer running when the reason picker is cancelled', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    Carbon::setTestNow('2026-06-02 10:05:00');
    $component->call('stop')
        ->call('cancelStopReasons')
        ->assertSet('showStopReasons', false)
        ->assertSet('runningEntryId', fn ($v) => $v !== null);

    expect(TimeEntry::sole()->ended_at)->toBeNull();
});

it('stops the previous timer when a new one starts', function () {
    $task = pomodoroTask();
    $other = Task::create([
        'name' => 'Second task',
        'color' => 'red',
        'board_column_id' => $task->board_column_id,
        'position' => 1,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);

    Carbon::setTestNow('2026-06-02 09:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    Carbon::setTestNow('2026-06-02 09:10:00');
    $component->call('start', $other->id);

    expect(TimeEntry::whereNull('ended_at')->count())->toBe(1)
        ->and(TimeEntry::whereNull('ended_at')->value('task_id'))->toBe($other->id)
        ->and($task->fresh()->total_seconds_spent)->toBe(600);
});

it('keeps the countdown when switching tasks mid-pomodoro', function () {
    $task = pomodoroTask();
    $other = Task::create([
        'name' => 'Second task',
        'color' => 'red',
        'board_column_id' => $task->board_column_id,
        'position' => 1,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    // 9 seconds elapsed (the screenshot's 24:51) before switching tasks.
    Carbon::setTestNow('2026-06-02 10:00:09');
    $component->call('setOpenTask', $other->id, $other->name)
        ->call('switchToOpenTask')
        ->assertSet('runningTaskId', $other->id)
        // The countdown anchor stays at the original start, not "now".
        ->assertSet('runningStartedAt', Carbon::parse('2026-06-02 10:00:00')->toIso8601String());

    // The old task is credited the 9s worked on it; the new entry carries the
    // block anchor so its countdown continues from 24:51.
    $new = TimeEntry::whereNull('ended_at')->sole();
    expect($new->task_id)->toBe($other->id)
        ->and($new->started_at->toIso8601String())->toBe(Carbon::parse('2026-06-02 10:00:09')->toIso8601String())
        ->and($new->pomodoro_started_at->toIso8601String())->toBe(Carbon::parse('2026-06-02 10:00:00')->toIso8601String());
});

it('treats a switched pomodoro that runs the full interval as finished', function () {
    $task = pomodoroTask();
    $other = Task::create([
        'name' => 'Second task',
        'color' => 'red',
        'board_column_id' => $task->board_column_id,
        'position' => 1,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    // Switch 5 minutes in, then let the block run out its full 25 minutes.
    Carbon::setTestNow('2026-06-02 10:05:00');
    $component->call('setOpenTask', $other->id, $other->name)->call('switchToOpenTask');

    Carbon::setTestNow('2026-06-02 10:25:00');
    // The new entry is only 20 min long, but the block hit 0:00 — no prompt.
    $component->call('stop')->assertSet('showStopReasons', false);

    expect(TimeEntry::whereNull('ended_at')->count())->toBe(0)
        ->and($task->fresh()->total_seconds_spent)->toBe(300)   // 10:00–10:05
        ->and($other->fresh()->total_seconds_spent)->toBe(1200); // 10:05–10:25
});

it('resumes a carried countdown anchor on mount', function () {
    $task = pomodoroTask();
    $entry = TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'pomodoro',
        'started_at' => now()->subMinute(),
        'pomodoro_started_at' => now()->subMinutes(10),
        'ended_at' => null,
        'seconds' => 0,
    ]);

    Livewire::test(PomodoroTimer::class)
        ->assertSet('runningEntryId', $entry->id)
        ->assertSet('runningStartedAt', $entry->pomodoro_started_at->toIso8601String());
});

it('resumes a running entry on mount so a reload keeps the timer', function () {
    $task = pomodoroTask();
    $entry = TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'pomodoro',
        'started_at' => now()->subMinutes(3),
        'ended_at' => null,
        'seconds' => 0,
    ]);

    Livewire::test(PomodoroTimer::class)
        ->assertSet('runningEntryId', $entry->id)
        ->assertSet('runningTaskId', $task->id)
        ->assertSet('runningTaskName', 'Focus work');
});

it('opens the panel and shows Change task when a different task modal opens', function () {
    $task = pomodoroTask();
    $other = Task::create([
        'name' => 'Other task',
        'color' => 'red',
        'board_column_id' => $task->board_column_id,
        'position' => 1,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);

    Livewire::test(PomodoroTimer::class)
        ->call('start', $task->id)
        ->call('togglePanel') // collapse first to prove setOpenTask re-opens it
        ->assertSet('showPanel', false)
        ->call('setOpenTask', $other->id, $other->name)
        ->assertSet('showPanel', true)
        ->assertSee('Change task')
        ->assertDontSee('Select open task');
});

it('keeps the panel open even when the open task matches the running task', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->call('start', $task->id)
        ->call('togglePanel')
        ->assertSet('showPanel', false)
        ->call('setOpenTask', $task->id, $task->name)
        ->assertSet('showPanel', true)
        ->assertDontSee('Change task');
});

it('logs the session as stopwatch when stopwatch mode is selected', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->call('setMode', 'stopwatch')
        ->assertSet('mode', 'stopwatch')
        ->call('start', $task->id);

    expect(TimeEntry::sole()->type)->toBe('stopwatch');
});

it('re-tags a running session when the mode is toggled mid-run', function () {
    $task = pomodoroTask();

    $component = Livewire::test(PomodoroTimer::class)
        ->call('start', $task->id);

    expect(TimeEntry::sole()->type)->toBe('pomodoro');

    $component->call('setMode', 'stopwatch')
        ->assertSet('mode', 'stopwatch')
        ->assertDispatched('pomodoro-updated');

    expect(TimeEntry::sole()->type)->toBe('stopwatch');
});

it('ignores an unknown mode', function () {
    Livewire::test(PomodoroTimer::class)
        ->call('setMode', 'nonsense')
        ->assertSet('mode', 'pomodoro');
});

it('hydrates the mode from a running stopwatch entry on mount', function () {
    $task = pomodoroTask();
    TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'stopwatch',
        'started_at' => now()->subMinutes(2),
        'ended_at' => null,
        'seconds' => 0,
    ]);

    Livewire::test(PomodoroTimer::class)->assertSet('mode', 'stopwatch');
});

it('does not count stopwatch sessions toward the pomodoro tally', function () {
    $task = pomodoroTask();

    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => now(), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'stopwatch', 'started_at' => now(), 'ended_at' => now(), 'seconds' => 600]);

    $state = Livewire::test(PomodoroTimer::class);

    expect($state->instance()->todaySeconds)->toBe(2100)
        ->and($state->instance()->todayPomodoros)->toBe(1);
});

it('reports today total seconds and completed pomodoro count', function () {
    $task = pomodoroTask();

    // Two completed pomodoros today.
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => now(), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => now(), 'seconds' => 900]);
    // A running one should not count toward seconds or pomodoro count.
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => null, 'seconds' => 0]);

    $state = Livewire::test(PomodoroTimer::class);

    expect($state->instance()->todaySeconds)->toBe(2400)
        ->and($state->instance()->todayPomodoros)->toBe(2);
});

it('opens and closes the timer log modal', function () {
    Livewire::test(PomodoroTimer::class)
        ->call('openLog')
        ->assertSet('showLog', true)
        ->assertSee('Timer log')
        ->call('closeLog')
        ->assertSet('showLog', false);
});

it('groups log entries by day with totals, newest day first', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-12 10:00:00');

    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->subDay()->setTime(9, 0), 'ended_at' => now()->subDay()->setTime(9, 25), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->setTime(8, 0), 'ended_at' => now()->setTime(8, 25), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'stopwatch', 'started_at' => now()->setTime(9, 0), 'ended_at' => now()->setTime(9, 10), 'seconds' => 600]);
    // Running entries stay out of the log until they are stopped.
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => null, 'seconds' => 0]);

    $days = Livewire::test(PomodoroTimer::class)->instance()->logDays;

    expect($days)->toHaveCount(2)
        ->and($days[0]['label'])->toBe('Today')
        ->and($days[0]['seconds'])->toBe(2100)
        ->and($days[0]['pomodoros'])->toBe(1)
        ->and($days[0]['entries'])->toHaveCount(2)
        ->and($days[1]['label'])->toBe('Yesterday')
        ->and($days[1]['seconds'])->toBe(1500);
});

it('filters the log by period and entry type', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-12 10:00:00');

    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->subWeeks(3), 'ended_at' => now()->subWeeks(3)->addMinutes(25), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->setTime(8, 0), 'ended_at' => now()->setTime(8, 25), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'stopwatch', 'started_at' => now()->setTime(9, 0), 'ended_at' => now()->setTime(9, 10), 'seconds' => 600]);

    $component = Livewire::test(PomodoroTimer::class);

    // Default period (this + last week) hides the three-week-old entry.
    expect($component->instance()->logDays->sum(fn ($day) => $day['entries']->count()))->toBe(2);

    $component->set('logPeriod', 'all');
    expect($component->instance()->logDays->sum(fn ($day) => $day['entries']->count()))->toBe(3);

    $component->set('logType', 'stopwatch');
    expect($component->instance()->logDays->sum(fn ($day) => $day['entries']->count()))->toBe(1);
});

it('deletes a log entry and rolls its time off the task', function () {
    $task = pomodoroTask();

    $entry = TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->subHour(), 'ended_at' => now()->subHour()->addMinutes(25), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => now()->addMinutes(15), 'seconds' => 900]);
    $task->recalculateSecondsSpent();

    Livewire::test(PomodoroTimer::class)
        ->call('deleteLogEntry', $entry->id)
        ->assertDispatched('pomodoro-updated');

    expect(TimeEntry::count())->toBe(1)
        ->and($task->fresh()->total_seconds_spent)->toBe(900);
});

it('clears the running timer when its entry is deleted from the log', function () {
    $task = pomodoroTask();

    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);
    $entryId = $component->get('runningEntryId');

    $component->call('deleteLogEntry', $entryId)
        ->assertSet('runningEntryId', null);

    expect(TimeEntry::count())->toBe(0);
});

it('opens the add-time modal seeded with the open task and today', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-12 14:30:00');

    Livewire::test(PomodoroTimer::class)
        ->call('setOpenTask', $task->id, $task->name)
        ->call('openAddTime')
        ->assertSet('showAddTime', true)
        ->assertSet('manualTaskId', $task->id)
        ->assertSet('manualDate', '2026-06-12')
        ->assertSet('manualFrom', '14:30')
        ->assertSet('manualTo', '14:30')
        ->assertSee('Add time manually');
});

it('opens the add-time modal pre-selecting the task from an open-add-time event', function () {
    $task = pomodoroTask();
    $other = Task::create(['name' => 'Other', 'color' => 'red', 'board_column_id' => $task->board_column_id, 'position' => 1, 'date' => today()]);

    Carbon::setTestNow('2026-06-12 14:30:00');

    Livewire::test(PomodoroTimer::class)
        ->call('setOpenTask', $other->id, $other->name)
        ->dispatch('open-add-time', taskId: $task->id)
        ->assertSet('showAddTime', true)
        ->assertSet('manualTaskId', $task->id)
        ->assertSet('manualDate', '2026-06-12');
});

it('pre-fills the date when open-add-time carries a day', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-12 14:30:00');

    Livewire::test(PomodoroTimer::class)
        ->dispatch('open-add-time', taskId: $task->id, date: '2026-06-16')
        ->assertSet('showAddTime', true)
        ->assertSet('manualTaskId', $task->id)
        ->assertSet('manualDate', '2026-06-16');
});

it('logs a completed manual entry and updates total_seconds_spent', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->set('manualTaskId', $task->id)
        ->set('manualDate', '2026-06-12')
        ->set('manualFrom', '09:00')
        ->set('manualTo', '10:30')
        ->call('saveManualEntry')
        ->assertSet('showAddTime', false)
        ->assertDispatched('pomodoro-updated');

    $entry = TimeEntry::sole();

    expect($entry->type)->toBe('manual')
        ->and($entry->seconds)->toBe(5400)
        ->and($entry->reason)->toBe('Added manually')
        ->and($entry->started_at->format('Y-m-d H:i'))->toBe('2026-06-12 09:00')
        ->and($entry->ended_at->format('Y-m-d H:i'))->toBe('2026-06-12 10:30')
        ->and($task->fresh()->total_seconds_spent)->toBe(5400);
});

it('rejects a manual entry with an invalid date', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->call('openAddTime')
        ->set('manualTaskId', $task->id)
        ->set('manualDate', 'not-a-date')
        ->set('manualFrom', '09:00')
        ->set('manualTo', '10:00')
        ->call('saveManualEntry')
        ->assertSet('showAddTime', true)
        ->assertSet('manualError', 'Enter a valid date.');

    expect(TimeEntry::count())->toBe(0);
});

it('rejects a manual entry with a malformed date without erroring', function () {
    $task = pomodoroTask();

    // Values like this pass a loose format check but make Carbon::createFromFormat
    // throw on the trailing data — the guard must catch it, not 500.
    foreach (['2026-07-06x', '2026-13-01', '2026-07', ''] as $bad) {
        Livewire::test(PomodoroTimer::class)
            ->call('openAddTime')
            ->set('manualTaskId', $task->id)
            ->set('manualDate', $bad)
            ->set('manualFrom', '09:00')
            ->set('manualTo', '10:00')
            ->call('saveManualEntry')
            ->assertSet('showAddTime', true)
            ->assertSet('manualError', 'Enter a valid date.');
    }

    expect(TimeEntry::count())->toBe(0);
});

it('rejects a manual entry dated in the future', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-12 14:30:00');

    Livewire::test(PomodoroTimer::class)
        ->call('openAddTime')
        ->set('manualTaskId', $task->id)
        ->set('manualDate', '2026-06-13')
        ->set('manualFrom', '09:00')
        ->set('manualTo', '10:00')
        ->call('saveManualEntry')
        ->assertSet('showAddTime', true)
        ->assertSet('manualError', "The date can't be in the future.");

    expect(TimeEntry::count())->toBe(0);
});

it('allows a manual entry dated today', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-12 14:30:00');

    Livewire::test(PomodoroTimer::class)
        ->call('openAddTime')
        ->set('manualTaskId', $task->id)
        ->set('manualDate', '2026-06-12')
        ->set('manualFrom', '09:00')
        ->set('manualTo', '10:00')
        ->call('saveManualEntry')
        ->assertSet('showAddTime', false);

    expect(TimeEntry::count())->toBe(1);
});

it('rejects a manual entry whose end is not after its start', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->call('openAddTime')
        ->set('manualTaskId', $task->id)
        ->set('manualDate', '2026-06-12')
        ->set('manualFrom', '10:00')
        ->set('manualTo', '10:00')
        ->call('saveManualEntry')
        ->assertSet('showAddTime', true)
        ->assertSet('manualError', fn ($v) => $v !== null);

    expect(TimeEntry::count())->toBe(0);
});

it('rejects a manual entry that overlaps an existing entry', function () {
    $task = pomodoroTask();

    TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'pomodoro',
        'started_at' => Carbon::parse('2026-06-12 09:00'),
        'ended_at' => Carbon::parse('2026-06-12 09:25'),
        'seconds' => 1500,
    ]);

    Livewire::test(PomodoroTimer::class)
        ->call('openAddTime')
        ->set('manualTaskId', $task->id)
        ->set('manualDate', '2026-06-12')
        ->set('manualFrom', '09:10')
        ->set('manualTo', '10:00')
        ->call('saveManualEntry')
        ->assertSet('showAddTime', true) // stays open so the user can fix it
        ->assertSet('manualError', 'That time range overlaps an existing entry.');

    expect(TimeEntry::count())->toBe(1);
});

it('allows a manual entry that abuts an existing one without overlapping', function () {
    $task = pomodoroTask();

    TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'pomodoro',
        'started_at' => Carbon::parse('2026-06-12 09:00'),
        'ended_at' => Carbon::parse('2026-06-12 09:25'),
        'seconds' => 1500,
    ]);

    Livewire::test(PomodoroTimer::class)
        ->call('openAddTime')
        ->set('manualTaskId', $task->id)
        ->set('manualDate', '2026-06-12')
        ->set('manualFrom', '09:25') // starts exactly when the other ends
        ->set('manualTo', '10:00')
        ->call('saveManualEntry')
        ->assertSet('showAddTime', false)
        ->assertSet('manualError', null);

    expect(TimeEntry::count())->toBe(2);
});

it('rejects a manual entry with no task chosen', function () {
    Livewire::test(PomodoroTimer::class)
        ->set('manualTaskId', null)
        ->set('manualDate', '2026-06-12')
        ->set('manualFrom', '09:00')
        ->set('manualTo', '10:00')
        ->call('saveManualEntry')
        ->assertSet('manualError', 'Choose a task.');

    expect(TimeEntry::count())->toBe(0);
});
