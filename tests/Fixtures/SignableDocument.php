<?php

namespace Tests\Fixtures;

use App\Contracts\HasSignOffs;
use App\Models\DocumentSignoff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SignableDocument extends Model implements HasSignOffs
{
    protected $guarded = [];

    public $timestamps = false;

    public static function create(array $attributes = []): self
    {
        return new self($attributes);
    }

    public function documentType(): string
    {
        return $this->document_type;
    }

    public function signoffs(): HasMany
    {
        return $this->hasMany(DocumentSignoff::class, 'document_id')
            ->where('document_type', $this->document_type);
    }
}
