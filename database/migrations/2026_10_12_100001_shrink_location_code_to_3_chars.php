<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Truncate existing values longer than 3 chars before shrinking the column
        DB::statement('UPDATE locations SET location_code = LEFT(location_code, 3) WHERE CHAR_LENGTH(location_code) > 3');

        Schema::table('locations', function (Blueprint $table) {
            $table->string('location_code', 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('location_code', 16)->nullable()->change();
        });
    }
};
