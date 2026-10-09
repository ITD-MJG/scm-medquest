<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    /**
     * Sentinel expiry stored in place of NULL so the unique index treats
     * "no expiry" batches as the same key. See spec §5.
     */
    public const EXPIRY_SENTINEL = '9999-12-31';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'status' => BatchStatus::class,
            'quantity' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(IncomingReceiptLine::class);
    }

    public function expiryDateLabel(): string
    {
        if ($this->expiry_date->toDateString() === self::EXPIRY_SENTINEL) {
            return 'N/A';
        }

        return $this->expiry_date->format('d M Y');
    }

    public function lotNoLabel(): string
    {
        return $this->lot_no === '' ? 'N/A' : $this->lot_no;
    }
}
