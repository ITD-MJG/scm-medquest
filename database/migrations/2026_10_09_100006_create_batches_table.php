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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batchId')->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Non-null sentinels: MySQL treats NULLs as distinct in a unique index, so a
            // nullable key column would let duplicate batches through. See spec §5.
            $table->string('lot_no')->default('');
            $table->date('expiry_date')->default('9999-12-31');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('uom', 16);
            $table->string('status');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'lot_no', 'expiry_date'], 'batches_product_lot_expiry_unique');
            $table->index('status');
            $table->index('location_id');
            $table->index('room_id');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
