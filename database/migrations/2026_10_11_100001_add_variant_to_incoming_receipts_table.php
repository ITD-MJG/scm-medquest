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
        Schema::table('incoming_receipts', function (Blueprint $table) {
            $table->string('variant')->default('standard')->index()->after('receipt_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incoming_receipts', function (Blueprint $table) {
            $table->dropIndex(['variant']);
            $table->dropColumn('variant');
        });
    }
};
