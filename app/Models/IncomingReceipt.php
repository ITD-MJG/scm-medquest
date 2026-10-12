<?php

namespace App\Models;

use App\Contracts\HasSignOffs;
use App\Enums\IncomingType;
use App\Enums\ProductStatus;
use App\Enums\ReceiptCategory;
use App\Enums\StorageRequirement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomingReceipt extends Model implements HasSignOffs
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'incoming_type' => IncomingType::class,
            'category' => ReceiptCategory::class,
            'product_status' => ProductStatus::class,
            'storage_requirement' => StorageRequirement::class,
            'receipt_date' => 'date',
            'unpacking_start_at' => 'datetime',
            'unpacking_finish_at' => 'datetime',
        ];
    }

    public function documentType(): string
    {
        return $this->variant === 'grifols' ? 'incoming_receipt_grifols' : 'incoming_receipt';
    }

    public function signoffs(): HasMany
    {
        return $this->hasMany(DocumentSignoff::class, 'document_id')
            ->where('document_type', $this->documentType());
    }

    public function lines(): HasMany
    {
        return $this->hasMany(IncomingReceiptLine::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
