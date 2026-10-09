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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            // NULL is distinct in a unique index on MySQL/MariaDB, so two rows with
            // location_id = NULL for the same type+period would both be accepted and two
            // first-time callers could each return sequence 0001 (spec §6). This generated
            // column collapses NULL to 0 for index purposes; distinct non-null locations
            // still get their own row.
            $table->unsignedBigInteger('location_key')->storedAs('coalesce(location_id, 0)');

            $table->unique(['document_type', 'location_key', 'period'], 'document_sequences_type_location_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
