<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ReviewedTemperatureHumidity: WHERE is_reviewed = 0 AND 24x IS NOT NULL ORDER BY date DESC
        // AcknowledgedTemperatureHumidity: WHERE is_acknowledged = 0 AND 24x IS NOT NULL ORDER BY date DESC
        Schema::table('temperature_humidities', function (Blueprint $table) {
            $table->index(['is_reviewed', 'date'], 'idx_th_reviewed_date');
            $table->index(['is_acknowledged', 'date'], 'idx_th_acknowledged_date');
        });

        // TemperatureHumidityResource::table() orderByDesc('date')->orderByDesc('created_at')
        // Used by ALL list pages (All, Reviewed, Acknowledged)
        Schema::table('temperature_humidities', function (Blueprint $table) {
            $table->index(['date', 'created_at'], 'idx_th_date_created');
        });

        // TemperatureDeviationResource::table() orderByDesc('created_at')
        Schema::table('temperature_deviations', function (Blueprint $table) {
            $table->index(['date', 'created_at'], 'idx_td_date_created');
        });
    }

    public function down(): void
    {
        Schema::table('temperature_humidities', function (Blueprint $table) {
            $table->dropIndex('idx_th_reviewed_date');
            $table->dropIndex('idx_th_acknowledged_date');
            $table->dropIndex('idx_th_date_created');
        });

        Schema::table('temperature_deviations', function (Blueprint $table) {
            $table->dropIndex('idx_td_date_created');
        });
    }
};