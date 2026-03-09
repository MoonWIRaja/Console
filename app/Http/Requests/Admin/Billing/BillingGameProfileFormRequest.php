<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingGameProfileFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'nest_ids' => ['nullable', 'array'],
            'nest_ids.*' => ['required', 'integer', Rule::exists('nests', 'id')],
        ];
    }

    /**
     * Return the selected nest IDs with duplicates removed.
     *
     * @return array<int, int>
     */
    public function nestIds(): array
    {
        return collect($this->input('nest_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
