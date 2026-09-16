<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingCoupon;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingCouponAssignRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'description' => 'nullable|string|max:191',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_redemptions' => 'nullable|integer|min:0',
            'expiry_months' => ['nullable', 'string', Rule::in(array_merge(['never'], array_map('strval', BillingCouponRequest::EXPIRY_MONTH_OPTIONS)))],
            'is_active' => 'nullable|boolean',
            // Recipients can come from the picker, a CSV, or both at once - neither is
            // required on its own, but the combined set must not end up empty. Each
            // entry in user_ids[] is either a numeric existing user id (picked from
            // the dropdown) or a raw email (typed as a free-text tag) - an email with
            // no matching account is not an error, it becomes a pending recipient
            // (see resolveRecipients()).
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'string',
            'user_csv' => 'nullable|file|mimes:csv,txt|max:1024',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('discount_type') === BillingCoupon::TYPE_PERCENTAGE
                && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'A percentage discount cannot be greater than 100%.');
            }

            $recipients = $this->resolveRecipients();
            if (empty($recipients['user_ids']) && empty($recipients['pending_emails'])) {
                $validator->errors()->add('user_ids', 'Select at least one user, type an email, or upload a CSV.');
            }
        });
    }

    /**
     * Shared fields applied to every per-user coupon created from this request.
     * Code and assigned_user_id/assigned_email are filled in separately per recipient.
     */
    public function normalizeShared(): array
    {
        $data = $this->validated();

        $type = $data['discount_type'];
        $value = round((float) $data['discount_value'], 2);
        if ($type === BillingCoupon::TYPE_PERCENTAGE) {
            $value = min($value, 100);
        }

        $maxRedemptions = (int) ($data['max_redemptions'] ?? 0);
        $expiryMonths = $data['expiry_months'] ?? 'never';

        return [
            'description' => $this->normalizeDescription($data['description'] ?? null),
            'discount_type' => $type,
            'discount_value' => $value,
            'max_redemptions' => $maxRedemptions > 0 ? $maxRedemptions : null,
            'expires_at' => $expiryMonths === 'never' ? null : CarbonImmutable::now()->addMonthsNoOverflow((int) $expiryMonths),
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * Existing accounts to create a personal coupon for immediately.
     *
     * @return int[]
     */
    public function userIds(): array
    {
        return $this->resolveRecipients()['user_ids'];
    }

    /**
     * Emails with no matching account yet - still get a coupon and an email today,
     * it just activates for whichever account later registers with that address.
     *
     * @return string[]
     */
    public function pendingEmails(): array
    {
        return $this->resolveRecipients()['pending_emails'];
    }

    /**
     * Splits every entry (picker selections/typed tags + CSV rows) into existing
     * users and not-yet-registered emails. Memoized so repeated calls (the "after"
     * validator, then the controller) don't reparse the CSV or re-hit the database.
     *
     * @return array{user_ids: int[], pending_emails: string[]}
     */
    private function resolveRecipients(): array
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $picker = $this->resolvePickerEntries();
        $csv = $this->parseCsv();

        return $resolved = [
            'user_ids' => array_values(array_unique(array_merge($picker['ids'], $csv['ids']))),
            'pending_emails' => array_values(array_unique(array_merge($picker['pending'], $csv['pending']))),
        ];
    }

    /**
     * Splits user_ids[] into numeric entries (an existing user id from the
     * dropdown - verified against the table) and email-shaped entries (a tag typed
     * directly into the picker - resolved the same way a CSV row is).
     *
     * @return array{ids: int[], pending: string[]}
     */
    private function resolvePickerEntries(): array
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }

        $entries = $this->input('user_ids', []);
        $numericIds = [];
        $emails = [];

        foreach ($entries as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }

            if (ctype_digit($entry)) {
                $numericIds[] = (int) $entry;
            } elseif (filter_var($entry, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($entry);
            }
        }

        $ids = User::query()->whereIn('id', $numericIds)->pluck('id')->all();
        $emailLookup = $this->resolveEmails($emails);

        return $result = [
            'ids' => array_merge($ids, $emailLookup['ids']),
            'pending' => $emailLookup['pending'],
        ];
    }

    /**
     * @return array{ids: int[], pending: string[]}
     */
    private function parseCsv(): array
    {
        static $parsed = null;
        if ($parsed !== null) {
            return $parsed;
        }

        $file = $this->file('user_csv');
        if (!$file || !$file->isValid()) {
            return $parsed = ['ids' => [], 'pending' => []];
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return $parsed = ['ids' => [], 'pending' => []];
        }

        $emails = [];
        $emailColumn = 0;
        $firstRow = true;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if ($firstRow) {
                $firstRow = false;
                // If the header names an "email" column explicitly, use that index
                // instead of assuming column 0 - otherwise treat row 1 as data.
                $headerIndex = array_search('email', array_map(fn ($c) => strtolower(trim((string) $c)), $row), true);
                if ($headerIndex !== false) {
                    $emailColumn = $headerIndex;
                    continue;
                }
            }

            $email = strtolower(trim((string) ($row[$emailColumn] ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
        fclose($handle);

        return $parsed = $this->resolveEmails($emails);
    }

    /**
     * @param string[] $emails Already lowercased.
     * @return array{ids: int[], pending: string[]}
     */
    private function resolveEmails(array $emails): array
    {
        $emails = array_values(array_unique($emails));
        if (empty($emails)) {
            return ['ids' => [], 'pending' => []];
        }

        $matched = User::query()->whereIn('email', $emails)->get(['id', 'email'])->keyBy(fn ($u) => strtolower($u->email));

        $ids = [];
        $pending = [];
        foreach ($emails as $email) {
            if ($matched->has($email)) {
                $ids[] = $matched->get($email)->id;
            } else {
                $pending[] = $email;
            }
        }

        return ['ids' => $ids, 'pending' => $pending];
    }

    private function normalizeDescription(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
