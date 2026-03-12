import React, { useEffect, useRef, useState } from 'react';
import { Actions, useStoreActions, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import Avatar from '@/components/Avatar';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Dialog } from '@/components/elements/dialog';
import UpdateEmailAddressForm from '@/components/dashboard/forms/UpdateEmailAddressForm';
import UpdatePasswordForm from '@/components/dashboard/forms/UpdatePasswordForm';
import ConfigureTwoFactorForm from '@/components/dashboard/forms/ConfigureTwoFactorForm';
import AccountApiContainer from '@/components/dashboard/AccountApiContainer';
import AccountSSHContainer from '@/components/dashboard/ssh/AccountSSHContainer';
import LinkedAccountsContainer from '@/components/dashboard/LinkedAccountsContainer';
import DiscordCommunityCard from '@/components/dashboard/DiscordCommunityCard';
import { ActivityLogFilters, useActivityLogs } from '@/api/account/activity';
import {
    BillingProfile,
    updateBillingProfile,
    useBillingProfile,
    useBillingSubscriptions,
} from '@/api/account/billing';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import Spinner from '@/components/elements/Spinner';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { DesktopComputerIcon } from '@heroicons/react/solid';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import updateAccountAvatar from '@/api/account/updateAccountAvatar';
import removeAccountAvatar from '@/api/account/removeAccountAvatar';
import { useHistory, useLocation } from 'react-router-dom';
import { emptyBillingProfile } from '@/components/billing/billingProfileUtils';

type Tab = 'API' | 'SSH';
type ModalContent = 'EMAIL' | 'PASSWORD' | '2FA' | null;

const cardClass =
    'rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-6 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.06),0_20px_35px_rgba(12, 12, 12, 0.45)]';
const billingInputClass =
    'w-full rounded-xl border border-[color:var(--border)] bg-[rgba(5,8,14,0.72)] px-3 py-2 text-sm text-[#f8f6ef] outline-none transition focus:border-[color:var(--primary)] focus:shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.35)]';

export default () => {
    const history = useHistory();
    const location = useLocation();
    const user = useStoreState((state: ApplicationStore) => state.user.data!);
    const updateUserData = useStoreActions((actions: Actions<ApplicationStore>) => actions.user.updateUserData);
    const [activeTab, setActiveTab] = useState<Tab>('API');
    const [modal, setModal] = useState<ModalContent>(null);
    const [avatarMenuOpen, setAvatarMenuOpen] = useState(false);
    const [avatarUploading, setAvatarUploading] = useState(false);
    const avatarMenuRef = useRef<HTMLDivElement>(null);
    const avatarInputRef = useRef<HTMLInputElement>(null);
    const { addFlash, clearFlashes } = useFlash();
    const { clearAndAddHttpError } = useFlashKey('account');
    const [activityFilters, setActivityFilters] = useState<ActivityLogFilters>({
        page: 1,
        sorts: { timestamp: -1 },
    });

    const {
        data: activityData,
        isValidating: activityLoading,
        error: activityError,
    } = useActivityLogs(activityFilters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });
    const {
        data: billingProfile,
        mutate: mutateBillingProfile,
        isValidating: billingProfileLoading,
    } = useBillingProfile();
    const { data: billingSubscriptions } = useBillingSubscriptions();
    const [billingForm, setBillingForm] = useState<BillingProfile>(emptyBillingProfile);
    const [billingSaving, setBillingSaving] = useState(false);

    useEffect(() => {
        clearAndAddHttpError(activityError);
    }, [activityError]);

    useEffect(() => {
        if (billingProfile) {
            setBillingForm(billingProfile);
        }
    }, [billingProfile]);

    useEffect(() => {
        const search = new URLSearchParams(location.search);
        const status = search.get('oauth_status');
        const provider = search.get('oauth_provider');

        if (!status || !provider) {
            return;
        }

        const label = provider === 'discord' ? 'Discord' : 'Google';
        const flashes = {
            linked: {
                type: 'success' as const,
                title: 'Account Linked',
                message: `${label} sign-in is now linked to this panel account.`,
            },
            conflict: {
                type: 'error' as const,
                title: 'Link Failed',
                message: `That ${label} account is already linked to another panel user.`,
            },
            cancelled: {
                type: 'error' as const,
                title: 'Link Cancelled',
                message: `${label} linking was cancelled before it completed.`,
            },
            disabled: {
                type: 'error' as const,
                title: 'Provider Disabled',
                message: `${label} OAuth is not available right now.`,
            },
            failed: {
                type: 'error' as const,
                title: 'Link Failed',
                message: `Unable to complete ${label} account linking right now.`,
            },
            invalid_state: {
                type: 'error' as const,
                title: 'Link Expired',
                message: `The ${label} linking session expired. Start the linking flow again.`,
            },
            login_required: {
                type: 'error' as const,
                title: 'Login Required',
                message: `Your session expired before ${label} could be linked. Sign in again and retry.`,
            },
        } as const;

        const flash = flashes[status as keyof typeof flashes];
        if (flash) {
            clearFlashes('account');
            addFlash({ key: 'account', ...flash });
        }

        search.delete('oauth_status');
        search.delete('oauth_provider');
        history.replace({
            pathname: location.pathname,
            search: search.toString() ? `?${search.toString()}` : '',
        });
    }, [location.search]);

    useEffect(() => {
        if (!avatarMenuOpen) return;

        const onOutsideClick = (event: MouseEvent) => {
            if (!avatarMenuRef.current) return;
            if (event.target instanceof Node && !avatarMenuRef.current.contains(event.target)) {
                setAvatarMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', onOutsideClick);
        return () => document.removeEventListener('mousedown', onOutsideClick);
    }, [avatarMenuOpen]);

    const onAvatarUpload = async (file: File) => {
        setAvatarUploading(true);
        clearAndAddHttpError();

        try {
            const image = await updateAccountAvatar(file);
            updateUserData({ image });
            setAvatarMenuOpen(false);
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setAvatarUploading(false);
        }
    };

    const onAvatarRemove = async () => {
        setAvatarUploading(true);
        clearAndAddHttpError();

        try {
            await removeAccountAvatar();
            updateUserData({ image: undefined });
            setAvatarMenuOpen(false);
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setAvatarUploading(false);
        }
    };

    const setBillingField = <K extends keyof BillingProfile>(field: K, value: BillingProfile[K]) => {
        setBillingForm((state) => ({ ...state, [field]: value }));
    };

    const renderBillingLabel = (label: string, required = false) => (
        <span className={'mb-2 block text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500'}>
            {label}
            {required && <span className={'ml-1 text-[color:var(--primary)]'}>*</span>}
        </span>
    );

    const onBillingProfileSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setBillingSaving(true);
        clearAndAddHttpError();

        try {
            const payload: BillingProfile = {
                ...billingForm,
                countryCode: billingForm.countryCode.trim().toUpperCase() || 'MY',
                companyName: billingForm.companyName?.trim() || null,
                phone: billingForm.phone?.trim() || null,
                addressLine1: billingForm.addressLine1?.trim() || null,
                addressLine2: billingForm.addressLine2?.trim() || null,
                city: billingForm.city?.trim() || null,
                state: billingForm.state?.trim() || null,
                postcode: billingForm.postcode?.trim() || null,
                taxId: billingForm.taxId?.trim() || null,
            };
            const updated = await updateBillingProfile(payload);

            await mutateBillingProfile(updated, false);
            clearFlashes('account');
            addFlash({
                key: 'account',
                type: 'success',
                title: 'Billing Profile Updated',
                message: 'Invoice identity and billing address have been saved.',
            });
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setBillingSaving(false);
        }
    };

    const billingSubscriptionCount = billingSubscriptions?.length ?? 0;
    const autoRenewEnabledCount = billingSubscriptions?.filter((subscription) => subscription.autoRenew).length ?? 0;

    return (
        <div className={'account-theme account-auth-shell min-h-screen px-4 pb-8 pt-6 text-white md:px-8 md:pt-8'}>
            <style>{`
                .account-auth-shell {
                    position: relative;
                    overflow: hidden;
                    background:
                        radial-gradient(circle at 8% 0%, rgba(var(--primary-rgb), 0.18), transparent 40%),
                        radial-gradient(circle at 94% 100%, rgba(84, 140, 255, 0.2), transparent 44%),
                        linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1));
                    font-family: var(--font-sans, 'Inter', sans-serif);
                }

                .account-auth-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        repeating-linear-gradient(
                            90deg,
                            rgba(255, 255, 255, 0.014) 0,
                            rgba(255, 255, 255, 0.014) 1px,
                            transparent 1px,
                            transparent 40px
                        );
                    opacity: 0.2;
                }

                .account-auth-shell::after {
                    content: '';
                    position: absolute;
                    left: 50%;
                    top: -18%;
                    width: min(1120px, 96vw);
                    height: 110%;
                    transform: translateX(-50%);
                    pointer-events: none;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(112, 168, 255, 0.08) 0%,
                        rgba(112, 168, 255, 0.03) 42%,
                        transparent 72%
                    );
                }

                .account-theme > * {
                    position: relative;
                    z-index: 2;
                }

                .account-theme {
                    --neon-green: var(--primary);
                }

                .account-theme .activity-feed-shell .bg-gray-700 {
                    background-color: transparent !important;
                }

                .account-theme .activity-feed-shell .bg-gray-600 {
                    background-color: #0f172a !important;
                }

                .account-theme .activity-feed-shell .grid {
                    min-height: 104px;
                    border-color: rgba(var(--primary-rgb), 0.18) !important;
                }

                .account-theme .activity-feed-shell .group:hover {
                    background-color: rgba(var(--primary-rgb), 0.05) !important;
                }

                .account-theme .activity-feed-shell .text-gray-50 {
                    color: #f8f6ef !important;
                    font-weight: 700;
                }

                .account-theme .activity-feed-shell .text-gray-400 {
                    color: #9ca3af !important;
                }

                .account-theme .activity-feed-shell .elements-activity-style-module__description {
                    color: #cbd5e1 !important;
                }

                .account-theme .activity-feed-shell .elements-activity-style-module__description strong {
                    color: #f8f6ef !important;
                }

                .account-theme .activity-feed-shell .elements-activity-style-module__icons {
                    color: #94a3b8 !important;
                }

                .account-theme .activity-feed-shell a {
                    color: #d1d5db !important;
                }

                .account-theme .activity-feed-shell a:hover {
                    color: var(--neon-green) !important;
                }

                .account-theme .activity-feed-shell .self-center button {
                    color: #9ca3af !important;
                }

                .account-theme .activity-feed-shell .self-center button:hover {
                    color: var(--neon-green) !important;
                }

                .account-theme .activity-pagination-top .my-2 {
                    margin: 0 !important;
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 14px;
                }

                .account-theme .activity-pagination-top p {
                    margin: 0 !important;
                    color: #9ca3af !important;
                    font-size: 11px !important;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .account-theme .activity-pagination-top span {
                    color: #f8f6ef !important;
                    font-weight: 700 !important;
                }

                .account-theme .activity-pagination-top button {
                    color: #9ca3af !important;
                }

                .account-theme .activity-pagination-top button:hover {
                    color: var(--neon-green) !important;
                }

                .account-theme .account-tabs-shell .bg-white {
                    background-color: transparent !important;
                }

                .account-theme .account-tabs-shell > div,
                .account-theme .account-tabs-shell > div > div {
                    background-color: transparent !important;
                }

                .account-theme .account-tabs-shell section {
                    background-color: var(--card) !important;
                    border: 1px solid var(--border) !important;
                    border-radius: 0.75rem !important;
                    padding: 1.5rem !important;
                }

                .account-theme .account-tabs-shell .text-[color:var(--primary-foreground)] {
                    color: #f8f6ef !important;
                }

                .account-theme .account-tabs-shell .text-neutral-500 {
                    color: #9ca3af !important;
                }

                .account-theme .account-tabs-shell .text-neutral-700 {
                    color: #cbd5e1 !important;
                }

                .account-theme .account-tabs-shell .border-[#0C0C0C],
                .account-theme .account-tabs-shell .border-neutral-200 {
                    border-color: rgba(var(--primary-rgb), 0.25) !important;
                }

                .account-theme .account-tabs-shell h2 {
                    color: #f8f6ef !important;
                }

                .account-theme .account-tabs-shell input,
                .account-theme .account-tabs-shell textarea,
                .account-theme .account-tabs-shell select {
                    background-color: var(--card) !important;
                    border-color: rgba(var(--primary-rgb), 0.28) !important;
                    color: #f8f6ef !important;
                    border-radius: 8px !important;
                }

                .account-theme .account-tabs-shell input::placeholder,
                .account-theme .account-tabs-shell textarea::placeholder {
                    color: rgba(248, 246, 239, 0.45) !important;
                }

                .account-theme .account-tabs-shell input:focus,
                .account-theme .account-tabs-shell textarea:focus,
                .account-theme .account-tabs-shell select:focus {
                    border-color: var(--neon-green) !important;
                    box-shadow: 0 0 0 1px rgba(var(--primary-rgb), 0.35), 0 0 0 4px rgba(var(--primary-rgb), 0.1) !important;
                }

                .account-theme .account-tabs-shell label {
                    color: #d1d5db !important;
                }

                .account-theme .account-tabs-shell code {
                    background-color: var(--card) !important;
                    color: var(--primary) !important;
                    border: 1px solid rgba(var(--primary-rgb), 0.32) !important;
                    border-radius: 6px !important;
                }

                .account-theme .account-tabs-shell button[type='submit'],
                .account-theme .account-tabs-shell .Button__ButtonStyle-sc-1qu1gou-0 {
                    border-color: var(--neon-green) !important;
                    background-color: var(--neon-green) !important;
                    color: var(--primary-foreground) !important;
                    border-radius: 8px !important;
                    box-shadow: 0 0 14px rgba(var(--primary-rgb), 0.25) !important;
                }

                .account-theme .account-tabs-shell button[type='submit']:hover,
                .account-theme .account-tabs-shell .Button__ButtonStyle-sc-1qu1gou-0:hover {
                    filter: brightness(1.06) !important;
                    box-shadow: 0 0 18px rgba(var(--primary-rgb), 0.35) !important;
                }

                .account-theme .account-tabs-shell button .text-neutral-400,
                .account-theme .account-tabs-shell button svg {
                    color: #94a3b8 !important;
                }

                .account-theme .account-tabs-shell button:hover svg {
                    color: #ef4444 !important;
                }
            `}</style>

            <Dialog open={modal === 'EMAIL'} onClose={() => setModal(null)} title={'Update Email Address'}>
                <UpdateEmailAddressForm />
            </Dialog>
            <Dialog open={modal === 'PASSWORD'} onClose={() => setModal(null)} title={'Update Password'}>
                <UpdatePasswordForm />
            </Dialog>
            <Dialog open={modal === '2FA'} onClose={() => setModal(null)} title={'Two-Step Verification'}>
                <ConfigureTwoFactorForm />
            </Dialog>

            <FlashMessageRender byKey={'account'} />
            <div className={'mb-6 flex w-full flex-col gap-6 xl:flex-row xl:items-stretch xl:justify-between'}>
                <section
                    className={
                        'h-full min-h-[96px] w-full rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.05)] xl:max-w-[620px] xl:flex-none'
                    }
                >
                    <div className={'flex h-full flex-wrap items-center gap-4'}>
                        <div ref={avatarMenuRef} className={'relative'}>
                            <button
                                type={'button'}
                                onClick={() => setAvatarMenuOpen((value) => !value)}
                                disabled={avatarUploading}
                                className={
                                    'h-16 w-16 overflow-hidden rounded-lg border border-[color:var(--primary)] bg-[color:var(--card)] transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60'
                                }
                            >
                                <Avatar.User size={64} variant={'beam'} />
                            </button>

                            {avatarMenuOpen && (
                                <div
                                    className={
                                        'absolute left-0 top-[calc(100%+0.5rem)] z-30 w-44 rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-2 shadow-[0_18px_32px_rgba(0,0,0,0.4)]'
                                    }
                                >
                                    <button
                                        type={'button'}
                                        className={
                                            'w-full rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-wide text-[color:var(--foreground)] transition-colors hover:bg-[color:var(--accent)]'
                                        }
                                        onClick={() => avatarInputRef.current?.click()}
                                        disabled={avatarUploading}
                                    >
                                        {avatarUploading ? 'Uploading...' : 'Change Image'}
                                    </button>
                                    <button
                                        type={'button'}
                                        className={
                                            'mt-1 w-full rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-wide text-red-300 transition-colors hover:bg-red-500/10 disabled:opacity-50'
                                        }
                                        onClick={onAvatarRemove}
                                        disabled={avatarUploading || !user.image}
                                    >
                                        Remove Image
                                    </button>
                                    <input
                                        ref={avatarInputRef}
                                        type={'file'}
                                        accept={'image/png,image/jpeg,image/jpg,image/webp,image/gif'}
                                        className={'hidden'}
                                        onChange={(event) => {
                                            const file = event.currentTarget.files?.[0];
                                            if (file) void onAvatarUpload(file);
                                            event.currentTarget.value = '';
                                        }}
                                    />
                                </div>
                            )}
                        </div>
                        <div className={'min-w-0'}>
                            <div className={'flex flex-wrap items-center gap-2'}>
                                <h1 className={'truncate text-2xl font-black tracking-tight text-[#f8f6ef]'}>
                                    {user.username}
                                </h1>
                                {user.rootAdmin && (
                                    <span
                                        className={
                                            'rounded-lg border border-[color:var(--primary)] bg-[color:var(--primary)]/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[color:var(--primary)]'
                                        }
                                    >
                                        Administrator
                                    </span>
                                )}
                            </div>
                            <p className={'mt-1 text-xs text-gray-400'}>{user.email}</p>
                        </div>
                    </div>
                </section>

                <div className={'w-full xl:ml-auto xl:max-w-[620px] xl:flex-none'}>
                    <DiscordCommunityCard />
                </div>
            </div>

            <div className={'grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_1fr]'}>
                <div className={'flex flex-col gap-6'}>
                    <section className={cardClass}>
                        <h2 className={'mb-5 text-lg font-bold tracking-tight text-[#f8f6ef]'}>Account Information</h2>

                        <div
                            className={
                                'mb-4 flex flex-wrap items-center justify-between gap-4 border-b border-[color:var(--border)] pb-4'
                            }
                        >
                            <div className={'min-w-[220px]'}>
                                <p className={'mb-1 text-[10px] uppercase tracking-widest text-gray-500'}>Email</p>
                                <p className={'text-sm text-gray-100'}>{user.email}</p>
                            </div>
                            <InteractiveHoverButton
                                onClick={() => setModal('EMAIL')}
                                type={'button'}
                                text={'Edit'}
                                className={'!h-9 !min-w-[8rem] !px-4 !text-[10px]'}
                            />
                        </div>

                        <div
                            className={
                                'mb-4 flex flex-wrap items-center justify-between gap-4 border-b border-[color:var(--border)] pb-4'
                            }
                        >
                            <div>
                                <p className={'mb-1 text-[10px] uppercase tracking-widest text-gray-500'}>Password</p>
                                <p className={'text-sm text-gray-100'}>********</p>
                            </div>
                            <InteractiveHoverButton
                                onClick={() => setModal('PASSWORD')}
                                type={'button'}
                                text={'Change'}
                                className={'!h-9 !min-w-[8rem] !px-4 !text-[10px]'}
                            />
                        </div>

                        <div className={'flex flex-wrap items-center justify-between gap-4'}>
                            <div>
                                <p className={'mb-1 text-[10px] uppercase tracking-widest text-gray-500'}>
                                    Two-Step Verification
                                </p>
                                <p
                                    className={`text-sm font-bold ${
                                        user.useTotp ? 'text-[color:var(--primary)]' : 'text-red-400'
                                    }`}
                                >
                                    {user.useTotp ? 'Currently enabled' : 'Currently disabled'}
                                </p>
                            </div>
                            <InteractiveHoverButton
                                onClick={() => setModal('2FA')}
                                type={'button'}
                                text={user.useTotp ? 'Disable' : 'Enable'}
                                variant={user.useTotp ? 'danger' : 'success'}
                                className={'!h-9 !min-w-[8rem] !px-4 !text-[10px]'}
                            />
                        </div>
                    </section>

                    <section className={cardClass}>
                        <div className={'mb-5 flex flex-wrap items-start justify-between gap-4'}>
                            <div>
                                <h2 className={'text-lg font-bold tracking-tight text-[#f8f6ef]'}>Billing Profile</h2>
                                <p className={'mt-1 text-xs text-gray-400'}>
                                    Saved here once, then copied into each invoice snapshot before checkout.
                                </p>
                                <p className={'mt-2 text-[11px] text-[color:var(--primary)]'}>
                                    Fields marked with * are required before any invoice checkout can start.
                                </p>
                            </div>
                            <div className={'flex flex-wrap gap-2'}>
                                <span
                                    className={
                                        'rounded-xl border border-[color:var(--border)] bg-[rgba(255,255,255,0.03)] px-3 py-2 text-[11px] font-bold uppercase tracking-[0.18em] text-gray-300'
                                    }
                                >
                                    {autoRenewEnabledCount}/{billingSubscriptionCount} Auto Renew
                                </span>
                                <span
                                    className={
                                        'rounded-xl border border-[color:var(--border)] bg-[rgba(var(--primary-rgb),0.08)] px-3 py-2 text-[11px] font-bold uppercase tracking-[0.18em] text-[color:var(--primary)]'
                                    }
                                >
                                    {billingForm.countryCode || 'MY'} Billing Country
                                </span>
                            </div>
                        </div>

                        {!billingProfile && billingProfileLoading ? (
                            <Spinner centered />
                        ) : (
                            <form className={'space-y-5'} onSubmit={onBillingProfileSubmit}>
                                <div className={'grid grid-cols-1 gap-4 md:grid-cols-2'}>
                                    <label className={'block'}>
                                        {renderBillingLabel('Legal Name', true)}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.legalName}
                                            onChange={(event) => setBillingField('legalName', event.currentTarget.value)}
                                            maxLength={191}
                                            required
                                        />
                                    </label>
                                    <label className={'block'}>
                                        {renderBillingLabel('Company Name')}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.companyName ?? ''}
                                            onChange={(event) =>
                                                setBillingField('companyName', event.currentTarget.value || null)
                                            }
                                            maxLength={191}
                                        />
                                    </label>
                                    <label className={'block'}>
                                        {renderBillingLabel('Invoice Email', true)}
                                        <input
                                            type={'email'}
                                            className={billingInputClass}
                                            value={billingForm.email}
                                            onChange={(event) => setBillingField('email', event.currentTarget.value)}
                                            maxLength={191}
                                            required
                                        />
                                    </label>
                                    <label className={'block'}>
                                        {renderBillingLabel('Phone', true)}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.phone ?? ''}
                                            onChange={(event) => setBillingField('phone', event.currentTarget.value || null)}
                                            maxLength={32}
                                            required
                                        />
                                    </label>
                                </div>

                                <div className={'grid grid-cols-1 gap-4 md:grid-cols-2'}>
                                    <label className={'block md:col-span-2'}>
                                        {renderBillingLabel('Address Line 1', true)}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.addressLine1 ?? ''}
                                            onChange={(event) =>
                                                setBillingField('addressLine1', event.currentTarget.value || null)
                                            }
                                            maxLength={191}
                                            required
                                        />
                                    </label>
                                    <label className={'block md:col-span-2'}>
                                        {renderBillingLabel('Address Line 2')}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.addressLine2 ?? ''}
                                            onChange={(event) =>
                                                setBillingField('addressLine2', event.currentTarget.value || null)
                                            }
                                            maxLength={191}
                                        />
                                    </label>
                                    <label className={'block'}>
                                        {renderBillingLabel('City', true)}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.city ?? ''}
                                            onChange={(event) => setBillingField('city', event.currentTarget.value || null)}
                                            maxLength={191}
                                            required
                                        />
                                    </label>
                                    <label className={'block'}>
                                        {renderBillingLabel('State')}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.state ?? ''}
                                            onChange={(event) => setBillingField('state', event.currentTarget.value || null)}
                                            maxLength={191}
                                        />
                                    </label>
                                    <label className={'block'}>
                                        {renderBillingLabel('Postcode', true)}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.postcode ?? ''}
                                            onChange={(event) =>
                                                setBillingField('postcode', event.currentTarget.value || null)
                                            }
                                            maxLength={32}
                                            required
                                        />
                                    </label>
                                    <label className={'block'}>
                                        {renderBillingLabel('Country Code', true)}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.countryCode}
                                            onChange={(event) =>
                                                setBillingField('countryCode', event.currentTarget.value.toUpperCase())
                                            }
                                            maxLength={2}
                                            required
                                        />
                                    </label>
                                </div>

                                <div className={'grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto]'}>
                                    <label className={'block'}>
                                        {renderBillingLabel('Tax ID')}
                                        <input
                                            className={billingInputClass}
                                            value={billingForm.taxId ?? ''}
                                            onChange={(event) => setBillingField('taxId', event.currentTarget.value || null)}
                                            maxLength={191}
                                        />
                                    </label>
                                    <label
                                        className={
                                            'flex items-center gap-3 rounded-xl border border-[color:var(--border)] bg-[rgba(255,255,255,0.03)] px-4 py-3 text-sm text-gray-200'
                                        }
                                    >
                                        <input
                                            type={'checkbox'}
                                            className={'h-4 w-4 rounded border-[color:var(--border)] bg-transparent'}
                                            checked={billingForm.isBusiness}
                                            onChange={(event) =>
                                                setBillingField('isBusiness', event.currentTarget.checked)
                                            }
                                        />
                                        <span>Business billing entity</span>
                                    </label>
                                </div>

                                <div className={'flex flex-wrap items-center justify-between gap-4 border-t border-[color:var(--border)] pt-4'}>
                                    <p className={'max-w-[38rem] text-xs leading-6 text-gray-400'}>
                                        These values are copied into each invoice so future changes do not rewrite your old billing records.
                                    </p>
                                    <InteractiveHoverButton
                                        type={'submit'}
                                        text={billingSaving ? 'Saving...' : 'Save Billing Profile'}
                                        className={'!h-10 !min-w-[12rem] !px-5 !text-[10px]'}
                                        disabled={billingSaving}
                                    />
                                </div>
                            </form>
                        )}
                    </section>

                    <section className={cardClass}>
                        <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                            <h2 className={'text-lg font-bold tracking-tight text-[#f8f6ef]'}>Recent Activity</h2>
                            {activityData && (
                                <div className={'activity-pagination-top'}>
                                    <PaginationFooter
                                        pagination={activityData.pagination}
                                        onPageSelect={(page) => setActivityFilters((value) => ({ ...value, page }))}
                                    />
                                </div>
                            )}
                        </div>

                        {!activityData && activityLoading ? (
                            <Spinner centered />
                        ) : (
                            <div className={'max-h-[520px] overflow-y-auto pr-1'}>
                                <div className={'activity-feed-shell'}>
                                    <div className={'bg-gray-700'}>
                                        {activityData?.items.length ? (
                                            activityData.items.map((activity) => (
                                                <ActivityLogEntry key={activity.id} activity={activity}>
                                                    {typeof activity.properties.useragent === 'string' && (
                                                        <Tooltip
                                                            content={activity.properties.useragent}
                                                            placement={'top'}
                                                        >
                                                            <span>
                                                                <DesktopComputerIcon />
                                                            </span>
                                                        </Tooltip>
                                                    )}
                                                </ActivityLogEntry>
                                            ))
                                        ) : (
                                            <p className={'py-8 text-center text-xs text-gray-500'}>
                                                No activity found for this account.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </section>
                </div>

                <section className={'rounded-xl border border-[color:var(--border)] bg-[color:var(--card)]'}>
                    <div className={'grid grid-cols-2 border-b border-[color:var(--border)]'}>
                        <button
                            onClick={() => setActiveTab('API')}
                            className={`px-4 py-3 text-[11px] font-bold uppercase tracking-widest transition ${
                                activeTab === 'API'
                                    ? 'bg-[color:var(--primary)] text-[color:var(--primary-foreground)] shadow-[0_0_14px_rgba(var(--primary-rgb), 0.35)]'
                                    : 'text-[color:var(--muted-foreground)] hover:text-[color:var(--foreground)]'
                            }`}
                            type={'button'}
                        >
                            API Keys
                        </button>
                        <button
                            onClick={() => setActiveTab('SSH')}
                            className={`px-4 py-3 text-[11px] font-bold uppercase tracking-widest transition ${
                                activeTab === 'SSH'
                                    ? 'bg-[color:var(--primary)] text-[color:var(--primary-foreground)] shadow-[0_0_14px_rgba(var(--primary-rgb), 0.35)]'
                                    : 'text-[color:var(--muted-foreground)] hover:text-[color:var(--foreground)]'
                            }`}
                            type={'button'}
                        >
                            SSH Keys
                        </button>
                    </div>

                    <div className={'account-tabs-shell min-w-0 px-4 pb-4 pt-5'}>
                        {activeTab === 'API' && <AccountApiContainer />}
                        {activeTab === 'SSH' && <AccountSSHContainer />}
                        <LinkedAccountsContainer />
                    </div>
                </section>
            </div>
        </div>
    );
};
