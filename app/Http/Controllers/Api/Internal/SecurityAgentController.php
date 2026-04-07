<?php

namespace Pterodactyl\Http\Controllers\Api\Internal;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Security\Agents\SecurityAgentService;
use Pterodactyl\Services\Security\Agents\SecurityAgentSignatureService;

class SecurityAgentController extends Controller
{
    public function __construct(
        private SecurityAgentSignatureService $signature,
        private SecurityAgentService $agents,
    ) {
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $agent = $this->signature->authenticate($request);

        $payload = $request->validate([
            'agent_id' => 'required|string|max:64',
            'node_id' => 'nullable|integer|exists:nodes,id',
            'status' => 'nullable|string|max:32',
            'version' => 'nullable|string|max:64',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'string|max:64',
            'meta' => 'nullable|array',
        ]);

        $agent = $this->agents->recordHeartbeat($agent, $payload, $request);

        return new JsonResponse([
            'data' => [
                'ok' => true,
                'agent' => [
                    'id' => $agent->id,
                    'uuid' => $agent->uuid,
                    'status' => $agent->status,
                    'last_heartbeat_at' => optional($agent->last_heartbeat_at)?->toIso8601String(),
                ],
            ],
        ], Response::HTTP_ACCEPTED);
    }

    public function report(Request $request): JsonResponse
    {
        $agent = $this->signature->authenticate($request);

        $payload = $request->validate([
            'agent_id' => 'required|string|max:64',
            'reports' => 'required|array|max:50',
            'reports.*.rule_key' => 'required|string|max:96',
            'reports.*.class' => 'nullable|string|max:64',
            'reports.*.surface' => 'nullable|string|max:64',
            'reports.*.severity' => 'nullable|string|max:16',
            'reports.*.confidence' => 'nullable|integer|min:0|max:100',
            'reports.*.source_ip' => 'nullable|string|max:64',
            'reports.*.fingerprint' => 'nullable|string|max:64',
            'reports.*.actor_type' => 'nullable|string|max:191',
            'reports.*.actor_id' => 'nullable|integer',
            'reports.*.node_id' => 'nullable|integer|exists:nodes,id',
            'reports.*.target_type' => 'nullable|string|max:191',
            'reports.*.target_id' => 'nullable|integer',
            'reports.*.summary' => 'nullable|string|max:2000',
            'reports.*.evidence' => 'nullable|array',
            'reports.*.blocked' => 'nullable|boolean',
            'reports.*.execute_actions' => 'nullable|boolean',
            'reports.*.verdict' => 'nullable|string|max:32',
            'reports.*.mitigation_stage' => 'nullable|string|max:32',
        ]);

        $events = $this->agents->recordReports($agent, $payload['reports'], $request);

        return new JsonResponse([
            'data' => [
                'ok' => true,
                'recorded' => count($events),
            ],
        ], Response::HTTP_ACCEPTED);
    }

    public function pullActions(Request $request): JsonResponse
    {
        $agent = $this->signature->authenticate($request);

        $payload = $request->validate([
            'agent_id' => 'required|string|max:64',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $actions = $this->agents->pullActions($agent, (int) ($payload['limit'] ?? 25));

        return new JsonResponse([
            'data' => $actions->map(fn ($action) => [
                'id' => $action->id,
                'action' => $action->action,
                'scope' => $action->scope,
                'source_ip' => $action->source_ip,
                'fingerprint' => $action->fingerprint,
                'payload' => $action->payload,
                'execute_after' => optional($action->execute_after)?->toIso8601String(),
                'incident_id' => $action->incident_id,
                'event_id' => $action->event_id,
            ])->values(),
        ], Response::HTTP_OK);
    }

    public function actionResult(Request $request): JsonResponse
    {
        $agent = $this->signature->authenticate($request);

        $payload = $request->validate([
            'agent_id' => 'required|string|max:64',
            'results' => 'required|array|max:50',
            'results.*.action_id' => 'required|integer',
            'results.*.success' => 'required|boolean',
            'results.*.artifact_refs' => 'nullable|array',
            'results.*.stdout_summary' => 'nullable|string|max:2000',
            'results.*.stderr_summary' => 'nullable|string|max:2000',
            'results.*.verdict_impact' => 'nullable|string|max:64',
            'results.*.meta' => 'nullable|array',
        ]);

        $updated = [];
        foreach ($payload['results'] as $result) {
            $action = $this->agents->completeAction($agent, (int) $result['action_id'], $result);
            if ($action) {
                $updated[] = $action->id;
            }
        }

        return new JsonResponse([
            'data' => [
                'ok' => true,
                'updated' => $updated,
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
