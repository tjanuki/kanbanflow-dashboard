<?php

namespace Database\Seeders;

use App\Models\StopReason;
use Illuminate\Database\Seeder;

class StopReasonSeeder extends Seeder
{
    /**
     * Seed the default "Why did you stop?" reasons, mirroring KanbanFlow.
     */
    public function run(): void
    {
        $reasons = [
            'Boss interrupted',
            'Cat Grooming',
            'Colleague interrupted',
            'Email',
            'My Wife',
            'Noise',
            'Phone call',
            'Web browsing',
            'Wrong click',
        ];

        foreach ($reasons as $index => $label) {
            StopReason::updateOrCreate(
                ['label' => $label],
                ['position' => $index + 1],
            );
        }
    }
}
