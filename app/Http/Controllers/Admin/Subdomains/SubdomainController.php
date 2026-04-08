<?php

namespace Pterodactyl\Http\Controllers\Admin\Subdomains;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Models\Nest;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Subdomains\SubdomainDomain;
use Pterodactyl\Models\Subdomains\SubdomainRecord;

class SubdomainController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.subdomains.index', [
            'domains' => SubdomainDomain::query()->withCount(['records', 'subdomains'])->orderBy('name')->get(),
            'records' => SubdomainRecord::query()->with(['domain', 'nests'])->orderBy('name')->get(),
        ]);
    }

    public function createDomain(): View
    {
        return view('admin.subdomains.domains.create');
    }

    public function storeDomain(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'api_token' => 'required|string',
            'zone_identifier' => 'required|string|max:191',
        ]);

        SubdomainDomain::query()->create([
            'name' => strtolower(trim($data['name'])),
            'provider' => SubdomainDomain::PROVIDER_CLOUDFLARE,
            'api_token' => encrypt($data['api_token']),
            'zone_identifier' => trim($data['zone_identifier']),
        ]);

        $this->alert->success('Subdomain domain created successfully.')->flash();

        return redirect()->route('admin.subdomains.index');
    }

    public function editDomain(SubdomainDomain $domain): View
    {
        return view('admin.subdomains.domains.edit', ['domain' => $domain]);
    }

    public function updateDomain(Request $request, SubdomainDomain $domain): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'api_token' => 'nullable|string',
            'zone_identifier' => 'required|string|max:191',
        ]);

        $payload = [
            'name' => strtolower(trim($data['name'])),
            'zone_identifier' => trim($data['zone_identifier']),
        ];

        if (filled($data['api_token'] ?? null)) {
            $payload['api_token'] = encrypt($data['api_token']);
        }

        $domain->forceFill($payload)->save();

        $this->alert->success('Subdomain domain updated successfully.')->flash();

        return redirect()->route('admin.subdomains.index');
    }

    public function deleteDomain(SubdomainDomain $domain): RedirectResponse
    {
        if ($domain->subdomains()->exists()) {
            $this->alert->danger('Delete all server subdomains using this domain before removing it.')->flash();

            return redirect()->route('admin.subdomains.index');
        }

        $domain->delete();

        $this->alert->success('Subdomain domain deleted successfully.')->flash();

        return redirect()->route('admin.subdomains.index');
    }

    public function createRecord(): View
    {
        return view('admin.subdomains.records.create', [
            'domains' => SubdomainDomain::query()->orderBy('name')->get(),
            'nests' => Nest::query()->withCount('eggs')->orderBy('name')->get(),
        ]);
    }

    public function storeRecord(Request $request): RedirectResponse
    {
        $record = $this->persistRecord(new SubdomainRecord(), $request);

        $this->alert->success(sprintf('Subdomain template "%s" created successfully.', $record->name))->flash();

        return redirect()->route('admin.subdomains.index');
    }

    public function editRecord(SubdomainRecord $record): View
    {
        return view('admin.subdomains.records.edit', [
            'record' => $record->load('nests'),
            'domains' => SubdomainDomain::query()->orderBy('name')->get(),
            'nests' => Nest::query()->withCount('eggs')->orderBy('name')->get(),
        ]);
    }

    public function updateRecord(Request $request, SubdomainRecord $record): RedirectResponse
    {
        $record = $this->persistRecord($record, $request);

        $this->alert->success(sprintf('Subdomain template "%s" updated successfully.', $record->name))->flash();

        return redirect()->route('admin.subdomains.index');
    }

    public function deleteRecord(SubdomainRecord $record): RedirectResponse
    {
        if ($record->subdomains()->exists()) {
            $this->alert->danger('Delete all server subdomains using this template before removing it.')->flash();

            return redirect()->route('admin.subdomains.index');
        }

        $record->nests()->detach();
        $record->delete();

        $this->alert->success('Subdomain template deleted successfully.')->flash();

        return redirect()->route('admin.subdomains.index');
    }

    private function persistRecord(SubdomainRecord $record, Request $request): SubdomainRecord
    {
        $data = $request->validate([
            'domain_id' => 'required|integer|exists:subdomain_domains,id',
            'name' => 'required|string|max:191',
            'record_type' => 'required|string|in:CNAME,SRV',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'proxied' => 'sometimes|boolean',
            'service' => 'nullable|string|max:64',
            'protocol' => 'nullable|string|max:16',
            'priority' => 'nullable|integer|min:0|max:65535',
            'weight' => 'nullable|integer|min:0|max:65535',
            'nest_ids' => 'required|array|min:1',
            'nest_ids.*' => 'integer|exists:nests,id',
        ]);

        $payload = [
            'domain_id' => $data['domain_id'],
            'name' => trim($data['name']),
            'record_type' => $data['record_type'],
            'ttl' => $data['ttl'] ?: null,
            'proxied' => $request->boolean('proxied'),
            'service' => $data['record_type'] === SubdomainRecord::TYPE_SRV ? trim((string) ($data['service'] ?: '_minecraft')) : null,
            'protocol' => $data['record_type'] === SubdomainRecord::TYPE_SRV ? trim((string) ($data['protocol'] ?: '_tcp')) : null,
            'priority' => $data['record_type'] === SubdomainRecord::TYPE_SRV ? ($data['priority'] ?? 0) : null,
            'weight' => $data['record_type'] === SubdomainRecord::TYPE_SRV ? ($data['weight'] ?? 0) : null,
        ];

        $record->forceFill($payload)->save();
        $record->nests()->sync($data['nest_ids']);

        return $record->fresh(['domain', 'nests']);
    }
}
