<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('driver')->default('mysql')->comment('sap db driver: mysql, sqlsrv');
            $table->string('host');
            $table->unsignedInteger('port')->default(3306);
            $table->string('database')->nullable();
            $table->string('username');
            $table->text('password')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable()->comment('success | failed');
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_connections');
    }
};
