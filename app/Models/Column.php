<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Column extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'position',
        'type',
        'color',
        'wip_limit',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'board_column_id')->orderBy('position');
    }
}
