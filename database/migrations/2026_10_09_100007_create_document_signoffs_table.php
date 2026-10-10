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
        Schema::create('document_signoffs', function (Blueprint $table) {
            $table->id();
            // Closed, known document set: a string type plus an id instead of a morphTo.
            // See spec §5.
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('step');
            // The role the config demanded when the step was signed, frozen on the row so a
            // later config change does not rewrite history.
            $table->string('role_required');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name');
            $table->dateTime('signed_at');
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['document_type', 'document_id', 'step']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signoffs');
    }
};
