<?php

namespace Pterodactyl\Http\Controllers\Admin\Security;

use Throwable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
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
use Pterodactyl\Services\Security\SecurityVocabulary;

class SecurityController extends Controller
{
    private const TABLE_PER_PAGE = 25;
    private const OVERVIEW_INCIDENTS_PER_PAGE = 10;
    private const QUARANTINE_STATUSES = ['quarantined'];

    private const TABS = ['overview', 'rules', 'incidents', 'live-events', 'agents', 'quarantine', 'settings'];

    public function __construct(
        private AlertsMessageBag $alert,
        private AdminSettingsStoreService $settingsStore,
        private SecurityCenterSettingsService $settings,
        private SecurityRuleBootstrapService $rules,
        private SecurityAgentService $agents,
        private SecuritySelfTestService $selfTest,
        private Encrypter $encrypter,
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
            'overviewIncidents' => $this->incidentPaginator($request, 'overview_incidents_verdict', 'overview_incidents_page', self::OVERVIEW_INCIDENTS_PER_PAGE),
            'overviewIncidentFilter' => $this->filterMeta($request, 'overview_incidents_verdict', SecurityVocabulary::verdicts(), 'overview_incidents_page', 'All verdicts', 'Verdict'),
            'incidents' => $this->incidentPaginator($request, 'incidents_verdict', 'incidents_page', self::TABLE_PER_PAGE),
            'incidentFilter' => $this->filterMeta($request, 'incidents_verdict', SecurityVocabulary::verdicts(), 'incidents_page', 'All verdicts', 'Verdict'),
            'events' => $this->eventPaginator($request),
            'eventFilter' => $this->filterMeta($request, 'events_verdict', SecurityVocabulary::verdicts(), 'events_page', 'All verdicts', 'Verdict'),
            'agents' => SecurityAgent::query()->with('node')->latest()->get()->map(function (SecurityAgent $agent) {
                try {
                    $agent->decrypted_secret = is_string($agent->current_secret_encrypted)
                        ? $this->encrypter->decrypt($agent->current_secret_encrypted)
                        : null;
                } catch (\Throwable) {
                    $agent->decrypted_secret = null;
                }
                return $agent;
            }),
            'artifacts' => $this->artifactPaginator($request),
            'artifactFilter' => $this->filterMeta($request, 'artifacts_status', self::QUARANTINE_STATUSES, 'artifacts_page', 'All artifact statuses'),
            'actions' => SecurityAction::query()->with(['agent', 'incident'])->latest()->limit(100)->get(),
            'nodes' => Node::query()->orderBy('name')->get(),
            'missingNodes' => $missingNodes,
            'defaultAgentCapabilities' => implode(', ', $this->agents->defaultCapabilities()),
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

    private function incidentPaginator(Request $request, string $filterName, string $pageName, int $perPage): LengthAwarePaginator
    {
        $query = SecurityIncident::query()->latest('last_seen_at');
        $this->applyFieldFilter($query, $request, $filterName, 'verdict', SecurityVocabulary::verdicts());

        return $query->paginate($perPage, ['*'], $pageName)->appends($request->except($pageName));
    }

    private function eventPaginator(Request $request): LengthAwarePaginator
    {
        $query = SecurityEvent::query()->with(['incident', 'rule'])->latest();
        $this->applyFieldFilter($query, $request, 'events_verdict', 'verdict', SecurityVocabulary::verdicts());

        return $query->paginate(self::TABLE_PER_PAGE, ['*'], 'events_page')->appends($request->except('events_page'));
    }

    private function artifactPaginator(Request $request): LengthAwarePaginator
    {
        $query = SecurityQuarantineArtifact::query()->latest('quarantined_at');
        $this->applyFieldFilter($query, $request, 'artifacts_status', 'status', self::QUARANTINE_STATUSES);

        return $query->paginate(self::TABLE_PER_PAGE, ['*'], 'artifacts_page')->appends($request->except('artifacts_page'));
    }

    private function applyFieldFilter($query, Request $request, string $name, string $field, array $allowed): void
    {
        $value = $this->selectedOption($request, $name, $allowed);

        if ($value !== null) {
            $query->where($field, $value);
        }
    }

    private function filterMeta(Request $request, string $name, array $options, string $pageName, string $placeholder, string $label = 'Status'): array
    {
        return [
            'name' => $name,
            'value' => $this->selectedOption($request, $name, $options),
            'options' => $this->statusOptions($options),
            'pageName' => $pageName,
            'placeholder' => $placeholder,
            'label' => $label,
        ];
    }

    private function selectedOption(Request $request, string $name, array $allowed): ?string
    {
        $value = (string) $request->query($name, '');

        if ($value === '') {
            return null;
        }

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function statusOptions(array $statuses): array
    {
        return array_reduce($statuses, function (array $options, string $status): array {
            $options[$status] = ucwords(str_replace(['_', ':', '.', '-'], ' ', $status));

            return $options;
        }, []);
    }
}
