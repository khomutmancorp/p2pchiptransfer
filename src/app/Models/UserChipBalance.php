<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChipBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'last_updated_at',
    ];

    protected $casts = [
        'balance' => 'integer',
        'last_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
