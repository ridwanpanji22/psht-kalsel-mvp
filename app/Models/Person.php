<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'birth_place', 'birth_date'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }
}
