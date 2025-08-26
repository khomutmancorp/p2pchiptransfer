<?php

namespace App\Rules;

use App\Exceptions\PlayerNotFoundException;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class PlayerExists implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (! User::where('id', $value)->exists()) {
            throw new PlayerNotFoundException("Player with ID {$value} not found.");
        }

        return true;
    }

    public function message(): string
    {
        // Will not be used since we throw exception, but must exist
        return 'Player not found.';
    }
}
