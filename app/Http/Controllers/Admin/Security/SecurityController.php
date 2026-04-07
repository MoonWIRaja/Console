<?php

namespace Pterodactyl\Http\Controllers\Admin\Security;

use Throwable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Security\StoreSecurityAgentRequest;
use Pterodactyl\Http\Requests\Admin\Security\UpdateSecuritySettingsRequest;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Security\SecurityAction;
use Pterodactyl\Models\Security\SecurityAgent;
use Pterodactyl\Models\Security\SecurityEvent;
use Pterodactyl\Models\Security\SecurityIncident;
use Pterodactyl\Models\Security\SecurityQuarantineArtifact;
use Pterodactyl\Services\Admin\Settings\AdminSettingsStoreService;
use Pterodactyl\Services\Security\Agents\SecurityAgentService;
use Pterodactyl\Services\Security\SecurityCenterSettingsService;
use Pterodactyl\Services\Security\SecuritySelfTestService;
use Pterodactyl\Services\Security\SecurityRuleBootstrapService;

class SecurityController extends Controller
{
    private const TABS = ['overview', 'rules', 'incidents', 'live-events', 'agents', 'quarantine', 'settings'];

    public function __construct(
        private AlertsMessageBag $alert,
        private AdminSettingsStoreService $settingsStore,
        private SecurityCenterSettingsService $settings,
        private SecurityRuleBootstrapService $rules,
        private SecurityAgentService $agents,
        private SecuritySelfTestService $selfTest,
    ) {
    }

    public function index(Request $request): View
    {
        $selectedTab = in_array((string) $request->query('tab', 'overview'), self::TABS, true)
            ? (string) $request->query('tab', 'overview')
            : 'overview';

        $rules = $this->rules->syncDefaults();
        $missingNodes = $this->agents->missingNodes();

        return view('admin.security.index', [
            'selectedTab' => $selectedTab,
            'tabs' => self::TABS,
            'settings' => $this->settings->config(),
            'rules' => $rules,
            'summary' => [
                'open_incidents' => SecurityIncident::query()->whereIn('status', ['open', 'monitoring', 'contained'])->count(),
                'blocked_events_24h' => SecurityEvent::query()->where('blocked', true)->where('created_at', '>=', now()->subDay())->count(),
                'active_agents' => SecurityAgent::query()->where('status', 'active')->count(),
                'quarantined_artifacts' => SecurityQuarantineArtifact::query()->where('status', 'quarantined')->count(),
                'pending_actions' => SecurityAction::query()->whereIn('status', ['pending', 'dispatched'])->count(),
            ],
            'incidents' => SecurityIncident::query()->latest('last_seen_at')->limit(50)->get(),
            'events' => SecurityEvent::query()->with(['incident', 'rule'])->latest()->limit(100)->get(),
            'agents' => SecurityAgent::query()->with('node')->latest()->get(),
            'artifacts' => SecurityQuarantineArtifact::query()->latest('quarantined_at')->limit(100)->get(),
            'actions' => SecurityAction::query()->with(['agent', 'incident'])->latest()->limit(100)->get(),
            'nodes' => Node::query()->orderBy('name')->get(),
            'missingNodes' => $missingNodes,
            'defaultAgentCapabilities' => implode(', ', $this->agents->defaultCapabilities()),
            'latestSelfTest' => $this->selfTest->latest(),
            'provisionedAgent' => session('security_agent_provisioned'),
            'provisionedAgents' => session('security_agents_provisioned', []),
        ]);
    }

    public function update(UpdateSecuritySettingsRequest $request): RedirectResponse
    {
        $this->settingsStore->save($request->normalize());

        $this->alert->success('Security Center settings updated successfully.')->flash();

        return redirect()->route('admin.security', ['tab' => 'settings']);
    }

    public function storeAgent(StoreSecurityAgentRequest $request): RedirectResponse
    {
        $node = $request->filled('node_id') ? Node::query()->findOrFail((int) $request->input('node_id')) : null;
        $name = trim((string) $request->input('name'));

        [$agent, $secret] = $this->agents->create(
            $name !== '' ? $name : $this->agents->suggestedName($node),
            $node,
            $request->capabilities(),
        );

        $this->alert->success('Security agent provisioned successfully. Copy the secret now; it is only shown after creation or rotation.')->flash();

        return $this->redirectWithProvisionedAgents([[
            'id' => $agent->id,
            'uuid' => $agent->uuid,
            'name' => $agent->name,
            'node_id' => $agent->node_id,
            'node_name' => $node?->name,
            'secret' => $secret,
        ]]);
    }

    public function autoProvisionAgents(): RedirectResponse
    {
        $created = $this->agents->provisionMissingNodes();

        if ($created === []) {
            $this->alert->warning('All nodes already have a linked security agent.')->flash();

            return redirect()->route('admin.security', ['tab' => 'agents']);
        }

        $this->alert->success(sprintf(
            'Auto-provisioned %d security agent(s) for nodes that did not have one yet.',
            count($created)
        ))->flash();

        return $this->redirectWithProvisionedAgents($created);
    }

    public function rotateAgentSecret(SecurityAgent $securityAgent): RedirectResponse
    {
        $secret = $this->agents->rotateSecret($securityAgent);

        $this->alert->success('Security agent secret rotated successfully.')->flash();

        return $this->redirectWithProvisionedAgents([[
            'id' => $securityAgent->id,
            'uuid' => $securityAgent->uuid,
            'name' => $securityAgent->name,
            'node_id' => $securityAgent->node_id,
            'node_name' => $securityAgent->node?->name,
            'secret' => $secret,
        ]]);
    }

    public function runSelfTest(Request $request): RedirectResponse
    {
        try {
            $result = $this->selfTest->run(
                $request->user() instanceof User ? $request->user() : null,
                $request->ip()
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger('Security self-test could not be executed. Check the application log and verify that PHP can spawn the self-test runner.')->flash();

            return redirect()->route('admin.security', ['tab' => 'overview']);
        }

        $failed = (int) ($result['summary']['failed'] ?? 0);

        if ($failed > 0) {
            $this->alert->warning(sprintf(
                'Security self-test completed with %d failing check(s). Review Overview, Live Events, or System Logs > Security for the full result.',
                $failed
            ))->flash();
        } else {
            $this->alert->success('Security self-test passed. Review Overview, Live Events, or System Logs > Security for the recorded result.')->flash();
        }

        return redirect()->route('admin.security', ['tab' => 'overview']);
    }

    private function redirectWithProvisionedAgents(array $agents): RedirectResponse
    {
        return redirect()->route('admin.security', ['tab' => 'agents'])
            ->with('security_agent_provisioned', $agents[0] ?? null)
            ->with('security_agents_provisioned', $agents);
    }
}
