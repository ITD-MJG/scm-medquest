<?php

namespace App\Filament\Resources\ApprovalFlows\Pages;

use App\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use App\Models\ApprovalFlow;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateApprovalFlow extends CreateRecord
{
    protected static string $resource = ApprovalFlowResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $flow = static::getModel()::create($data);

        if ($flow->is_active) {
            $flow->activate();
        }

        return $flow;
    }
}
