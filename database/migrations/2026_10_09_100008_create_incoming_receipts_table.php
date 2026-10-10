<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incoming_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('receiptId')->unique();
            // Issued by DocumentNumberService before insert, so it is never null.
            $table->string('receipt_no')->unique();
            $table->string('purchase_order_no')->nullable();
            $table->date('receipt_date');
            $table->time('receipt_time')->nullable();
            $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('shipper_address')->nullable();
            $table->string('incoming_type');
            $table->boolean('measured_by_device')->nullable();
            $table->decimal('product_temp_result', 5, 2)->nullable();
            $table->string('product_temp_record_ref')->nullable();
            $table->string('storage_requirement')->nullable();
            $table->dateTime('unpacking_start_at')->nullable();
            $table->dateTime('unpacking_finish_at')->nullable();
            $table->string('product_status');
            $table->text('remark')->nullable();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('location_id');
            $table->index('receipt_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_receipts');
    }
};
