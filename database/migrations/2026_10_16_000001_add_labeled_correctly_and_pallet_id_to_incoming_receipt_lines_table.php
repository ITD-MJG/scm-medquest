<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_receipt_lines', function (Blueprint $table) {
            $table->boolean('labeled_correctly')->nullable()->after('no_of_cases');
            $table->string('pallet_id')->nullable()->after('labeled_correctly');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['labeled_correctly', 'pallet_id']);
        });
    }
};
