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
        Schema::table('incoming_receipt_lines', function (Blueprint $table) {
            $table->string('part_no')->nullable();
            $table->string('serial_no')->nullable();
            $table->unsignedInteger('no_of_cases')->nullable();
            $table->text('packaging_observation')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incoming_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['part_no', 'serial_no', 'no_of_cases', 'packaging_observation']);
        });
    }
};
