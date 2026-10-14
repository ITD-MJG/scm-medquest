<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalFlow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalFlowStep::class)->orderBy('sequence');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ApprovalFlowInstance::class);
    }

    public function activate(): void
    {
        // One active flow per document type: activating this one retires the others.
        static::query()
            ->where('model_type', $this->model_type)
            ->whereKeyNot($this->getKey())
            ->update(['is_active' => false]);

        $this->forceFill(['is_active' => true])->save();
    }
}
