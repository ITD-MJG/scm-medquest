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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('productId')->unique();
            $table->string('internal_code')->unique();
            $table->foreignId('principal_id')->nullable()->constrained('principals')->nullOnDelete();
            $table->string('principal_code')->nullable();
            $table->text('description');
            $table->string('part_no')->nullable();
            $table->string('uom');
            $table->string('uom_source', 16)->default('sap');
            $table->string('storage_requirement')->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('sap_code')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
