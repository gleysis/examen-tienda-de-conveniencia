<?php

namespace App\Models;

use Database\Factories\SystemSettingChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSettingChange extends Model
{
    /** @use HasFactory<SystemSettingChangeFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'old_value',
        'new_value',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
