<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalFlowInstance extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'current_sequence' => 'integer',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function signoffs(): HasMany
    {
        return $this->hasMany(DocumentSignoff::class, 'approval_flow_instance_id');
    }

    /**
     * The step the document is currently waiting on, or null when completed.
     */
    public function currentStep(): ?ApprovalFlowStep
    {
        if ($this->status === 'completed') {
            return null;
        }

        return $this->flow->steps->firstWhere('sequence', $this->current_sequence);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
