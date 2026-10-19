<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_receipt_lines', function (Blueprint $table) {
            $table->unsignedInteger('unit_per_case')->nullable()->after('no_of_cases');
            $table->boolean('isn_or_packing_list')->nullable()->after('labeled_correctly');
            $table->foreignId('labeled_by')->nullable()->after('registration_label_qa_by')
                ->constrained('users')->nullOnDelete();
            $table->string('physical_movement_by')->nullable()->after('packaging_observation');
        });

        Schema::table('incoming_receipts', function (Blueprint $table) {
            $table->boolean('registration_label_attached')->nullable()->after('storage_requirement');
            $table->decimal('duration_of_transportation_hours', 5, 2)->nullable()->after('disposition');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_receipt_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('labeled_by');
            $table->dropColumn(['unit_per_case', 'isn_or_packing_list', 'physical_movement_by']);
        });

        Schema::table('incoming_receipts', function (Blueprint $table) {
            $table->dropColumn(['registration_label_attached', 'duration_of_transportation_hours']);
        });
    }
};
