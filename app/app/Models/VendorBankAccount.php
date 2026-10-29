<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorBankAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array'];
    }
}
