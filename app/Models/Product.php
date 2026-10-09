<?php

namespace App\Models;

use App\Enums\ProductSource;
use App\Enums\StorageRequirement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'storage_requirement' => StorageRequirement::class,
            'source' => ProductSource::class,
            'is_active' => 'boolean',
        ];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
