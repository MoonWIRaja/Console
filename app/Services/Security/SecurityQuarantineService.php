<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Pterodactyl\Models\Security\SecurityQuarantineArtifact;

class SecurityQuarantineService
{
    public function quarantine(EloquentModel $target, array $attributes = []): SecurityQuarantineArtifact
    {
        return SecurityQuarantineArtifact::query()->create([
            'incident_id' => $attributes['incident_id'] ?? null,
            'event_id' => $attributes['event_id'] ?? null,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'disk' => $attributes['disk'] ?? null,
            'path' => $attributes['path'] ?? null,
            'original_name' => $attributes['original_name'] ?? null,
            'sha256' => $attributes['sha256'] ?? null,
            'reason' => (string) ($attributes['reason'] ?? 'Suspicious artifact detected.'),
            'status' => $attributes['status'] ?? 'quarantined',
            'meta' => $attributes['meta'] ?? null,
            'quarantined_at' => $attributes['quarantined_at'] ?? now(),
        ]);
    }

    public function activeForTarget(EloquentModel $target): ?SecurityQuarantineArtifact
    {
        return SecurityQuarantineArtifact::query()
            ->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->where('status', 'quarantined')
            ->latest('quarantined_at')
            ->first();
    }

    public function release(SecurityQuarantineArtifact $artifact, ?array $meta = null): SecurityQuarantineArtifact
    {
        $artifact->forceFill([
            'status' => 'released',
            'released_at' => now(),
            'meta' => array_merge($artifact->meta ?? [], $meta ?? []),
        ])->saveOrFail();

        return $artifact->fresh();
    }
}
