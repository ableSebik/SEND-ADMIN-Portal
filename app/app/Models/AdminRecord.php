<?php

namespace App\Models;

use App\Services\AssetDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminRecord extends Model
{
    use SoftDeletes;

    protected $table = 'records';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn (AdminRecord $record) => AssetDetails::sync($record));
    }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
