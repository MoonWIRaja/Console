import React, { memo } from 'react';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import StatGraphs from '@/components/server/console/StatGraphs';
import PowerButtons from '@/components/server/console/PowerButtons';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import { Alert } from '@/components/elements/alert';
import { ApplicationStore } from '@/state';
import { useStoreState } from 'easy-peasy';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);
    const status = ServerContext.useStoreState((state) => state.status.value);

    // User data for the client card
    const userName = useStoreState((state: ApplicationStore) => state.user.data!.username);
    const userEmail = useStoreState((state: ApplicationStore) => state.user.data!.email);

    return (
        <div className="flex-1 flex overflow-hidden relative" style={{ fontFamily: "'Inter', sans-serif" }}>
            <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-blue-400/10 blur-[100px] pointer-events-none"></div>
            <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] rounded-full bg-purple-400/10 blur-[100px] pointer-events-none"></div>

            <div className="flex-1 flex flex-col md:flex-row h-full w-full max-w-full overflow-hidden z-10">
                <div className="flex-1 flex flex-col p-6 min-w-0 overflow-y-auto gap-6">
                    {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                        <Alert type={'warning'} className={'mb-0'}>
                            {isNodeUnderMaintenance
                                ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                                : isInstalling
                                    ? 'This server is currently running its installation process and most actions are unavailable.'
                                    : 'This server is currently being transferred to another node and all actions are unavailable.'}
                        </Alert>
                    )}

                    {/* Console Block */}
                    <div className="bg-card-light dark:bg-card-dark rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col flex-1 min-h-[500px]">
                        <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50 rounded-t-xl">
                            <h2 className="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center">
                                <span className={`w-2 h-2 rounded-full mr-2 ${status === 'running' ? 'bg-green-500 animate-pulse' : status === 'offline' ? 'bg-red-500' : 'bg-yellow-500 animate-pulse'}`}></span>
                                Live Console: {name}
                            </h2>
                        </div>
                        <div className="flex-1 p-4 overflow-hidden bg-black rounded-b-xl" style={{ position: 'relative' }}>
                            <Spinner.Suspense>
                                <Console />
                            </Spinner.Suspense>
                        </div>
                    </div>

                    {/* Server Statistics Block */}
                    <div className="bg-card-light dark:bg-card-dark rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-6">Server Statistics</h3>
                        <div className="grid grid-cols-1 overflow-hidden" style={{ minWidth: 0 }}>
                            <Spinner.Suspense>
                                <StatGraphs />
                            </Spinner.Suspense>
                        </div>
                    </div>
                </div>

                {/* Right Sidebar Block */}
                <div className="w-full md:w-80 p-6 pl-0 flex flex-col gap-6 overflow-y-auto">
                    {/* User Profile Card */}
                    <div className="bg-card-light dark:bg-card-dark rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex items-center">
                        <div className="h-10 w-10 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-lg mr-3 shadow-md shadow-blue-500/30">
                            {userName.charAt(0).toUpperCase()}
                        </div>
                        <div className="overflow-hidden">
                            <h3 className="font-bold text-gray-900 dark:text-white truncate">{userName}</h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{userEmail}</p>
                        </div>
                    </div>

                    {/* Server Control Card */}
                    <div className="bg-card-light dark:bg-card-dark rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                        <h3 className="font-bold text-lg text-gray-900 dark:text-white mb-4">Server Control</h3>
                        <ServerDetailsBlock className={''} />
                        <div className="mt-6">
                            <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                                <PowerButtons className={'flex flex-col space-y-3'} />
                            </Can>
                        </div>
                    </div>

                    {/* Static Players Panel */}
                    <div className="bg-card-light dark:bg-card-dark rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex-1 flex flex-col min-h-[300px]">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="font-bold text-lg text-gray-900 dark:text-white">Players</h3>
                            <span className="text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-1 rounded-md">5 Online</span>
                        </div>
                        <div className="relative mb-4">
                            <input className="w-full pl-3 pr-8 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-xs outline-none text-gray-700 dark:text-gray-300" placeholder="Filter by Name or ID..." type="text" />
                            <span className="material-icons-round absolute right-2 top-2 text-gray-400 text-sm pointer-events-none">search</span>
                        </div>
                        <div className="space-y-3 overflow-y-auto pr-1">
                            <div className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors border border-transparent hover:border-gray-100 dark:hover:border-gray-700">
                                <div className="flex items-center gap-3">
                                    <div className="h-9 w-9 rounded bg-blue-500 flex items-center justify-center text-white font-bold text-sm shadow-sm">E</div>
                                    <div>
                                        <p className="text-sm font-bold text-gray-800 dark:text-gray-200">Ekarl</p>
                                        <p className="text-[10px] text-gray-500">Ping: 32ms</p>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <button className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded hover:bg-gray-200 dark:hover:bg-gray-700">
                                        <span className="material-icons-round text-sm">settings</span>
                                    </button>
                                </div>
                            </div>
                            <div className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors border border-transparent hover:border-gray-100 dark:hover:border-gray-700">
                                <div className="flex items-center gap-3">
                                    <div className="h-9 w-9 rounded bg-indigo-500 flex items-center justify-center text-white font-bold text-sm shadow-sm">C</div>
                                    <div>
                                        <p className="text-sm font-bold text-gray-800 dark:text-gray-200">Coral</p>
                                        <p className="text-[10px] text-gray-500">Ping: 45ms</p>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <button className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded hover:bg-gray-200 dark:hover:bg-gray-700">
                                        <span className="material-icons-round text-sm">settings</span>
                                    </button>
                                </div>
                            </div>
                            <div className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors border border-transparent hover:border-gray-100 dark:hover:border-gray-700">
                                <div className="flex items-center gap-3">
                                    <div className="h-9 w-9 rounded bg-cyan-500 flex items-center justify-center text-white font-bold text-sm shadow-sm">I</div>
                                    <div>
                                        <p className="text-sm font-bold text-gray-800 dark:text-gray-200">Izzxt</p>
                                        <p className="text-[10px] text-gray-500">Ping: 28ms</p>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <button className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded hover:bg-gray-200 dark:hover:bg-gray-700">
                                        <span className="material-icons-round text-sm">settings</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <Features enabled={eggFeatures} />
        </div>
    );
};

export default memo(ServerConsoleContainer, isEqual);
