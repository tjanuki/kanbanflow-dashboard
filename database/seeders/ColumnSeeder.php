<?php

namespace Database\Seeders;

use App\Models\Column;
use Illuminate\Database\Seeder;

class ColumnSeeder extends Seeder
{
    /**
     * Seed the default board columns, mirroring the KanbanFlow layout.
     */
    public function run(): void
    {
        $columns = [
            ['name' => 'Templates', 'type' => 'fixed'],
            ['name' => 'Today', 'type' => 'fixed', 'wip_limit' => 10],
            ['name' => 'Done', 'type' => 'fixed'],
            ['name' => 'Ideas', 'type' => 'fixed'],
            ['name' => 'Monday', 'type' => 'day'],
            ['name' => 'Tuesday', 'type' => 'day'],
            ['name' => 'Wednesday', 'type' => 'day'],
            ['name' => 'Thursday', 'type' => 'day'],
            ['name' => 'Friday', 'type' => 'day'],
            ['name' => 'Saturday', 'type' => 'day'],
        ];

        foreach ($columns as $index => $column) {
            Column::updateOrCreate(
                ['name' => $column['name']],
                [
                    'type' => $column['type'],
                    'position' => $index + 1,
                    'wip_limit' => $column['wip_limit'] ?? null,
                ],
            );
        }
    }
}
