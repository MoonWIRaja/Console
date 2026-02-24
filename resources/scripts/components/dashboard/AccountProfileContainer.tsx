import React, { useEffect, useState } from 'react';
import { useStoreState } from 'easy-peasy';
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

type Tab = 'API' | 'SSH';
type ModalContent = 'EMAIL' | 'PASSWORD' | '2FA' | null;

const cardClass =
    'rounded-xl border border-[#1f2a14] bg-[#000000] p-6 shadow-[0_0_0_1px_rgba(163,255,18,0.06),0_20px_35px_rgba(0,0,0,0.45)]';

export default () => {
    const user = useStoreState((state: ApplicationStore) => state.user.data!);
    const [activeTab, setActiveTab] = useState<Tab>('API');
    const [modal, setModal] = useState<ModalContent>(null);
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

    return (
        <div className={'account-theme h-screen overflow-y-auto bg-[#000000] px-6 py-8 font-mono text-white md:px-10'}>
            <style>{`
                .account-theme {
                    --neon-green: #a3ff12;
                }

                .account-theme .activity-feed-shell .bg-gray-700 {
                    background-color: transparent !important;
                }

                .account-theme .activity-feed-shell .bg-gray-600 {
                    background-color: #0f172a !important;
                }

                .account-theme .activity-feed-shell .grid {
                    min-height: 104px;
                    border-color: rgba(163, 255, 18, 0.18) !important;
                }

                .account-theme .activity-feed-shell .group:hover {
                    background-color: rgba(163, 255, 18, 0.05) !important;
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
                    background-color: #000000 !important;
                    border: 1px solid #1f2a14 !important;
                    border-radius: 0.75rem !important;
                    padding: 1.5rem !important;
                }

                .account-theme .account-tabs-shell .text-black {
                    color: #f8f6ef !important;
                }

                .account-theme .account-tabs-shell .text-neutral-500 {
                    color: #9ca3af !important;
                }

                .account-theme .account-tabs-shell .text-neutral-700 {
                    color: #cbd5e1 !important;
                }

                .account-theme .account-tabs-shell .border-black,
                .account-theme .account-tabs-shell .border-neutral-200 {
                    border-color: rgba(163, 255, 18, 0.25) !important;
                }

                .account-theme .account-tabs-shell h2 {
                    color: #f8f6ef !important;
                }

                .account-theme .account-tabs-shell input,
                .account-theme .account-tabs-shell textarea,
                .account-theme .account-tabs-shell select {
                    background-color: #000000 !important;
                    border-color: rgba(163, 255, 18, 0.28) !important;
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
                    box-shadow: 0 0 0 1px rgba(163, 255, 18, 0.35), 0 0 0 4px rgba(163, 255, 18, 0.1) !important;
                }

                .account-theme .account-tabs-shell label {
                    color: #d1d5db !important;
                }

                .account-theme .account-tabs-shell code {
                    background-color: #0a1104 !important;
                    color: #d9ff93 !important;
                    border: 1px solid rgba(163, 255, 18, 0.32) !important;
                    border-radius: 6px !important;
                }

                .account-theme .account-tabs-shell button[type='submit'],
                .account-theme .account-tabs-shell .Button__ButtonStyle-sc-1qu1gou-0 {
                    border-color: var(--neon-green) !important;
                    background-color: var(--neon-green) !important;
                    color: #000000 !important;
                    border-radius: 8px !important;
                    box-shadow: 0 0 14px rgba(163, 255, 18, 0.25) !important;
                }

                .account-theme .account-tabs-shell button[type='submit']:hover,
                .account-theme .account-tabs-shell .Button__ButtonStyle-sc-1qu1gou-0:hover {
                    filter: brightness(1.06) !important;
                    box-shadow: 0 0 18px rgba(163, 255, 18, 0.35) !important;
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
                    'mb-6 w-full max-w-[620px] rounded-xl border border-[#1f2a14] bg-[#000000] p-4 shadow-[0_0_0_1px_rgba(163,255,18,0.05)]'
                }
            >
                <div className={'flex flex-wrap items-center gap-4'}>
                    <div className={'h-16 w-16 overflow-hidden rounded-lg border border-[#a3ff12]/40 bg-black'}>
                        <Avatar.User size={64} variant={'beam'} />
                    </div>
                    <div className={'min-w-0'}>
                        <div className={'flex flex-wrap items-center gap-2'}>
                            <h1 className={'truncate text-2xl font-black tracking-tight text-[#f8f6ef]'}>
                                {user.username}
                            </h1>
                            {user.rootAdmin && (
                                <span
                                    className={
                                        'rounded-lg border border-[#a3ff12]/45 bg-[#a3ff12]/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#d9ff93]'
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
                                'mb-4 flex flex-wrap items-center justify-between gap-4 border-b border-[#1f2a14] pb-4'
                            }
                        >
                            <div className={'min-w-[220px]'}>
                                <p className={'mb-1 text-[10px] uppercase tracking-widest text-gray-500'}>Email</p>
                                <p className={'text-sm text-gray-100'}>{user.email}</p>
                            </div>
                            <button
                                onClick={() => setModal('EMAIL')}
                                className={
                                    'rounded-lg border border-[#2f3f17] bg-black px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-300 transition hover:border-[#a3ff12] hover:text-[#a3ff12]'
                                }
                                type={'button'}
                            >
                                Edit
                            </button>
                        </div>

                        <div
                            className={
                                'mb-4 flex flex-wrap items-center justify-between gap-4 border-b border-[#1f2a14] pb-4'
                            }
                        >
                            <div>
                                <p className={'mb-1 text-[10px] uppercase tracking-widest text-gray-500'}>Password</p>
                                <p className={'text-sm text-gray-100'}>********</p>
                            </div>
                            <button
                                onClick={() => setModal('PASSWORD')}
                                className={
                                    'rounded-lg border border-[#2f3f17] bg-black px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-300 transition hover:border-[#a3ff12] hover:text-[#a3ff12]'
                                }
                                type={'button'}
                            >
                                Change
                            </button>
                        </div>

                        <div className={'flex flex-wrap items-center justify-between gap-4'}>
                            <div>
                                <p className={'mb-1 text-[10px] uppercase tracking-widest text-gray-500'}>
                                    Two-Step Verification
                                </p>
                                <p className={`text-sm font-bold ${user.useTotp ? 'text-[#a3ff12]' : 'text-red-400'}`}>
                                    {user.useTotp ? 'Currently enabled' : 'Currently disabled'}
                                </p>
                            </div>
                            <button
                                onClick={() => setModal('2FA')}
                                className={`rounded-lg px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest transition ${
                                    user.useTotp
                                        ? 'border border-red-600 bg-red-600 text-white hover:bg-red-500'
                                        : 'border border-[#a3ff12] bg-[#a3ff12] text-black shadow-[0_0_14px_rgba(163,255,18,0.35)] hover:brightness-110'
                                }`}
                                type={'button'}
                            >
                                {user.useTotp ? 'Disable' : 'Enable'}
                            </button>
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

                <section className={'rounded-xl border border-[#1f2a14] bg-[#050505]'}>
                    <div className={'grid grid-cols-2 border-b border-[#1f2a14]'}>
                        <button
                            onClick={() => setActiveTab('API')}
                            className={`px-4 py-3 text-[11px] font-bold uppercase tracking-widest transition ${
                                activeTab === 'API'
                                    ? 'bg-[#a3ff12] text-black shadow-[0_0_14px_rgba(163,255,18,0.35)]'
                                    : 'text-gray-400 hover:text-white'
                            }`}
                            type={'button'}
                        >
                            API Keys
                        </button>
                        <button
                            onClick={() => setActiveTab('SSH')}
                            className={`px-4 py-3 text-[11px] font-bold uppercase tracking-widest transition ${
                                activeTab === 'SSH'
                                    ? 'bg-[#a3ff12] text-black shadow-[0_0_14px_rgba(163,255,18,0.35)]'
                                    : 'text-gray-400 hover:text-white'
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
