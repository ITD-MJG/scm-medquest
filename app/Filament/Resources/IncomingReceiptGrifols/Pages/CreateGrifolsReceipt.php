<?php

namespace App\Filament\Resources\IncomingReceiptGrifols\Pages;

use App\Filament\Resources\IncomingReceiptGrifols\GrifolsReceiptResource;
use App\Models\IncomingReceipt;
use App\Models\Product;
use App\Models\Room;
use App\Services\BatchResolver;
use App\Services\SignOffService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateGrifolsReceipt extends CreateRecord
{
    protected static string $resource = GrifolsReceiptResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $receipt = IncomingReceipt::create([
                'receiptId' => Str::orderedUuid(),
                'receipt_no' => $data['receipt_no'],
                'variant' => 'grifols',
                'purchase_order_no' => $data['purchase_order_no'] ?? null,
                'receipt_date' => $data['receipt_date'],
                'receipt_time' => $data['receipt_time'] ?? null,
                'incoming_type' => $data['incoming_type'],
                'product_status' => $data['product_status'],
                'remark' => $data['remark'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'carrier_id' => $data['carrier_id'] ?? null,
                'shipper_address' => $data['shipper_address'] ?? null,
                'measured_by_device' => $data['measured_by_device'] ?? null,
                'product_temp_result' => $data['product_temp_result'] ?? null,
                'product_temp_record_ref' => $data['product_temp_record_ref'] ?? null,
                'storage_requirement' => $data['storage_requirement'] ?? null,
                'unpacking_start_at' => $data['unpacking_start_at'] ?? null,
                'unpacking_finish_at' => $data['unpacking_finish_at'] ?? null,
                'location_id' => $data['location_id'],
                'created_by' => auth()->id(),
            ]);

            foreach ($data['lines'] ?? [] as $i => $line) {
                $resolver = app(BatchResolver::class);
                $args = [
                    Product::find($line['product_id']),
                    $line['lot_no'],
                    $line['expiry_date'] ?: null,
                    (float) $line['quantity'],
                    $line['uom'],
                    ! empty($line['room_id']) ? Room::find($line['room_id']) : null,
                    $data['location_id'],
                ];

                // Spec §8: under concurrency two writers can race on the
                // (product_id, lot_no, expiry_date) unique index. Retry once
                // inside the transaction — the second lookup finds the
                // winner's row and merges instead of creating.
                try {
                    $batch = $resolver->resolve(...$args);
                } catch (QueryException $e) {
                    if (! str_contains($e->getMessage(), 'SQLSTATE[23000]') || ! str_contains($e->getMessage(), '1062')) {
                        throw $e;
                    }

                    $batch = $resolver->resolve(...$args);
                }

                $receipt->lines()->create([
                    'batch_id' => $batch->id,
                    'line_no' => $i + 1,
                    'internal_code' => $line['internal_code'],
                    'product_description' => $line['product_description'],
                    'lot_no' => $line['lot_no'],
                    'expiry_date' => $line['expiry_date'] ?: null,
                    'quantity' => $line['quantity'],
                    'uom' => $line['uom'],
                    'physical_packing_condition' => $line['physical_packing_condition'] ?? null,
                    'registration_label_no' => $line['registration_label_no'] ?? null,
                    'registration_label_qa_by' => $line['registration_label_qa_by'] ?? null,
                    'function_test_result' => $line['function_test_result'] ?? null,
                    'function_test_by' => $line['function_test_by'] ?? null,
                    'room_id' => $line['room_id'] ?? null,
                    'part_no' => $line['part_no'] ?? null,
                    'serial_no' => $line['serial_no'] ?? null,
                    'no_of_cases' => $line['no_of_cases'] ?? null,
                    'packaging_observation' => $line['packaging_observation'] ?? null,
                ]);
            }

            app(SignOffService::class)->record($receipt, 'received_by');

            return $receipt;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->title('Grifols incoming receipt successfully created');
    }
}
