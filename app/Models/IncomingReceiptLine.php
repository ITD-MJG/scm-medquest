<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingReceiptLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(IncomingReceipt::class, 'incoming_receipt_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function registrationLabelQaBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registration_label_qa_by');
    }
}
