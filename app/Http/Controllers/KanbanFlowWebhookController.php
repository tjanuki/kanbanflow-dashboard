<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KanbanFlowWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $data = $request->all();

        if (! isset($data['task'])) {
            return response()->json(['message' => 'No task data found']);
        }

        if ($data['task']['totalSecondsSpent'] === 0) {
            return response()->json(['message' => 'Task has no time spent']);
        }

        // Create or update the task
        $task = Task::updateOrCreate(
            ['kanbanflow_task_id' => $data['task']['_id']],
            [
                'name' => $data['task']['name'],
                'description' => $data['task']['description'],
                'color' => $data['task']['color'],
                'column_id' => $data['task']['columnId'],
                'total_seconds_spent' => $data['task']['totalSecondsSpent'],
                'total_seconds_estimate' => $data['task']['totalSecondsEstimate'],
                'changed_properties' => json_encode($data['changedProperties'] ?? []),
            ]
        );

        if (! $task->date) {
            $task->date = today();
            $task->save();
        }

        $task->subTasks()->delete();
        foreach ($data['task']['subTasks'] as $subTaskData) {
            $task->subTasks()->create([
                'name' => $subTaskData['name'],
                'finished' => $subTaskData['finished'],
            ]);
        }

        // Respond to KanbanFlow
        return response()->json(['message' => "Webhook received successfully {$task->name}"]);
    }
}
