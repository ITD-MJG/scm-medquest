<?php

namespace App\Filament\Resources\ApprovalFlows\Pages;

use App\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApprovalFlow extends EditRecord
{
    protected static string $resource = ApprovalFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->is_active) {
            $this->record->activate();
        }
    }
}
