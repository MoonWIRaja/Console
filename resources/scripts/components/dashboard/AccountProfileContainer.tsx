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
import { ActivityLogFilters, useActivityLogs } from '@/api/account/activity';
import { useFlashKey } from '@/plugins/useFlash';
import Spinner from '@/components/elements/Spinner';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { DesktopComputerIcon } from '@heroicons/react/solid';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import updateAccountAvatar from '@/api/account/updateAccountAvatar';
import removeAccountAvatar from '@/api/account/removeAccountAvatar';

type Tab = 'API' | 'SSH';
type ModalContent = 'EMAIL' | 'PASSWORD' | '2FA' | null;

const cardClass =
    'rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-6 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.06),0_20px_35px_rgba(12, 12, 12, 0.45)]';

export default () => {
    const user = useStoreState((state: ApplicationStore) => state.user.data!);
    const updateUserData = useStoreActions((actions: Actions<ApplicationStore>) => actions.user.updateUserData);
    const [activeTab, setActiveTab] = useState<Tab>('API');
    const [modal, setModal] = useState<ModalContent>(null);
    const [avatarMenuOpen, setAvatarMenuOpen] = useState(false);
    const [avatarUploading, setAvatarUploading] = useState(false);
    const avatarMenuRef = useRef<HTMLDivElement>(null);
    const avatarInputRef = useRef<HTMLInputElement>(null);
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

    useEffect(() => {
        clearAndAddHttpError(activityError);
    }, [activityError]);

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
            clearAndAddHttpError(error);
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
            clearAndAddHttpError(error);
        } finally {
            setAvatarUploading(false);
        }
    };

    return (
        <div className={'account-theme h-screen overflow-y-auto bg-[color:var(--card)] px-6 py-8 font-mono text-white md:px-10'}>
            <style>{`
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

            <section
                className={
                    'mb-6 w-full max-w-[620px] rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.05)]'
                }
            >
                <div className={'flex flex-wrap items-center gap-4'}>
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
                            <div className={'absolute left-0 top-[calc(100%+0.5rem)] z-30 w-44 rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-2 shadow-[0_18px_32px_rgba(0,0,0,0.4)]'}>
                                <button
                                    type={'button'}
                                    className={'w-full rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-wide text-[color:var(--foreground)] transition-colors hover:bg-[color:var(--accent)]'}
                                    onClick={() => avatarInputRef.current?.click()}
                                    disabled={avatarUploading}
                                >
                                    {avatarUploading ? 'Uploading...' : 'Change Image'}
                                </button>
                                <button
                                    type={'button'}
                                    className={'mt-1 w-full rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-wide text-red-300 transition-colors hover:bg-red-500/10 disabled:opacity-50'}
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
                                <p className={`text-sm font-bold ${user.useTotp ? 'text-[color:var(--primary)]' : 'text-red-400'}`}>
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
                    </div>
                </section>
            </div>
        </div>
    );
};
