import React, { useEffect, useState } from 'react';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { useBillingProfile, updateBillingProfile, BillingProfile } from '@/api/account/billing';
import {
    emptyBillingProfile,
    getMissingBillingProfileLabels,
    normalizeBillingProfile,
} from '@/components/billing/billingProfileUtils';

type Props = {
    cardClass: string;
};

const labelClass = 'mb-2 block text-[10px] font-bold uppercase tracking-widest text-gray-500';

export default ({ cardClass }: Props) => {
    const { addFlash } = useFlash();
    const { clearAndAddHttpError } = useFlashKey('account');
    const { data: profile, isValidating, error, mutate } = useBillingProfile();
    const [form, setForm] = useState<BillingProfile>(emptyBillingProfile);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (error) {
            clearAndAddHttpError(error);
        }
    }, [error, clearAndAddHttpError]);

    useEffect(() => {
        if (profile) {
            setForm(profile);
        }
    }, [profile]);

    const onChange = <K extends keyof BillingProfile>(key: K, value: BillingProfile[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    const onSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSaving(true);
        clearAndAddHttpError();

        try {
            const updated = await updateBillingProfile(normalizeBillingProfile(form));
            await mutate(updated, false);
            setForm(updated);
            addFlash({
                key: 'account',
                type: 'success',
                title: 'Billing Details Saved',
                message: updated.isComplete
                    ? 'Your billing details are complete and checkout can proceed.'
                    : `Billing details were saved, but checkout is still blocked until these fields are completed: ${getMissingBillingProfileLabels(
                          updated
                      )}.`,
            });
        } catch (saveError) {
            clearAndAddHttpError(saveError as Error);
        } finally {
            setSaving(false);
        }
    };

    return (
        <section className={cardClass}>
            <div className={'mb-5 flex flex-wrap items-start justify-between gap-4'}>
                <div>
                    <h2 className={'text-lg font-bold tracking-tight text-[#f8f6ef]'}>Billing Details</h2>
                    <p className={'mt-1 text-xs text-gray-400'}>
                        These details are printed on invoices and receipts. Checkout stays blocked until the required
                        fields are complete.
                    </p>
                </div>
                {profile ? (
                    <span
                        className={`rounded-xl border px-3 py-2 text-[11px] font-bold uppercase tracking-[0.18em] ${
                            profile.isComplete
                                ? 'border-[color:var(--primary)] bg-[rgba(var(--primary-rgb),0.08)] text-[color:var(--primary)]'
                                : 'border-amber-400/40 bg-amber-500/10 text-amber-200'
                        }`}
                    >
                        {profile.isComplete ? 'Checkout Ready' : 'Incomplete'}
                    </span>
                ) : null}
            </div>

            {!profile && isValidating ? (
                <Spinner centered />
            ) : (
                <form onSubmit={onSubmit} className={'grid gap-4'}>
                    {!form.isComplete && form.missingFields && form.missingFields.length > 0 && (
                        <div className={'rounded-xl border border-amber-400/35 bg-amber-500/10 px-4 py-3 text-xs leading-6 text-amber-100'}>
                            Missing required fields: {getMissingBillingProfileLabels(form)}.
                        </div>
                    )}

                    <div className={'grid gap-4 md:grid-cols-2'}>
                        <div>
                            <label className={labelClass}>Legal Name</label>
                            <Input value={form.legalName} onChange={(e) => onChange('legalName', e.currentTarget.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Invoice Email</label>
                            <Input
                                type={'email'}
                                value={form.email}
                                onChange={(e) => onChange('email', e.currentTarget.value)}
                            />
                        </div>
                        <div>
                            <label className={labelClass}>Phone</label>
                            <Input value={form.phone ?? ''} onChange={(e) => onChange('phone', e.currentTarget.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Company Name</label>
                            <Input
                                value={form.companyName ?? ''}
                                onChange={(e) => onChange('companyName', e.currentTarget.value || null)}
                            />
                        </div>
                        <div className={'md:col-span-2'}>
                            <label className={labelClass}>Address Line 1</label>
                            <Input
                                value={form.addressLine1 ?? ''}
                                onChange={(e) => onChange('addressLine1', e.currentTarget.value)}
                            />
                        </div>
                        <div className={'md:col-span-2'}>
                            <label className={labelClass}>Address Line 2</label>
                            <Input
                                value={form.addressLine2 ?? ''}
                                onChange={(e) => onChange('addressLine2', e.currentTarget.value || null)}
                            />
                        </div>
                        <div>
                            <label className={labelClass}>City</label>
                            <Input value={form.city ?? ''} onChange={(e) => onChange('city', e.currentTarget.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>State</label>
                            <Input value={form.state ?? ''} onChange={(e) => onChange('state', e.currentTarget.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Postcode</label>
                            <Input
                                value={form.postcode ?? ''}
                                onChange={(e) => onChange('postcode', e.currentTarget.value)}
                            />
                        </div>
                        <div>
                            <label className={labelClass}>Country Code</label>
                            <Input
                                maxLength={2}
                                value={form.countryCode}
                                onChange={(e) => onChange('countryCode', e.currentTarget.value.toUpperCase())}
                            />
                        </div>
                        <div>
                            <label className={labelClass}>Tax ID</label>
                            <Input value={form.taxId ?? ''} onChange={(e) => onChange('taxId', e.currentTarget.value || null)} />
                        </div>
                        <label className={'flex items-center gap-3 rounded-xl border border-[color:var(--border)] bg-[rgba(255,255,255,0.02)] px-4 py-3 text-sm text-gray-200'}>
                            <Input
                                type={'checkbox'}
                                checked={Boolean(form.isBusiness)}
                                onChange={(e) => onChange('isBusiness', e.currentTarget.checked)}
                            />
                            Business billing entity
                        </label>
                    </div>

                    <div className={'flex justify-end'}>
                        <InteractiveHoverButton
                            type={'submit'}
                            text={saving ? 'Saving...' : 'Save Billing Details'}
                            disabled={saving}
                            className={'!h-10 !min-w-[12rem] !px-5 !text-[10px]'}
                        />
                    </div>
                </form>
            )}
        </section>
    );
};
