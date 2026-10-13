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
            $table->string('awb_delivery_doc_no')->nullable()->after('shipper_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incoming_receipts', function (Blueprint $table) {
            $table->dropColumn('awb_delivery_doc_no');
        });
    }
};
