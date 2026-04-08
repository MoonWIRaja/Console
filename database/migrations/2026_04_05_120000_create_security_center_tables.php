<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('class', 64);
            $table->string('surface', 64);
            $table->boolean('enabled')->default(true);
            $table->string('mode', 32)->default('active');
            $table->unsignedInteger('threshold')->default(10);
            $table->unsignedInteger('window_seconds')->default(300);
            $table->unsignedInteger('weight')->default(10);
            $table->json('response_policy')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(300);
            $table->boolean('agent_required')->default(false);
            $table->timestamps();

            $table->index(['surface', 'enabled'], 'security_rules_surface_enabled');
        });

        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('threat_id', 64)->unique();
            $table->string('title', 191);
            $table->text('summary')->nullable();
            $table->string('class', 64);
            $table->string('surface', 64);
            $table->string('status', 32)->default('open');
            $table->string('severity', 16)->default('medium');
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('source_ip', 64)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedInteger('node_id')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('evidence')->nullable();
            $table->json('meta')->nullable();
            $table->string('verdict', 32)->default('observed_only');
            $table->boolean('blocked')->default(false);
            $table->string('mitigation_stage', 32)->default('observe');
            $table->string('correlation_id', 128)->nullable();
            $table->unsignedInteger('event_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_seen_at'], 'security_incidents_status_seen');
            $table->index(['surface', 'severity'], 'security_incidents_surface_severity');
            $table->index(['source_ip', 'fingerprint'], 'security_incidents_source_fingerprint');
            $table->index(['correlation_id'], 'security_incidents_correlation');
            $table->foreign('node_id')->references('id')->on('nodes')->nullOnDelete();
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->nullable()->constrained('security_incidents')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('security_rules')->nullOnDelete();
            $table->string('threat_id', 64);
            $table->string('class', 64);
            $table->string('surface', 64);
            $table->string('severity', 16)->default('medium');
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('source_ip', 64)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedInteger('node_id')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('evidence')->nullable();
            $table->string('verdict', 32)->default('observed_only');
            $table->boolean('blocked')->default(false);
            $table->string('mitigation_stage', 32)->default('observe');
            $table->string('correlation_id', 128)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['surface', 'created_at'], 'security_events_surface_created');
            $table->index(['verdict', 'blocked'], 'security_events_verdict_blocked');
            $table->index(['source_ip', 'fingerprint'], 'security_events_source_fingerprint');
            $table->index(['correlation_id'], 'security_events_correlation');
            $table->foreign('node_id')->references('id')->on('nodes')->nullOnDelete();
        });

        Schema::create('security_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 191);
            $table->unsignedInteger('node_id')->nullable();
            $table->string('status', 32)->default('provisioning');
            $table->json('capabilities')->nullable();
            $table->longText('current_secret_encrypted')->nullable();
            $table->longText('previous_secret_encrypted')->nullable();
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamp('isolated_at')->nullable();
            $table->string('last_ip', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_heartbeat_at'], 'security_agents_status_heartbeat');
            $table->foreign('node_id')->references('id')->on('nodes')->nullOnDelete();
        });

        Schema::create('security_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('security_agents')->nullOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('security_incidents')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('security_events')->nullOnDelete();
            $table->string('action', 64);
            $table->string('scope', 64)->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('source_ip', 64)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('result')->nullable();
            $table->timestamp('execute_after')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status', 'execute_after'], 'security_actions_agent_status_due');
            $table->index(['status', 'created_at'], 'security_actions_status_created');
        });

        Schema::create('security_quarantine_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->nullable()->constrained('security_incidents')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('security_events')->nullOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('disk', 64)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('sha256', 128)->nullable();
            $table->string('reason', 191);
            $table->string('status', 32)->default('quarantined');
            $table->json('meta')->nullable();
            $table->timestamp('quarantined_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id', 'status'], 'security_quarantine_target_status');
            $table->index(['status', 'quarantined_at'], 'security_quarantine_status_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_quarantine_artifacts');
        Schema::dropIfExists('security_actions');
        Schema::dropIfExists('security_agents');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('security_rules');
    }
};
