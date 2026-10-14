<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApprovalFlow;
use Illuminate\Database\Seeder;

/**
 * Backfills one active ApprovalFlow per document type from the legacy
 * config('documents.signoff_steps') definitions, so deploying approval
 * management changes no behaviour until an admin edits a flow.
 */
class ApprovalFlowSeeder extends Seeder
{
    public function run(): void
    {
        $labels = config('approval_flow_model_types', [
            'incoming_receipt' => 'Incoming Receipt',
            'incoming_receipt_grifols' => 'Incoming Receipt (Grifols)',
        ]);

        foreach (config('documents.signoff_steps', []) as $modelType => $steps) {
            if (ApprovalFlow::query()->where('model_type', $modelType)->exists()) {
                continue;
            }

            $flow = ApprovalFlow::query()->create([
                'name' => ($labels[$modelType] ?? $modelType).' — Default (from config)',
                'model_type' => $modelType,
                'is_active' => true,
            ]);

            $sequence = 1;

            foreach ($steps as $key => $config) {
                $flow->steps()->create([
                    'sequence' => $sequence++,
                    'label' => $config['label'],
                    'role_name' => $config['role'],
                ]);
            }
        }
    }
}
