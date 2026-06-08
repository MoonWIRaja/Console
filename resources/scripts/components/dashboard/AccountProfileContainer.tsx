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
import BillingDetailsCard from '@/components/dashboard/BillingDetailsCard';
import { ActivityLogFilters, useActivityLogs } from '@/api/account/activity';
import { useBillingProfile } from '@/api/account/billing';
import { getMissingBillingProfileLabels, isBillingProfileComplete } from '@/components/billing/billingProfileUtils';
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

type Tab = 'API' | 'SSH';
type ModalContent = 'EMAIL' | 'PASSWORD' | '2FA' | 'BILLING' | null;

const cardClass =
    'sc-card min-w-0 p-6';

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
    const { data: billingProfile } = useBillingProfile();
    const billingReady = billingProfile ? isBillingProfileComplete(billingProfile) : false;
    const billingMissingLabels = billingProfile ? getMissingBillingProfileLabels(billingProfile) : '';

    const {
        data: activityData,
        isValidating: activityLoading,
        error: activityError,
    } = useActivityLogs(activityFilters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });
    useEffect(() => {
        clearAndAddHttpError(activityError);
    }, [activityError]);

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

    return (
        <div
            className={
                'account-theme account-auth-shell flex-1 h-full min-h-0 px-4 pb-8 pt-6 text-[#742220] md:px-8 md:pt-8'
            }
        >
            <style>{`
                .account-theme {
                    --account-ink: #742220;
                    --account-border: #2D4A3E;
                    --account-card: #FEF9E1;
                    --account-card-soft: #F5EFD5;
                    --account-card-muted: #EDE6D0;
                    --account-muted: rgba(116, 34, 32, 0.58);
                    --account-paper-texture:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px);
                    --neon-green: var(--primary);
                }

                .account-theme .sc-card {
                    border-radius: 22px;
                    border: 2px solid var(--account-border);
                    background-color: var(--account-card);
                    background-image: var(--account-paper-texture);
                    box-shadow: 4px 4px 0px 0px var(--account-border);
                    color: var(--account-ink);
                }

                .account-theme .sc-card-inner {
                    border-radius: 18px;
                    border: 1px solid var(--account-card-muted);
                    background-color: var(--account-card-soft);
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.025) 4.5px, rgba(116, 34, 32, 0.025) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.025) 4.5px, rgba(116, 34, 32, 0.025) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.025) 4.5px, rgba(116, 34, 32, 0.025) 5px);
                    color: var(--account-ink);
                }

                .account-auth-shell {
                    position: relative;
                    display: flex;
                    flex: 1;
                    flex-direction: column;
                    overflow-y: auto;
                    overflow-x: hidden;
                    height: 100%;
                    min-height: 0;
                    min-width: 0;
                    -webkit-overflow-scrolling: touch;
                    background: #D6D2C7;
                    font-family: var(--font-sans, 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
                }

                .account-auth-shell::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    pointer-events: none;
                    z-index: 9999;
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px);
                }

                .account-auth-shell::after {
                    display: none;
                }

                .account-theme > * {
                    position: relative;
                    z-index: 2;
                }

                .account-theme > .account-profile-top-grid {
                    z-index: 6;
                }

                .account-theme > .account-profile-content-grid {
                    z-index: 2;
                }

                .account-avatar-menu-anchor {
                    z-index: 7;
                }

                .account-avatar-menu-popover {
                    z-index: 8;
                }

                .account-theme .text-white,
                .account-theme .text-gray-100,
                .account-theme .text-neutral-100,
                .account-theme .text-slate-300,
                .account-theme .text-neutral-300 {
                    color: var(--account-ink) !important;
                }

                .account-theme .text-gray-400,
                .account-theme .text-gray-500,
                .account-theme .text-neutral-400,
                .account-theme .text-neutral-500,
                .account-theme .text-slate-400,
                .account-theme .text-slate-500 {
                    color: var(--account-muted) !important;
                }

                .account-theme .bg-\\[color\\:var\\(--card\\)\\],
                .account-theme .bg-\\[color\\:var\\(--background\\)\\] {
                    background-color: var(--account-card-soft) !important;
                }

                .account-theme .border-\\[color\\:var\\(--border\\)\\] {
                    border-color: var(--account-card-muted) !important;
                }

                .account-theme input,
                .account-theme textarea,
                .account-theme select {
                    background-color: var(--account-card) !important;
                    border-color: var(--account-border) !important;
                    color: var(--account-ink) !important;
                }

                .account-theme input::placeholder,
                .account-theme textarea::placeholder {
                    color: rgba(116, 34, 32, 0.42) !important;
                }

                .account-theme label,
                .account-theme .input-help,
                .account-theme p {
                    color: var(--account-ink);
                }

                .account-theme .input-help,
                .account-theme small {
                    color: var(--account-muted) !important;
                }

                .account-theme code {
                    border: 1px solid var(--account-border) !important;
                    background-color: var(--account-card) !important;
                    color: var(--account-border) !important;
                }

                .account-theme .activity-feed-shell .bg-gray-700 {
                    background-color: var(--account-card-soft) !important;
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.025) 4.5px, rgba(116, 34, 32, 0.025) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.025) 4.5px, rgba(116, 34, 32, 0.025) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.025) 4.5px, rgba(116, 34, 32, 0.025) 5px);
                    border-radius: 18px;
                }

                .account-theme .activity-feed-shell .bg-gray-600 {
                    background-color: var(--account-card-soft) !important;
                }

                .account-theme .activity-feed-shell .grid {
                    min-height: 104px;
                    border-color: var(--account-card-muted) !important;
                }

                .account-theme .activity-feed-shell .group:hover {
                    background-color: rgba(45, 74, 62, 0.08) !important;
                }

                .account-theme .activity-feed-shell .text-gray-50 {
                    color: var(--account-ink) !important;
                    font-weight: 700;
                }

                .account-theme .activity-feed-shell .text-gray-400 {
                    color: var(--account-muted) !important;
                }

                .account-theme .activity-feed-shell .elements-activity-style-module__description {
                    color: var(--account-ink) !important;
                }

                .account-theme .activity-feed-shell .elements-activity-style-module__description strong {
                    color: var(--account-ink) !important;
                }

                .account-theme .activity-feed-shell .elements-activity-style-module__icons {
                    color: var(--account-muted) !important;
                }

                .account-theme .activity-feed-shell a {
                    color: var(--account-border) !important;
                }

                .account-theme .activity-feed-shell a:hover {
                    color: var(--neon-green) !important;
                }

                .account-theme .activity-feed-shell .self-center button {
                    color: var(--account-muted) !important;
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
                    color: var(--account-muted) !important;
                    font-size: 11px !important;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .account-theme .activity-pagination-top span {
                    color: var(--account-ink) !important;
                    font-weight: 700 !important;
                }

                .account-theme .activity-pagination-top button {
                    color: var(--account-muted) !important;
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
                    background-color: var(--account-card-soft) !important;
                    border: 1px solid var(--account-card-muted) !important;
                    border-radius: 18px !important;
                    padding: 1.5rem !important;
                }

                .account-theme .account-tabs-shell .text-[color:var(--primary-foreground)] {
                    color: #FEF9E1 !important;
                }

                .account-theme .account-tabs-shell .text-neutral-500 {
                    color: var(--account-muted) !important;
                }

                .account-theme .account-tabs-shell .text-neutral-700 {
                    color: var(--account-ink) !important;
                }

                .account-theme .account-tabs-shell .border-[#0C0C0C],
                .account-theme .account-tabs-shell .border-neutral-200 {
                    border-color: var(--account-card-muted) !important;
                }

                .account-theme .account-tabs-shell h2 {
                    color: var(--account-ink) !important;
                }

                .account-theme .account-tabs-shell input,
                .account-theme .account-tabs-shell textarea,
                .account-theme .account-tabs-shell select {
                    background-color: var(--account-card) !important;
                    border-color: var(--account-border) !important;
                    color: var(--account-ink) !important;
                    border-radius: 8px !important;
                }

                .account-theme .account-tabs-shell input::placeholder,
                .account-theme .account-tabs-shell textarea::placeholder {
                    color: rgba(116, 34, 32, 0.42) !important;
                }

                .account-theme .account-tabs-shell input:focus,
                .account-theme .account-tabs-shell textarea:focus,
                .account-theme .account-tabs-shell select:focus {
                    border-color: var(--neon-green) !important;
                    box-shadow: 0 0 0 1px rgba(var(--primary-rgb), 0.35), 0 0 0 4px rgba(var(--primary-rgb), 0.1) !important;
                }

                .account-theme .account-tabs-shell label {
                    color: var(--account-ink) !important;
                }

                .account-theme .account-tabs-shell code {
                    background-color: var(--account-card) !important;
                    color: var(--account-border) !important;
                    border: 1px solid var(--account-border) !important;
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
                    color: var(--account-muted) !important;
                }

                .account-theme .account-tabs-shell button:hover svg {
                    color: #ef4444 !important;
                }

                .account-billing-dialog-wrapper,
                .account-billing-dialog-panel,
                .account-billing-dialog-panel > div,
                .account-billing-dialog-panel .account-billing-dialog-scroll {
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                }

                .account-billing-dialog-wrapper::-webkit-scrollbar,
                .account-billing-dialog-panel::-webkit-scrollbar,
                .account-billing-dialog-panel > div::-webkit-scrollbar,
                .account-billing-dialog-panel .account-billing-dialog-scroll::-webkit-scrollbar {
                    display: none;
                    width: 0;
                    height: 0;
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
            <Dialog
                open={modal === 'BILLING'}
                onClose={() => setModal(null)}
                title={'Billing Details'}
                description={'Update the details used on invoices and receipts.'}
                panelClassName={'account-billing-dialog-panel !max-w-4xl overflow-hidden'}
                contentClassName={'account-billing-dialog-scroll max-h-[78vh] overflow-y-auto'}
                scrollWrapperClassName={'account-billing-dialog-wrapper'}
            >
                <BillingDetailsCard variant={'dialog'} onSaved={() => setModal(null)} />
            </Dialog>

            <FlashMessageRender byKey={'account'} />
            <div
                className={
                    'account-profile-top-grid mb-6 grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]'
                }
            >
                <section
                    className={
                        'sc-card min-w-0 overflow-visible p-4'
                    }
                >
                    <div className={'flex h-full min-w-0 flex-wrap items-center gap-4'}>
                        <div ref={avatarMenuRef} className={'account-avatar-menu-anchor relative'}>
                            <button
                                type={'button'}
                                onClick={() => setAvatarMenuOpen((value) => !value)}
                                disabled={avatarUploading}
                                className={
                                    'h-16 w-16 overflow-hidden rounded-[16px] border-2 border-[#2D4A3E] bg-[#F5EFD5] transition-all hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60'
                                }
                            >
                                <Avatar.User size={64} variant={'beam'} />
                            </button>

                            {avatarMenuOpen && (
                                <div
                                    className={
                                        'account-avatar-menu-popover sc-card absolute left-0 top-[calc(100%+0.5rem)] w-44 p-2'
                                    }
                                >
                                    <button
                                        type={'button'}
                                        className={
                                            'w-full rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-wide text-[#742220] transition-colors hover:bg-[rgba(45,74,62,0.1)]'
                                        }
                                        onClick={() => avatarInputRef.current?.click()}
                                        disabled={avatarUploading}
                                    >
                                        {avatarUploading ? 'Uploading...' : 'Change Image'}
                                    </button>
                                    <button
                                        type={'button'}
                                        className={
                                            'mt-1 w-full rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-wide text-red-700 transition-colors hover:bg-red-500/10 disabled:opacity-50'
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
                        <div className={'min-w-0 flex-1'}>
                            <div className={'flex flex-wrap items-center gap-2'}>
                                <h1 className={'truncate text-2xl font-black tracking-tight text-[#742220]'}>
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
                            <p className={'mt-1 text-xs text-[rgba(116,34,32,0.58)]'}>{user.email}</p>
                        </div>
                    </div>
                </section>

                <div className={'min-w-0'}>
                    <DiscordCommunityCard />
                </div>
            </div>

            <div className={'account-profile-content-grid grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-[1.2fr_1fr]'}>
                <div className={'flex min-w-0 flex-col gap-6'}>
                    <section className={cardClass}>
                        <h2 className={'mb-5 text-lg font-bold tracking-tight text-[#742220]'}>Account Information</h2>

                        <div className={'grid min-w-0 items-start gap-4 lg:grid-cols-2'}>
                            <div
                                className={
                                    'sc-card-inner flex min-w-0 flex-col p-4'
                                }
                            >
                                <div className={'min-w-0'}>
                                    <p className={'mb-1 text-[10px] uppercase tracking-widest text-[rgba(116,34,32,0.58)]'}>Email</p>
                                    <p className={'break-all text-sm text-[#742220]'}>{user.email}</p>
                                </div>
                                <InteractiveHoverButton
                                    onClick={() => setModal('EMAIL')}
                                    type={'button'}
                                    text={'Edit'}
                                    className={
                                        'mt-4 w-full sm:w-auto !h-9 !min-w-0 sm:!min-w-[8rem] !px-4 !text-[10px] sm:self-start'
                                    }
                                />
                            </div>

                            <div
                                className={
                                    'sc-card-inner flex min-w-0 flex-col p-4'
                                }
                            >
                                <div className={'min-w-0'}>
                                    <p className={'mb-1 text-[10px] uppercase tracking-widest text-[rgba(116,34,32,0.58)]'}>
                                        Password
                                    </p>
                                    <p className={'text-sm text-[#742220]'}>********</p>
                                </div>
                                <InteractiveHoverButton
                                    onClick={() => setModal('PASSWORD')}
                                    type={'button'}
                                    text={'Change'}
                                    className={
                                        'mt-4 w-full sm:w-auto !h-9 !min-w-0 sm:!min-w-[8rem] !px-4 !text-[10px] sm:self-start'
                                    }
                                />
                            </div>

                            <div
                                className={
                                    'sc-card-inner flex min-w-0 flex-col p-4'
                                }
                            >
                                <div className={'flex min-w-0 flex-wrap items-start justify-between gap-3'}>
                                    <div className={'min-w-0 flex-1'}>
                                        <p className={'mb-1 text-[10px] uppercase tracking-widest text-[rgba(116,34,32,0.58)]'}>
                                            Billing Details
                                        </p>
                                        <p
                                            className={`text-sm font-bold ${
                                                billingProfile
                                                    ? billingReady
                                                        ? 'text-[color:var(--primary)]'
                                                        : 'text-amber-300'
                                                    : 'text-[rgba(116,34,32,0.62)]'
                                            }`}
                                        >
                                            {billingProfile
                                                ? billingReady
                                                    ? 'Checkout ready'
                                                    : 'Billing profile incomplete'
                                                : 'Checking billing profile...'}
                                        </p>
                                        {billingProfile && !billingReady && billingMissingLabels && (
                                            <p className={'mt-1 break-words text-xs text-amber-100/90'}>
                                                Missing: {billingMissingLabels}
                                            </p>
                                        )}
                                    </div>
                                    {billingProfile && (
                                        <span
                                            className={`shrink-0 rounded-xl border px-3 py-2 text-center text-[11px] font-bold uppercase tracking-[0.18em] ${
                                                billingReady
                                                    ? 'border-[color:var(--primary)] bg-[rgba(var(--primary-rgb),0.08)] text-[color:var(--primary)]'
                                                    : 'border-amber-400/40 bg-amber-500/10 text-amber-200'
                                            }`}
                                        >
                                            {billingReady ? 'Ready' : 'Incomplete'}
                                        </span>
                                    )}
                                </div>
                                <InteractiveHoverButton
                                    onClick={() => setModal('BILLING')}
                                    type={'button'}
                                    text={'Change'}
                                    className={
                                        'mt-4 w-full sm:w-auto !h-9 !min-w-0 sm:!min-w-[8rem] !px-4 !text-[10px] sm:self-start'
                                    }
                                />
                            </div>

                            <div
                                className={
                                    'sc-card-inner flex min-w-0 flex-col p-4'
                                }
                            >
                                <div className={'min-w-0'}>
                                    <p className={'mb-1 text-[10px] uppercase tracking-widest text-[rgba(116,34,32,0.58)]'}>
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
                                    className={
                                        'mt-4 w-full sm:w-auto !h-9 !min-w-0 sm:!min-w-[8rem] !px-4 !text-[10px] sm:self-start'
                                    }
                                />
                            </div>
                        </div>
                    </section>

                    <section className={`${cardClass} overflow-hidden`}>
                        <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                            <h2 className={'text-lg font-bold tracking-tight text-[#742220]'}>Recent Activity</h2>
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
                                            <p className={'py-8 text-center text-xs text-[rgba(116,34,32,0.58)]'}>
                                                No activity found for this account.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </section>
                </div>

                <section
                    className={
                        'sc-card min-w-0 overflow-hidden'
                    }
                >
                    <div className={'grid grid-cols-2 border-b-2 border-[#2D4A3E] bg-[#F5EFD5]'}>
                        <button
                            onClick={() => setActiveTab('API')}
                            className={`px-4 py-3 text-[11px] font-bold uppercase tracking-widest transition ${
                                activeTab === 'API'
                                    ? 'bg-[#2D4A3E] text-[#FEF9E1]'
                                    : 'text-[rgba(116,34,32,0.62)] hover:bg-[#EDE6D0] hover:text-[#742220]'
                            }`}
                            type={'button'}
                        >
                            API Keys
                        </button>
                        <button
                            onClick={() => setActiveTab('SSH')}
                            className={`px-4 py-3 text-[11px] font-bold uppercase tracking-widest transition ${
                                activeTab === 'SSH'
                                    ? 'bg-[#2D4A3E] text-[#FEF9E1]'
                                    : 'text-[rgba(116,34,32,0.62)] hover:bg-[#EDE6D0] hover:text-[#742220]'
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
