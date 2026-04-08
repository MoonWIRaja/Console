<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('down_detector_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained('down_detector_monitors')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 64)->nullable();
            $table->text('summary')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['monitor_id', 'created_at'], 'down_detector_incidents_monitor_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('down_detector_incidents');
    }
};
