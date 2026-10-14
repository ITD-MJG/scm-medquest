<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Closed document set: string key matching HasSignOffs::documentType(),
            // not a morphTo. See spec §5 and document_signoffs.document_type.
            $table->string('model_type');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('model_type');
        });

        Schema::create('approval_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained('approval_flows')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('label');
            // Role name from the spatie roles table; Super Admin may always sign.
            $table->string('role_name');
            $table->timestamps();

            $table->unique(['approval_flow_id', 'sequence']);
            $table->index('role_name');
        });

        Schema::create('approval_flow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained('approval_flows')->restrictOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            // pending | completed
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_sequence')->default(1);
            $table->timestamps();

            $table->unique(['model_type', 'model_id']);
            $table->index(['approval_flow_id', 'status']);
        });

        Schema::table('document_signoffs', function (Blueprint $table) {
            // Nullable: legacy sign-offs recorded before flows existed keep working
            // via the step string alone.
            $table->foreignId('approval_flow_instance_id')->nullable()
                ->constrained('approval_flow_instances')->nullOnDelete()->after('role_required');
            $table->foreignId('approval_flow_step_id')->nullable()
                ->constrained('approval_flow_steps')->nullOnDelete()->after('approval_flow_instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_signoffs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_flow_step_id');
            $table->dropConstrainedForeignId('approval_flow_instance_id');
        });
        Schema::dropIfExists('approval_flow_instances');
        Schema::dropIfExists('approval_flow_steps');
        Schema::dropIfExists('approval_flows');
    }
};
