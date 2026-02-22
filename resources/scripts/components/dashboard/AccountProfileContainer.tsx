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

    const close = () => setModal(null);

    useEffect(() => {
        clearAndAddHttpError(activityError);
    }, [activityError]);

    return (
        <div
            style={{
                width: '100%',
                boxSizing: 'border-box',
                maxWidth: '100%',
                margin: '0',
                padding: '0',
                fontFamily: "'Space Mono', monospace",
                backgroundColor: '#f3f4f6',
                color: '#000000',
                height: '100vh',
                overflow: 'hidden',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
            `}</style>

            <Dialog open={modal === 'EMAIL'} onClose={close} title='Update Email Address'>
                <UpdateEmailAddressForm />
            </Dialog>
            <Dialog open={modal === 'PASSWORD'} onClose={close} title='Update Password'>
                <UpdatePasswordForm />
            </Dialog>
            <Dialog open={modal === '2FA'} onClose={close} title='Two-Step Verification'>
                <ConfigureTwoFactorForm />
            </Dialog>

            <div style={{ padding: '16px 16px 0' }}>
                <FlashMessageRender byKey={'account'} />
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '16px',
                        marginBottom: '16px',
                        flexWrap: 'wrap',
                    }}
                >
                    <div
                        style={{ width: '64px', height: '64px', flexShrink: 0, borderRadius: '0', overflow: 'hidden' }}
                    >
                        <Avatar.User />
                    </div>
                    <div>
                        <div
                            style={{
                                fontSize: 'clamp(18px, 2vw, 22px)',
                                fontWeight: 'bold',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                                letterSpacing: '-0.02em',
                                flexWrap: 'wrap',
                            }}
                        >
                            {user.username}
                            {user.rootAdmin && (
                                <span
                                    style={{
                                        fontSize: '10px',
                                        backgroundColor: '#000000',
                                        color: '#ffffff',
                                        padding: '2px 6px',
                                        letterSpacing: '0.05em',
                                        textTransform: 'uppercase',
                                    }}
                                >
                                    Administrator
                                </span>
                            )}
                        </div>
                        <div style={{ fontSize: '12px', color: '#6b7280', marginTop: '2px', wordBreak: 'break-word' }}>
                            {user.email}
                        </div>
                    </div>
                </div>
            </div>

            <div
                style={{
                    flex: 1,
                    minHeight: 0,
                    overflowY: 'auto',
                    padding: '8px 16px 16px',
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
                    gap: '24px',
                    alignItems: 'start',
                }}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                    <div style={{ border: '1px solid #000000', padding: '24px', backgroundColor: '#ffffff' }}>
                        <h2
                            style={{
                                fontSize: '18px',
                                fontWeight: 'bold',
                                marginBottom: '20px',
                                letterSpacing: '-0.02em',
                            }}
                        >
                            Account Information
                        </h2>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                borderBottom: '1px solid #000000',
                                paddingBottom: '16px',
                                marginBottom: '16px',
                                gap: '16px',
                                flexWrap: 'wrap',
                            }}
                        >
                            <div style={{ minWidth: '220px' }}>
                                <div
                                    style={{
                                        fontSize: '10px',
                                        color: '#6b7280',
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.05em',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Email
                                </div>
                                <div style={{ fontSize: '13px', wordBreak: 'break-word' }}>{user.email}</div>
                            </div>
                            <button
                                onClick={() => setModal('EMAIL')}
                                style={{
                                    padding: '6px 12px',
                                    fontSize: '10px',
                                    fontWeight: 'bold',
                                    backgroundColor: 'transparent',
                                    border: '1px solid #000000',
                                    color: '#000000',
                                    cursor: 'pointer',
                                    fontFamily: "'Space Mono', monospace",
                                    textTransform: 'uppercase',
                                }}
                            >
                                Edit
                            </button>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                borderBottom: '1px solid #000000',
                                paddingBottom: '16px',
                                marginBottom: '16px',
                                gap: '16px',
                                flexWrap: 'wrap',
                            }}
                        >
                            <div>
                                <div
                                    style={{
                                        fontSize: '10px',
                                        color: '#6b7280',
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.05em',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Password
                                </div>
                                <div style={{ fontSize: '13px' }}>********</div>
                            </div>
                            <button
                                onClick={() => setModal('PASSWORD')}
                                style={{
                                    padding: '6px 12px',
                                    fontSize: '10px',
                                    fontWeight: 'bold',
                                    backgroundColor: 'transparent',
                                    border: '1px solid #000000',
                                    color: '#000000',
                                    cursor: 'pointer',
                                    fontFamily: "'Space Mono', monospace",
                                    textTransform: 'uppercase',
                                }}
                            >
                                Change
                            </button>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                gap: '16px',
                                flexWrap: 'wrap',
                            }}
                        >
                            <div>
                                <div
                                    style={{
                                        fontSize: '10px',
                                        color: '#6b7280',
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.05em',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Two-Step Verification
                                </div>
                                <div style={{ fontSize: '13px', color: user.useTotp ? '#22c55e' : '#ef4444' }}>
                                    {user.useTotp ? 'Currently enabled' : 'Currently disabled'}
                                </div>
                            </div>
                            <button
                                onClick={() => setModal('2FA')}
                                style={{
                                    padding: '6px 12px',
                                    fontSize: '10px',
                                    fontWeight: 'bold',
                                    backgroundColor: user.useTotp ? '#ef4444' : '#000000',
                                    border: '1px solid',
                                    borderColor: user.useTotp ? '#ef4444' : '#000000',
                                    color: '#ffffff',
                                    cursor: 'pointer',
                                    fontFamily: "'Space Mono', monospace",
                                    textTransform: 'uppercase',
                                }}
                            >
                                {user.useTotp ? 'Disable' : 'Enable'}
                            </button>
                        </div>
                    </div>

                    <div className='login-activity' style={{ border: '1px solid #000000', padding: '24px', backgroundColor: '#ffffff' }}>
                        <style>{`
                            .login-activity .bg-gray-700 { background-color: #ffffff !important; }
                            .login-activity .grid { border-color: #000000 !important; min-height: 104px; }
                            .login-activity .group:hover { background-color: #f9fafb !important; }
                            .login-activity .text-gray-50 { color: #000000 !important; font-weight: 700; }
                            .login-activity .text-gray-400 { color: #6b7280 !important; }
                            .login-activity .elements-activity-style-module__description { color: #4b5563 !important; }
                            .login-activity .elements-activity-style-module__icons { color: #000000 !important; }
                            .login-activity a { color: #000000 !important; }
                            .login-activity a:hover { color: #4b5563 !important; }
                            .login-activity .bg-gray-600 { background-color: #e5e7eb !important; }
                            .login-activity .self-center button { color: #000000 !important; }
                            .login-activity .self-center button:hover { color: #6b7280 !important; }
                            
                            /* Pagination Top Right Fixes */
                            .login-activity .activity-pagination-top .my-2 { margin: 0 !important; align-items: center; justify-content: flex-end; gap: 16px; }
                            .login-activity .activity-pagination-top p { margin: 0 !important; color: #6b7280 !important; font-size: 11px !important; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Space Mono', monospace !important; }
                            .login-activity .activity-pagination-top span { color: #000000 !important; font-weight: bold !important; font-size: 13px !important; }
                            .login-activity .activity-pagination-top button { color: #000000 !important; padding: 4px !important; }
                            .login-activity .activity-pagination-top svg { width: 16px; height: 16px; }
                        `}</style>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '16px' }}>
                            <h2
                                style={{
                                    fontSize: '18px',
                                    fontWeight: 'bold',
                                    margin: 0,
                                    letterSpacing: '-0.02em',
                                }}
                            >
                                Recent Activity
                            </h2>
                            {activityData && (
                                <div className="activity-pagination-top">
                                    <PaginationFooter
                                        pagination={activityData.pagination}
                                        onPageSelect={(page) => setActivityFilters((value) => ({ ...value, page }))}
                                    />
                                </div>
                            )}
                        </div>
                        <div style={{ fontFamily: 'inherit' }}>
                            {!activityData && activityLoading ? (
                                <Spinner centered />
                            ) : (
                                <div style={{ height: '520px', overflowY: 'auto', paddingRight: '6px' }}>
                                    <div className={'bg-gray-700'}>
                                        {activityData?.items.map((activity) => (
                                            <ActivityLogEntry key={activity.id} activity={activity}>
                                                {typeof activity.properties.useragent === 'string' && (
                                                    <Tooltip content={activity.properties.useragent} placement={'top'}>
                                                        <span>
                                                            <DesktopComputerIcon />
                                                        </span>
                                                    </Tooltip>
                                                )}
                                            </ActivityLogEntry>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div
                    style={{
                        border: '1px solid #000000',
                        backgroundColor: '#ffffff',
                        display: 'flex',
                        flexDirection: 'column',
                        minWidth: 0,
                    }}
                >
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', borderBottom: '1px solid #000000' }}>
                        <button
                            onClick={() => setActiveTab('API')}
                            style={{
                                padding: '16px',
                                fontSize: '12px',
                                fontWeight: 'bold',
                                backgroundColor: activeTab === 'API' ? '#000000' : 'transparent',
                                color: activeTab === 'API' ? '#ffffff' : '#6b7280',
                                border: 'none',
                                cursor: 'pointer',
                                fontFamily: "'Space Mono', monospace",
                                letterSpacing: '0.05em',
                                transition: 'all 0.15s',
                                textTransform: 'uppercase',
                            }}
                        >
                            API Keys
                        </button>
                        <button
                            onClick={() => setActiveTab('SSH')}
                            style={{
                                padding: '16px',
                                fontSize: '12px',
                                fontWeight: 'bold',
                                backgroundColor: activeTab === 'SSH' ? '#000000' : 'transparent',
                                color: activeTab === 'SSH' ? '#ffffff' : '#6b7280',
                                border: 'none',
                                cursor: 'pointer',
                                fontFamily: "'Space Mono', monospace",
                                letterSpacing: '0.05em',
                                transition: 'all 0.15s',
                                textTransform: 'uppercase',
                            }}
                        >
                            SSH Keys
                        </button>
                    </div>

                    <div className='embedded-tabs-content' style={{ minWidth: 0 }}>
                        <style>{`
                            .embedded-tabs-content .PageContentBlock > h1 { display: none !important; }
                            .embedded-tabs-content .ContentContainer-sc-x3r2dw-0:last-child { display: none !important; }
                            .embedded-tabs-content .ContentContainer-sc-x3r2dw-0 { margin: 0 !important; width: 100% !important; max-width: 100% !important; }
                            
                            /* Tab View Responsiveness: 50/50 Layout */
                            .embedded-tabs-content .md\\:flex { 
                                display: flex !important; 
                                flex-direction: row !important; 
                                gap: 24px !important; 
                                align-items: flex-start !important; 
                            }
                            .embedded-tabs-content .md\\:w-1\\/2 { 
                                width: calc(50% - 12px) !important; 
                                border: 1px solid #000000 !important; 
                                background-color: #ffffff !important; 
                                padding: 24px !important; 
                                box-sizing: border-box !important;
                            }
                            .embedded-tabs-content .md\\:ml-8 { margin-left: 0 !important; }
                            
                            /* Inner ContentBox completely transparent to remove "box dalam" */
                            .embedded-tabs-content .ContentBox { background: transparent !important; box-shadow: none !important; border: none !important; padding: 0 !important; margin: 0 !important; }
                            
                            /* Headers */
                            .embedded-tabs-content h2,
                            .embedded-tabs-content .ContentBox > h2 { 
                                font-size: 18px !important; 
                                font-weight: bold !important; 
                                border-bottom: none !important; 
                                padding: 0 !important; 
                                margin: 0 0 20px 0 !important; 
                                background: transparent !important; 
                                color: #000000 !important; 
                                letter-spacing: -0.02em !important; 
                                text-transform: none !important;
                                font-family: 'Space Mono', monospace !important; 
                            }
                            .embedded-tabs-content .ContentBox > div { padding: 0 !important; }
                            
                            /* Form overrides to match stark theme */
                            .embedded-tabs-content input, 
                            .embedded-tabs-content textarea,
                            .embedded-tabs-content select { 
                                background-color: #ffffff !important; 
                                border: 1px solid #000000 !important; 
                                color: #000000 !important; 
                                border-radius: 0 !important; 
                                font-family: 'Space Mono', monospace !important; 
                                padding: 12px !important;
                                font-size: 13px !important;
                                width: 100% !important;
                                box-sizing: border-box !important;
                            }
                            .embedded-tabs-content input:focus, 
                            .embedded-tabs-content textarea:focus { 
                                outline: none !important;
                            }
                            
                            .embedded-tabs-content label { 
                                color: #6b7280 !important; 
                                text-transform: uppercase !important; 
                                font-size: 10px !important; 
                                letter-spacing: 0.05em !important; 
                                font-family: 'Space Mono', monospace !important; 
                                display: block !important;
                                margin-bottom: 4px !important;
                            }
                            
                            .embedded-tabs-content p.text-sm, 
                            .embedded-tabs-content .InputError___StyledP2-sc-12bxu09-0,
                            .embedded-tabs-content p { 
                                color: #6b7280 !important; 
                                font-family: 'Space Mono', monospace !important; 
                                font-size: 12px !important;
                                margin-top: 4px !important;
                                line-height: 1.5 !important;
                            }
                            
                            /* Buttons */
                            .embedded-tabs-content button[type="submit"], 
                            .embedded-tabs-content button.Button__ButtonStyle-sc-1qu1gou-0 { 
                                background-color: transparent !important; 
                                color: #000000 !important; 
                                border-radius: 0 !important; 
                                font-family: 'Space Mono', monospace !important; 
                                font-size: 10px !important; 
                                font-weight: bold !important; 
                                text-transform: uppercase !important; 
                                padding: 6px 12px !important; 
                                border: 1px solid #000000 !important; 
                                cursor: pointer !important;
                                transition: all 0.2s ease !important;
                                box-shadow: none !important;
                                display: inline-block !important;
                                margin-top: 16px !important;
                            }
                            .embedded-tabs-content button[type="submit"]:hover, 
                            .embedded-tabs-content button.Button__ButtonStyle-sc-1qu1gou-0:hover { 
                                background-color: #000000 !important; 
                                color: #ffffff !important; 
                            }
                            
                            /* List Items / API Key Cards */
                            .embedded-tabs-content .GreyRowBox-sc-1bczx2-0, 
                            .embedded-tabs-content .grid { 
                                background-color: #ffffff !important; 
                                border: 1px solid #000000 !important; 
                                color: #000000 !important; 
                                border-radius: 0 !important; 
                                box-shadow: none !important; 
                                margin-bottom: 12px !important; 
                                padding: 16px !important;
                                display: flex !important;
                                align-items: center !important;
                                justify-content: space-between !important;
                            }
                            
                            .embedded-tabs-content code { 
                                background-color: #f3f4f6 !important; 
                                color: #000000 !important; 
                                padding: 4px 8px !important; 
                                border-radius: 0 !important; 
                                font-family: 'Space Mono', monospace !important; 
                                font-size: 12px !important;
                                border: 1px solid #e5e7eb !important;
                            }
                            
                            /* Delete Buttons in List */
                            .embedded-tabs-content button .text-neutral-400,
                            .embedded-tabs-content button svg { 
                                color: #000000 !important; 
                            }
                            .embedded-tabs-content button:hover svg { 
                                color: #ef4444 !important; 
                            }
                        `}</style>
                        {activeTab === 'API' && <AccountApiContainer />}
                        {activeTab === 'SSH' && <AccountSSHContainer />}
                    </div>
                </div>
            </div>
        </div>
    );
};
