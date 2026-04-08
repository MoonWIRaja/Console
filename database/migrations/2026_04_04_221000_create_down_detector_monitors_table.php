<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('down_detector_monitors', function (Blueprint $table) {
            $table->id();
            $table->nullableNumericMorphs('monitorable');
            $table->string('current_status', 32)->default('unknown');
            $table->string('last_reason', 64)->nullable();
            $table->text('last_message')->nullable();
            $table->json('last_meta')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('consecutive_successes')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_status_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['monitorable_type', 'monitorable_id'], 'down_detector_monitors_unique');
            $table->index(['monitorable_type', 'current_status'], 'down_detector_monitors_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('down_detector_monitors');
    }
};
