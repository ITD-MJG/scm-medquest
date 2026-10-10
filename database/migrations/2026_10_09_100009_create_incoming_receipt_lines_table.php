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
        Schema::create('incoming_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_receipt_id')->constrained('incoming_receipts')->cascadeOnDelete();
            // A line must not silently lose its batch.
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->unsignedInteger('line_no');
            // Snapshot columns, deliberately duplicated from the batch/product at
            // receipt time. See spec §5.
            $table->string('internal_code');
            $table->text('product_description');
            $table->string('lot_no');
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('uom', 16);
            $table->string('physical_packing_condition')->nullable();
            $table->string('registration_label_no')->nullable();
            $table->foreignId('registration_label_qa_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('function_test_result')->nullable();
            $table->string('function_test_by')->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_receipt_lines');
    }
};
