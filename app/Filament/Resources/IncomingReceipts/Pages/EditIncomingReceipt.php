<?php

namespace App\Filament\Resources\IncomingReceipts\Pages;

use App\Filament\Resources\IncomingReceipts\IncomingReceiptResource;
use App\Support\SignatureFormatter;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIncomingReceipt extends EditRecord
{
    protected static string $resource = IncomingReceiptResource::class;

    public bool $overrideRecordedThisSave = false;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        $this->recordEditOverride();
    }

    /**
     * Spec §8: a Super Admin editing a signed receipt must leave an override
     * entry in document_signoffs naming the edited step. canEdit() already
     * gates who gets here; this only records the audit trail — once per save.
     */
    protected function recordEditOverride(): void
    {
        if ($this->overrideRecordedThisSave) {
            return;
        }

        $receipt = $this->getRecord();

        $hasRealSignoff = $receipt->signoffs()
            ->where('step', '!=', 'edit_override')
            ->exists();

        if (! $hasRealSignoff) {
            return;
        }

        $signedAt = now('Asia/Jakarta');

        $receipt->signoffs()->create([
            'document_type' => $receipt->documentType(),
            'step' => 'edit_override',
            'role_required' => 'Super Admin',
            'user_id' => auth()->id(),
            'signer_name' => SignatureFormatter::format(auth()->user(), $signedAt),
            'signed_at' => $signedAt,
        ]);

        $this->overrideRecordedThisSave = true;
    }
}
