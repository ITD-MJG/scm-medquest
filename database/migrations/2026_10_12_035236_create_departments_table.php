<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('incoming_receipts', function (Blueprint $table) {
            $table->string('category')->nullable()->after('incoming_type');
            $table->foreignId('department_id')->nullable()->after('category')->constrained('departments')->nullOnDelete();
        });

        DB::statement("UPDATE incoming_receipts SET category = 'import' WHERE incoming_type LIKE 'new_inventory%'");
    }

    public function down(): void
    {
        Schema::table('incoming_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('category');
        });

        Schema::dropIfExists('departments');
    }
};
