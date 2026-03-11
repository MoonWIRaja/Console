import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useHistory, useParams } from 'react-router';
import { Form, Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import tw from 'twin.macro';
import getServerDatabases from '@/api/server/databases/getServerDatabases';
import deleteServerDatabase from '@/api/server/databases/deleteServerDatabase';
import {
    DatabaseWorkspaceHealth,
    DatabaseWorkspaceQueryResult,
    DatabaseWorkspaceRows,
    DatabaseWorkspaceTable,
    downloadDatabaseWorkspaceExport,
    executeDatabaseWorkspaceQuery,
    getDatabaseWorkspaceHealth,
    getDatabaseWorkspaceRows,
    getDatabaseWorkspaceTables,
    importDatabaseWorkspaceSql,
} from '@/api/server/databases/databaseWorkspace';
import { httpErrorToHuman } from '@/api/http';
import FlashMessageRender from '@/components/FlashMessageRender';
import Can from '@/components/elements/Can';
import CodemirrorEditor from '@/components/elements/CodemirrorEditor';
import Modal from '@/components/elements/Modal';
import Field from '@/components/elements/Field';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Button from '@/components/elements/Button';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { NotFound } from '@/components/elements/ScreenBlock';
import RotatePasswordButton from '@/components/server/databases/RotatePasswordButton';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';

type Tab = 'overview' | 'tables' | 'query' | 'import-export' | 'health' | 'settings';

const tabLabels: { id: Tab; label: string }[] = [
    { id: 'overview', label: 'Overview' },
    { id: 'tables', label: 'Tables' },
    { id: 'query', label: 'Query' },
    { id: 'import-export', label: 'Import / Export' },
    { id: 'health', label: 'Health' },
    { id: 'settings', label: 'Settings' },
];

const statCard = tw`rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4`;

const ConnectionField = ({ label, value, copyValue }: { label: string; value: string; copyValue?: string }) => (
    <div>
        <Label>{label}</Label>
        {copyValue ? (
            <CopyOnClick text={copyValue} showInNotification={false}>
                <Input type={'text'} readOnly value={value} />
            </CopyOnClick>
        ) : (
            <Input type={'text'} readOnly value={value} />
        )}
    </div>
);

const formatBytes = (bytes: number) => {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** index;

    return `${value >= 100 || index === 0 ? value.toFixed(0) : value.toFixed(2)} ${units[index]}`;
};

const formatDate = (value?: string | null) => {
    if (!value) {
        return 'Unavailable';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString();
};

const renderValue = (value: unknown) => {
    if (value === null || value === undefined || value === '') {
        return <span css={tw`text-neutral-500`}>NULL</span>;
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
};

const DataTable = ({
    columns,
    rows,
    emptyMessage,
}: {
    columns: string[];
    rows: Record<string, unknown>[];
    emptyMessage: string;
}) => {
    if (!columns.length) {
        return <p css={tw`text-sm text-neutral-400`}>{emptyMessage}</p>;
    }

    return (
        <div css={tw`overflow-x-auto rounded-xl border border-[color:var(--border)]`}>
            <table css={tw`min-w-full divide-y divide-[color:var(--border)] text-left text-sm`}>
                <thead css={tw`bg-[color:var(--background)]`}>
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column}
                                css={tw`whitespace-nowrap px-4 py-3 text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-400`}
                            >
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody css={tw`divide-y divide-[color:var(--border)] bg-[color:var(--card)]`}>
                    {rows.length > 0 ? (
                        rows.map((row, index) => (
                            <tr key={`${index}-${columns.join('-')}`}>
                                {columns.map((column) => (
                                    <td key={`${index}-${column}`} css={tw`max-w-[24rem] px-4 py-3 align-top text-[#f8f6ef]`}>
                                        <div css={tw`whitespace-pre-wrap break-words text-sm leading-6`}>
                                            {renderValue(row[column])}
                                        </div>
                                    </td>
                                ))}
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={columns.length} css={tw`px-4 py-6 text-center text-sm text-neutral-400`}>
                                {emptyMessage}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};

const DatabaseDashboardContainer = () => {
    const { databaseId } = useParams<{ databaseId: string }>();
    const history = useHistory();
    const [activeTab, setActiveTab] = useState<Tab>('overview');
    const [loading, setLoading] = useState(true);
    const [deleteVisible, setDeleteVisible] = useState(false);
    const [tables, setTables] = useState<DatabaseWorkspaceTable[]>([]);
    const [tablesLoaded, setTablesLoaded] = useState(false);
    const [tablesLoading, setTablesLoading] = useState(false);
    const [selectedTable, setSelectedTable] = useState('');
    const [tableRows, setTableRows] = useState<DatabaseWorkspaceRows | null>(null);
    const [tableRowsLoading, setTableRowsLoading] = useState(false);
    const [tablePage, setTablePage] = useState(1);
    const [health, setHealth] = useState<DatabaseWorkspaceHealth | null>(null);
    const [healthLoading, setHealthLoading] = useState(false);
    const [queryMode, setQueryMode] = useState('text/x-sql');
    const [querySeed, setQuerySeed] = useState('SHOW TABLE STATUS;');
    const [queryLoading, setQueryLoading] = useState(false);
    const [queryResult, setQueryResult] = useState<DatabaseWorkspaceQueryResult | null>(null);
    const [queryHistory, setQueryHistory] = useState<string[]>([]);
    const [importSql, setImportSql] = useState('');
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importFileKey, setImportFileKey] = useState(0);
    const [importLoading, setImportLoading] = useState(false);
    const [lastImportMessage, setLastImportMessage] = useState('');
    const [exporting, setExporting] = useState<'full' | 'schema' | null>(null);
    const deleteInProgress = useRef(false);
    const queryFetcher = useRef<() => Promise<string>>(async () => querySeed);

    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const permissions = ServerContext.useStoreState((state) => state.server.permissions);
    const databases = ServerContext.useStoreState((state) => state.databases.data);
    const setDatabases = ServerContext.useStoreActions((state) => state.databases.setDatabases);
    const appendDatabase = ServerContext.useStoreActions((state) => state.databases.appendDatabase);
    const removeDatabase = ServerContext.useStoreActions((state) => state.databases.removeDatabase);
    const { addFlash, addError, clearFlashes } = useFlash();

    const canUpdateDatabase = permissions.includes('*') || permissions.includes('database.update');
    const database = useMemo(() => databases.find((item) => item.id === databaseId), [databases, databaseId]);
    const jdbcConnectionString = useMemo(() => {
        if (!database) {
            return '';
        }

        return `jdbc:mysql://${database.username}${
            database.password ? `:${encodeURIComponent(database.password)}` : ''
        }@${database.connectionString}/${database.name}`;
    }, [database]);

    const showWorkspaceErrorMessage = (message: string) => {
        clearFlashes('database:workspace');
        addError({ key: 'database:workspace', message });
    };

    const showWorkspaceError = (error: unknown) => {
        const status = (error as { response?: { status?: number } })?.response?.status;
        if (typeof status !== 'number' || status >= 500) {
            console.error(error);
        }

        showWorkspaceErrorMessage(httpErrorToHuman(error));
    };

    const refreshTables = (preserveSelection = true) => {
        if (!database) {
            return Promise.resolve();
        }

        if (health?.reachable !== true) {
            setTables([]);
            setTablesLoaded(false);
            setSelectedTable('');
            setTableRows(null);

            return Promise.resolve();
        }

        setTablesLoading(true);

        return getDatabaseWorkspaceTables(uuid, database.id)
            .then((response) => {
                setTables(response);

                if (!response.length) {
                    setSelectedTable('');
                    setTableRows(null);
                    return;
                }

                if (!preserveSelection || !response.some((table) => table.name === selectedTable)) {
                    setSelectedTable(response[0].name);
                    setTablePage(1);
                }
            })
            .catch(showWorkspaceError)
            .finally(() => {
                setTablesLoaded(true);
                setTablesLoading(false);
            });
    };

    const refreshHealth = () => {
        if (!database) {
            return Promise.resolve();
        }

        setHealthLoading(true);

        return getDatabaseWorkspaceHealth(uuid, database.id)
            .then((response) => {
                setHealth(response);

                if (!response.reachable) {
                    setTables([]);
                    setTablesLoaded(false);
                    setSelectedTable('');
                    setTableRows(null);
                }
            })
            .catch(showWorkspaceError)
            .finally(() => setHealthLoading(false));
    };

    const refreshTablePreview = (tableName: string, page: number) => {
        if (!database || !tableName || health?.reachable !== true) {
            setTableRows(null);
            return Promise.resolve();
        }

        setTableRowsLoading(true);

        return getDatabaseWorkspaceRows(uuid, database.id, tableName, page)
            .then(setTableRows)
            .catch(showWorkspaceError)
            .finally(() => setTableRowsLoading(false));
    };

    useEffect(() => {
        clearFlashes('databases:dashboard');
        clearFlashes('database:workspace');
        setTables([]);
        setTablesLoaded(false);
        setSelectedTable('');
        setTableRows(null);
        setHealth(null);

        if (database) {
            setLoading(false);
            return;
        }

        setLoading(true);
        getServerDatabases(uuid)
            .then((response) => setDatabases(response))
            .catch((error) => {
                console.error(error);
                addError({ key: 'databases:dashboard', message: httpErrorToHuman(error) });
            })
            .then(() => setLoading(false));
    }, [database, uuid, setDatabases, addError, clearFlashes]);

    useEffect(() => {
        if (!database) {
            return;
        }

        if ((activeTab === 'tables' || activeTab === 'health' || activeTab === 'overview') && !health && !healthLoading) {
            refreshHealth();
            return;
        }

        if (
            (activeTab === 'tables' || activeTab === 'health' || activeTab === 'overview') &&
            health?.reachable === true &&
            !tablesLoaded &&
            !tablesLoading
        ) {
            refreshTables(false);
        }

        if (activeTab === 'tables' && health?.reachable === false) {
            setSelectedTable('');
            setTableRows(null);
        }
    }, [activeTab, database?.id, health, healthLoading, tablesLoaded, tablesLoading]);

    useEffect(() => {
        if (activeTab !== 'tables' || !selectedTable || health?.reachable === false) {
            return;
        }

        refreshTablePreview(selectedTable, tablePage);
    }, [activeTab, selectedTable, tablePage, database?.id, health?.reachable]);

    if (loading) {
        return (
            <ServerContentBlock title={'Database Dashboard'} className={'content-container-full px-4 xl:px-6'}>
                <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={9} className='min-h-[420px]' />
            </ServerContentBlock>
        );
    }

    if (!database) {
        return (
            <NotFound
                title={'Database Not Found'}
                message={'The requested database could not be found for this server.'}
                onBack={() => history.push(`/server/${serverId}/databases`)}
            />
        );
    }

    const schema = object().shape({
        confirm: string()
            .required('The database name must be provided.')
            .oneOf([database.name.split('_', 2)[1], database.name], 'The database name must be provided.'),
    });

    const submitDelete = (_values: { confirm: string }, { setSubmitting }: FormikHelpers<{ confirm: string }>) => {
        if (deleteInProgress.current) {
            return;
        }

        deleteInProgress.current = true;
        clearFlashes();

        deleteServerDatabase(uuid, database.id)
            .then(() => {
                setDeleteVisible(false);
                removeDatabase(database.id);
                history.push(`/server/${serverId}/databases`);
            })
            .catch((error) => {
                console.error(error);
                setSubmitting(false);
                addError({ key: 'database:delete', message: httpErrorToHuman(error) });
            })
            .finally(() => {
                deleteInProgress.current = false;
            });
    };

    const executeQuery = async () => {
        clearFlashes('database:workspace');
        setQueryLoading(true);

        try {
            const sql = await queryFetcher.current();
            setQuerySeed(sql);

            const response = await executeDatabaseWorkspaceQuery(uuid, database.id, sql);
            setQueryResult(response);
            setQueryHistory((current) => [sql, ...current.filter((entry) => entry !== sql)].slice(0, 6));

            addFlash({
                key: 'database:workspace',
                type: 'success',
                title: 'Query Executed',
                message: response.message,
            });

            if (response.mode === 'write') {
                refreshTables();
                refreshHealth();
                if (selectedTable) {
                    refreshTablePreview(selectedTable, tablePage);
                }
            }
        } catch (error) {
            showWorkspaceError(error);
        } finally {
            setQueryLoading(false);
        }
    };

    const runExport = async (schemaOnly: boolean) => {
        clearFlashes('database:workspace');
        setExporting(schemaOnly ? 'schema' : 'full');

        try {
            await downloadDatabaseWorkspaceExport(uuid, database.id, schemaOnly);
            addFlash({
                key: 'database:workspace',
                type: 'success',
                title: 'Export Ready',
                message: schemaOnly ? 'Schema export download started.' : 'Database export download started.',
            });
        } catch (error) {
            showWorkspaceError(error);
        } finally {
            setExporting(null);
        }
    };

    const runImport = async () => {
        if (!importFile && importSql.trim() === '') {
            showWorkspaceErrorMessage('Provide a SQL file or paste SQL content to import.');
            return;
        }

        clearFlashes('database:workspace');
        setImportLoading(true);

        try {
            const response = await importDatabaseWorkspaceSql(uuid, database.id, {
                sql: importFile ? undefined : importSql,
                file: importFile,
            });

            setLastImportMessage(response.output || response.message);
            setImportSql('');
            setImportFile(null);
            setImportFileKey((value) => value + 1);
            addFlash({
                key: 'database:workspace',
                type: 'success',
                title: 'Import Complete',
                message: response.message,
            });

            refreshTables(false);
            refreshHealth();
            if (selectedTable) {
                refreshTablePreview(selectedTable, 1);
            }
        } catch (error) {
            showWorkspaceError(error);
        } finally {
            setImportLoading(false);
        }
    };

    const overviewLargestTables = [...tables].sort((left, right) => right.sizeBytes - left.sizeBytes).slice(0, 5);
    const selectedTableMeta = tables.find((table) => table.name === selectedTable) || null;
    const queryColumns = queryResult?.columns || Object.keys(queryResult?.rows?.[0] || {});
    const workspaceUnavailable = health?.reachable === false;

    return (
        <>
            <Formik onSubmit={submitDelete} initialValues={{ confirm: '' }} validationSchema={schema} validateOnMount>
                {({ isSubmitting, isValid, resetForm }) => (
                    <Modal
                        visible={deleteVisible}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            setDeleteVisible(false);
                            resetForm();
                        }}
                    >
                        <FlashMessageRender byKey={'database:delete'} css={tw`mb-6`} />
                        <h2 css={tw`mb-6 text-2xl text-[#f8f6ef]`}>Confirm database deletion</h2>
                        <p css={tw`text-sm text-neutral-300`}>
                            Deleting this database is permanent. This will remove <strong>{database.name}</strong> and
                            all associated data.
                        </p>
                        <Form css={tw`m-0 mt-6`}>
                            <Field
                                type={'text'}
                                id={'confirm_database_name'}
                                name={'confirm'}
                                label={'Confirm Database Name'}
                                description={'Enter the database name to confirm deletion.'}
                            />
                            <div css={tw`mt-6 flex flex-col justify-end gap-3 sm:flex-row`}>
                                <Button
                                    type={'button'}
                                    isSecondary
                                    disabled={isSubmitting}
                                    onClick={() => setDeleteVisible(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type={'submit'} color={'red'} disabled={isSubmitting || !isValid}>
                                    Delete Database
                                </Button>
                            </div>
                        </Form>
                    </Modal>
                )}
            </Formik>

            <ServerContentBlock title={`Database | ${database.name}`} className={'content-container-full px-4 xl:px-6'}>
                <div css={tw`flex h-full min-h-0 flex-col overflow-hidden`}>
                    <div css={tw`flex-shrink-0`}>
                        <FlashMessageRender byKey={'databases:dashboard'} css={tw`mb-4`} />
                        <FlashMessageRender byKey={'database:workspace'} css={tw`mb-4`} />
                        <FlashMessageRender byKey={'database-connection-modal'} css={tw`mb-4`} />

                        {workspaceUnavailable && health?.errorMessage && (
                            <div
                                css={tw`mb-4 rounded-xl border border-[#7f1d1d] bg-[#2a0707] px-4 py-3 text-sm leading-6 text-[#fecaca]`}
                            >
                                {health.errorMessage}
                            </div>
                        )}

                        <div css={tw`mb-6 rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-5`}>
                            <div css={tw`flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between`}>
                                <div css={tw`min-w-0`}>
                                    <p css={tw`text-[11px] font-bold uppercase tracking-[0.22em] text-[color:var(--primary)]`}>
                                        SQL Dashboard
                                    </p>
                                    <h1 css={tw`mt-2 truncate text-3xl font-black tracking-tight text-[#f8f6ef]`}>
                                        {database.name}
                                    </h1>
                                    <p css={tw`mt-2 text-sm text-neutral-400`}>
                                        Browse tables, run SQL, export or import dumps, and inspect database health from one
                                        workspace.
                                    </p>
                                </div>

                                <div css={tw`flex flex-col gap-3 sm:flex-row`}>
                                    <Button isSecondary onClick={() => history.push(`/server/${serverId}/databases`)}>
                                        Back to Databases
                                    </Button>
                                    <Button isSecondary color={'primary'} onClick={() => setActiveTab('query')}>
                                        Open Query Console
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div
                            css={tw`mb-6 flex flex-wrap items-center gap-2 rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-2`}
                        >
                            {tabLabels.map((tab) => (
                                <button
                                    key={tab.id}
                                    type={'button'}
                                    onClick={() => setActiveTab(tab.id)}
                                    css={[
                                        tw`rounded-lg px-4 py-2 text-[11px] font-bold uppercase tracking-[0.18em] transition`,
                                        activeTab === tab.id
                                            ? tw`bg-[color:var(--primary)] text-[color:var(--primary-foreground)]`
                                            : tw`text-neutral-400 hover:text-[#f8f6ef]`,
                                    ]}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div css={tw`min-h-0 flex-1 overflow-y-auto overflow-x-hidden pr-1`}>
                        {activeTab === 'overview' && (
                    <div css={tw`space-y-6`}>
                        <div css={tw`grid gap-4 md:grid-cols-2 xl:grid-cols-4`}>
                            <div css={statCard}>
                                <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                    Endpoint
                                </p>
                                <p css={tw`mt-2 break-all text-base font-semibold text-[#f8f6ef]`}>
                                    {database.connectionString}
                                </p>
                            </div>
                            <div css={statCard}>
                                <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                    Tables
                                </p>
                                <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>
                                    {health ? health.tableCount : tables.length}
                                </p>
                            </div>
                            <div css={statCard}>
                                <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                    Estimated Size
                                </p>
                                <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>
                                    {health ? formatBytes(health.sizeBytes) : tables.length ? formatBytes(
                                        tables.reduce((total, table) => total + table.sizeBytes, 0)
                                    ) : 'Loading...'}
                                </p>
                            </div>
                            <div css={statCard}>
                                <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                    DB Latency
                                </p>
                                <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>
                                    {health ? (health.reachable ? `${health.latencyMs} ms` : 'Unavailable') : 'Loading...'}
                                </p>
                            </div>
                        </div>

                        <div css={tw`grid gap-6 xl:grid-cols-[1.2fr_minmax(0,1fr)]`}>
                            <TitledGreyBox title={'Connection Overview'}>
                                <div css={tw`grid gap-4 md:grid-cols-2`}>
                                    <ConnectionField
                                        label={'Database Name'}
                                        value={database.name}
                                        copyValue={database.name}
                                    />
                                    <ConnectionField
                                        label={'Endpoint'}
                                        value={database.connectionString}
                                        copyValue={database.connectionString}
                                    />
                                    <ConnectionField
                                        label={'Username'}
                                        value={database.username}
                                        copyValue={database.username}
                                    />
                                    <ConnectionField label={'Connections From'} value={database.allowConnectionsFrom} />
                                    <Can action={'database.view_password'}>
                                        <ConnectionField
                                            label={'Password'}
                                            value={database.password || 'Unavailable'}
                                            copyValue={database.password}
                                        />
                                    </Can>
                                    <ConnectionField
                                        label={'JDBC Connection String'}
                                        value={jdbcConnectionString}
                                        copyValue={jdbcConnectionString}
                                    />
                                </div>
                            </TitledGreyBox>

                            <div css={tw`space-y-6`}>
                                <TitledGreyBox title={'Workspace Shortcuts'}>
                                    <div css={tw`grid gap-3`}>
                                        {[
                                            {
                                                title: 'Browse Tables',
                                                text: 'Inspect table structure and preview live rows.',
                                                tab: 'tables' as Tab,
                                            },
                                            {
                                                title: 'Run SQL Queries',
                                                text: 'Execute read or write statements from the built-in SQL editor.',
                                                tab: 'query' as Tab,
                                            },
                                            {
                                                title: 'Import or Export',
                                                text: 'Download dumps or import .sql content without leaving the panel.',
                                                tab: 'import-export' as Tab,
                                            },
                                            {
                                                title: 'Health Check',
                                                text: 'Measure reachability, latency, and storage footprint.',
                                                tab: 'health' as Tab,
                                            },
                                        ].map((item) => (
                                            <button
                                                key={item.title}
                                                type={'button'}
                                                onClick={() => setActiveTab(item.tab)}
                                                css={tw`rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-4 text-left transition hover:border-[color:var(--primary)]`}
                                            >
                                                <p css={tw`text-sm font-semibold text-[#f8f6ef]`}>{item.title}</p>
                                                <p css={tw`mt-2 text-sm leading-6 text-neutral-400`}>{item.text}</p>
                                            </button>
                                        ))}
                                    </div>
                                </TitledGreyBox>

                                <TitledGreyBox title={'Largest Tables'}>
                                    {overviewLargestTables.length > 0 ? (
                                        <div css={tw`space-y-3`}>
                                            {overviewLargestTables.map((table) => (
                                                <button
                                                    key={table.name}
                                                    type={'button'}
                                                    onClick={() => {
                                                        setSelectedTable(table.name);
                                                        setTablePage(1);
                                                        setActiveTab('tables');
                                                    }}
                                                    css={tw`flex w-full items-center justify-between rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-left transition hover:border-[color:var(--primary)]`}
                                                >
                                                    <div css={tw`min-w-0`}>
                                                        <p css={tw`truncate text-sm font-semibold text-[#f8f6ef]`}>
                                                            {table.name}
                                                        </p>
                                                        <p css={tw`mt-1 text-xs text-neutral-500`}>
                                                            {table.rows.toLocaleString()} rows
                                                        </p>
                                                    </div>
                                                    <span css={tw`text-xs font-semibold text-[color:var(--primary)]`}>
                                                        {formatBytes(table.sizeBytes)}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    ) : (
                                        <p css={tw`text-sm text-neutral-400`}>
                                            Load the table workspace to inspect table inventory.
                                        </p>
                                    )}
                                </TitledGreyBox>
                            </div>
                        </div>
                    </div>
                        )}

                        {activeTab === 'tables' && (
                    <div css={tw`grid gap-6 xl:grid-cols-[0.95fr_minmax(0,1.45fr)]`}>
                        <TitledGreyBox title={`Tables (${tables.length})`}>
                            <div css={tw`mb-4 flex justify-end`}>
                                <Button
                                    isSecondary
                                    size={'small'}
                                    onClick={() => refreshTables(false)}
                                    isLoading={tablesLoading}
                                    disabled={workspaceUnavailable || health?.reachable !== true}
                                >
                                    Refresh Tables
                                </Button>
                            </div>

                            {workspaceUnavailable ? (
                                <p css={tw`text-sm leading-6 text-neutral-400`}>
                                    Table metadata is unavailable while the panel cannot connect to this database host.
                                    Refresh health after the host is unblocked.
                                </p>
                            ) : tablesLoading && tables.length === 0 ? (
                                <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={6} className='min-h-[280px]' />
                            ) : tables.length > 0 ? (
                                <div css={tw`space-y-2`}>
                                    {tables.map((table) => (
                                        <button
                                            key={table.name}
                                            type={'button'}
                                            onClick={() => {
                                                setSelectedTable(table.name);
                                                setTablePage(1);
                                            }}
                                            css={[
                                                tw`w-full rounded-xl border px-4 py-3 text-left transition`,
                                                selectedTable === table.name
                                                    ? tw`border-[color:var(--primary)] bg-[color:var(--background)]`
                                                    : tw`border-[color:var(--border)] bg-[color:var(--background)] hover:border-[color:var(--primary)]`,
                                            ]}
                                        >
                                            <div css={tw`flex items-center justify-between gap-3`}>
                                                <p css={tw`truncate text-sm font-semibold text-[#f8f6ef]`}>
                                                    {table.name}
                                                </p>
                                                <span css={tw`text-xs font-semibold text-[color:var(--primary)]`}>
                                                    {formatBytes(table.sizeBytes)}
                                                </span>
                                            </div>
                                            <div css={tw`mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500`}>
                                                <span>{table.engine || 'Unknown engine'}</span>
                                                <span>{table.rows.toLocaleString()} rows</span>
                                                <span>{table.collation || 'No collation'}</span>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <p css={tw`text-sm text-neutral-400`}>No tables exist in this database yet.</p>
                            )}
                        </TitledGreyBox>

                        <div css={tw`space-y-6`}>
                            <TitledGreyBox title={selectedTable ? `Table Preview: ${selectedTable}` : 'Table Preview'}>
                                {selectedTableMeta && (
                                    <div css={tw`mb-4 grid gap-3 md:grid-cols-3`}>
                                        <div css={statCard}>
                                            <p css={tw`text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-500`}>
                                                Engine
                                            </p>
                                            <p css={tw`mt-2 text-sm font-semibold text-[#f8f6ef]`}>
                                                {selectedTableMeta.engine || 'Unknown'}
                                            </p>
                                        </div>
                                        <div css={statCard}>
                                            <p css={tw`text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-500`}>
                                                Estimated Rows
                                            </p>
                                            <p css={tw`mt-2 text-sm font-semibold text-[#f8f6ef]`}>
                                                {selectedTableMeta.rows.toLocaleString()}
                                            </p>
                                        </div>
                                        <div css={statCard}>
                                            <p css={tw`text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-500`}>
                                                Size
                                            </p>
                                            <p css={tw`mt-2 text-sm font-semibold text-[#f8f6ef]`}>
                                                {formatBytes(selectedTableMeta.sizeBytes)}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {workspaceUnavailable ? (
                                    <p css={tw`text-sm leading-6 text-neutral-400`}>
                                        Row previews are unavailable until the panel can reconnect to the database host.
                                    </p>
                                ) : selectedTable ? (
                                    <>
                                        <div css={tw`mb-4 flex flex-wrap items-center justify-between gap-3`}>
                                            <p css={tw`text-sm text-neutral-400`}>
                                                Inspect live rows from <strong css={tw`text-[#f8f6ef]`}>{selectedTable}</strong>.
                                            </p>
                                            <div css={tw`flex flex-wrap gap-2`}>
                                                <Button
                                                    isSecondary
                                                    size={'small'}
                                                    onClick={() => {
                                                        setQuerySeed(`SELECT * FROM \`${selectedTable}\` LIMIT 25;`);
                                                        setActiveTab('query');
                                                    }}
                                                >
                                                    Open In Query
                                                </Button>
                                                <Button
                                                    isSecondary
                                                    size={'small'}
                                                    onClick={() => refreshTablePreview(selectedTable, tablePage)}
                                                    isLoading={tableRowsLoading}
                                                >
                                                    Refresh Rows
                                                </Button>
                                            </div>
                                        </div>

                                        {tableRows ? (
                                            <>
                                                <div css={tw`mb-4 flex flex-wrap gap-2`}>
                                                    {tableRows.columns.map((column) => (
                                                        <span
                                                            key={column.name}
                                                            css={tw`rounded-full border border-[color:var(--border)] bg-[color:var(--background)] px-3 py-1 text-[11px] font-semibold text-neutral-300`}
                                                        >
                                                            {column.name}
                                                            <span css={tw`ml-2 text-neutral-500`}>{column.type}</span>
                                                        </span>
                                                    ))}
                                                </div>

                                                <DataTable
                                                    columns={tableRows.columns.map((column) => column.name)}
                                                    rows={tableRows.rows}
                                                    emptyMessage={'No rows exist for this table on the selected page.'}
                                                />
                                                <PaginationFooter
                                                    className={'mt-4'}
                                                    pagination={tableRows.pagination}
                                                    onPageSelect={(page) => setTablePage(page)}
                                                />
                                            </>
                                        ) : tableRowsLoading ? (
                                            <PageLoadingSkeleton
                                                showChrome={false}
                                                showSpinner={false}
                                                rows={6}
                                                className='min-h-[280px]'
                                            />
                                        ) : (
                                            <p css={tw`text-sm text-neutral-400`}>
                                                Select a table to preview its rows.
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <p css={tw`text-sm text-neutral-400`}>
                                        Choose a table from the left to inspect its columns and row data.
                                    </p>
                                )}
                            </TitledGreyBox>
                        </div>
                    </div>
                        )}

                        {activeTab === 'query' && (
                    <div css={tw`space-y-6`}>
                        <TitledGreyBox title={'SQL Query Console'}>
                            <div css={tw`mb-4 flex flex-wrap items-center justify-between gap-3`}>
                                <p css={tw`text-sm leading-6 text-neutral-400`}>
                                    Read-only statements such as <strong css={tw`text-[#f8f6ef]`}>SELECT</strong>,
                                    <strong css={tw`text-[#f8f6ef]`}> SHOW</strong>,
                                    <strong css={tw`text-[#f8f6ef]`}> DESCRIBE</strong>, and
                                    <strong css={tw`text-[#f8f6ef]`}> EXPLAIN</strong> are always allowed. Write
                                    statements require database update permission.
                                </p>
                                <div css={tw`flex flex-wrap gap-2`}>
                                    {selectedTable && (
                                        <Button
                                            isSecondary
                                            size={'small'}
                                            onClick={() => setQuerySeed(`SELECT * FROM \`${selectedTable}\` LIMIT 25;`)}
                                        >
                                            Query Selected Table
                                        </Button>
                                    )}
                                    <Button
                                        isSecondary
                                        size={'small'}
                                        onClick={() => setQuerySeed('SHOW TABLE STATUS;')}
                                    >
                                        Reset Template
                                    </Button>
                                </div>
                            </div>

                            {!canUpdateDatabase && (
                                <div
                                    css={tw`mb-4 rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-neutral-400`}
                                >
                                    This account is currently in read-only SQL mode for write statements.
                                </div>
                            )}

                            <div css={tw`min-h-[24rem] overflow-hidden rounded-xl border border-[color:var(--border)]`}>
                                <CodemirrorEditor
                                    mode={queryMode}
                                    filename={'query.sql'}
                                    initialContent={querySeed}
                                    onModeChanged={setQueryMode}
                                    onContentChanged={() => undefined}
                                    fetchContent={(fetcher) => {
                                        queryFetcher.current = fetcher;
                                    }}
                                    onContentSaved={() => executeQuery()}
                                />
                            </div>

                            <div css={tw`mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between`}>
                                <div css={tw`flex flex-wrap gap-2`}>
                                    {queryHistory.map((entry) => (
                                        <button
                                            key={entry}
                                            type={'button'}
                                            onClick={() => setQuerySeed(entry)}
                                            css={tw`max-w-[16rem] truncate rounded-full border border-[color:var(--border)] bg-[color:var(--background)] px-3 py-1.5 text-xs text-neutral-300 transition hover:border-[color:var(--primary)]`}
                                            title={entry}
                                        >
                                            {entry}
                                        </button>
                                    ))}
                                </div>
                                <div css={tw`flex flex-wrap justify-end gap-2`}>
                                    <Button isSecondary onClick={() => setQueryResult(null)}>
                                        Clear Results
                                    </Button>
                                    <Button onClick={() => executeQuery()} isLoading={queryLoading}>
                                        Run Query
                                    </Button>
                                </div>
                            </div>
                        </TitledGreyBox>

                        <TitledGreyBox title={'Query Results'}>
                            {queryResult ? (
                                <>
                                    <div css={tw`mb-4 grid gap-4 md:grid-cols-4`}>
                                        <div css={statCard}>
                                            <p css={tw`text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-500`}>
                                                Query Type
                                            </p>
                                            <p css={tw`mt-2 text-sm font-semibold text-[#f8f6ef]`}>
                                                {queryResult.type.toUpperCase()}
                                            </p>
                                        </div>
                                        <div css={statCard}>
                                            <p css={tw`text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-500`}>
                                                Mode
                                            </p>
                                            <p css={tw`mt-2 text-sm font-semibold text-[#f8f6ef]`}>
                                                {queryResult.mode === 'read' ? 'Read' : 'Write'}
                                            </p>
                                        </div>
                                        <div css={statCard}>
                                            <p css={tw`text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-500`}>
                                                Execution
                                            </p>
                                            <p css={tw`mt-2 text-sm font-semibold text-[#f8f6ef]`}>
                                                {queryResult.executionTimeMs} ms
                                            </p>
                                        </div>
                                        <div css={statCard}>
                                            <p css={tw`text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-500`}>
                                                Affected
                                            </p>
                                            <p css={tw`mt-2 text-sm font-semibold text-[#f8f6ef]`}>
                                                {queryResult.mode === 'write'
                                                    ? (queryResult.affectedRows ?? 0).toLocaleString()
                                                    : queryResult.rowCount.toLocaleString()}
                                            </p>
                                        </div>
                                    </div>

                                    <p css={tw`mb-4 text-sm text-neutral-400`}>
                                        {queryResult.message}
                                        {queryResult.truncated && ' Only the first 250 rows are shown.'}
                                    </p>

                                    <DataTable
                                        columns={queryColumns}
                                        rows={queryResult.rows}
                                        emptyMessage={
                                            queryResult.mode === 'write'
                                                ? 'This statement completed without returning a result set.'
                                                : 'The query returned no rows.'
                                        }
                                    />
                                </>
                            ) : (
                                <p css={tw`text-sm text-neutral-400`}>
                                    Run a SQL statement to see its result set, affected row count, and execution time.
                                </p>
                            )}
                        </TitledGreyBox>
                    </div>
                        )}

                        {activeTab === 'import-export' && (
                    <div css={tw`grid gap-6 xl:grid-cols-[0.95fr_minmax(0,1.05fr)]`}>
                        <TitledGreyBox title={'Export Database'}>
                            <p css={tw`text-sm leading-6 text-neutral-400`}>
                                Download a full SQL dump or generate a schema-only export for migration and backup
                                workflows.
                            </p>

                            <div css={tw`mt-6 space-y-3`}>
                                <Button onClick={() => runExport(false)} isLoading={exporting === 'full'}>
                                    Download Full Dump
                                </Button>
                                <Button
                                    isSecondary
                                    color={'primary'}
                                    onClick={() => runExport(true)}
                                    isLoading={exporting === 'schema'}
                                >
                                    Download Schema Only
                                </Button>
                            </div>

                            <div css={tw`mt-6 rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4`}>
                                <p css={tw`text-sm font-semibold text-[#f8f6ef]`}>Export Notes</p>
                                <ul css={tw`mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-400`}>
                                    <li>Exports are generated directly from the database user assigned to this server.</li>
                                    <li>Large databases may take longer depending on remote DB latency.</li>
                                    <li>Schema-only exports are useful for fresh migrations or quick reviews.</li>
                                </ul>
                            </div>
                        </TitledGreyBox>

                        <Can action={'database.update'}>
                            <TitledGreyBox title={'Import SQL'}>
                                <p css={tw`text-sm leading-6 text-neutral-400`}>
                                    Upload a `.sql` file or paste SQL directly. Multi-statement imports are supported
                                    here.
                                </p>

                                <div css={tw`mt-6 space-y-4`}>
                                    <div>
                                        <Label>SQL File</Label>
                                        <input
                                            key={importFileKey}
                                            type={'file'}
                                            accept={'.sql,.txt,text/plain,application/sql'}
                                            onChange={(event) => setImportFile(event.currentTarget.files?.[0] || null)}
                                            css={tw`block w-full rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-3 text-sm text-neutral-300`}
                                        />
                                    </div>
                                    <div>
                                        <Label>Or Paste SQL</Label>
                                        <textarea
                                            value={importSql}
                                            onChange={(event) => setImportSql(event.currentTarget.value)}
                                            rows={12}
                                            placeholder={'CREATE TABLE example (...);'}
                                            css={tw`block w-full rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] px-4 py-3 text-sm text-[#f8f6ef] outline-none transition focus:border-[color:var(--primary)]`}
                                        />
                                    </div>
                                </div>

                                <div css={tw`mt-6 flex justify-end`}>
                                    <Button onClick={() => runImport()} isLoading={importLoading}>
                                        Start Import
                                    </Button>
                                </div>

                                {lastImportMessage && (
                                    <div css={tw`mt-6 rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4`}>
                                        <p css={tw`text-sm font-semibold text-[#f8f6ef]`}>Last Import Output</p>
                                        <pre css={tw`mt-3 whitespace-pre-wrap break-words text-xs leading-6 text-neutral-400`}>
                                            {lastImportMessage}
                                        </pre>
                                    </div>
                                )}
                            </TitledGreyBox>
                        </Can>
                    </div>
                        )}

                        {activeTab === 'health' && (
                    <div css={tw`space-y-6`}>
                        <div css={tw`flex justify-end`}>
                            <Button isSecondary onClick={() => refreshHealth()} isLoading={healthLoading}>
                                Refresh Health
                            </Button>
                        </div>

                        {healthLoading && !health ? (
                            <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={7} className='min-h-[280px]' />
                        ) : health ? (
                            <>
                                <div css={tw`grid gap-4 md:grid-cols-2 xl:grid-cols-4`}>
                                    <div css={statCard}>
                                        <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                            Reachability
                                        </p>
                                        <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>
                                            {health.reachable ? 'Online' : 'Unavailable'}
                                        </p>
                                    </div>
                                    <div css={statCard}>
                                        <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                            Latency
                                        </p>
                                        <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>
                                            {health.reachable ? `${health.latencyMs} ms` : 'Unavailable'}
                                        </p>
                                    </div>
                                    <div css={statCard}>
                                        <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                            Estimated Rows
                                        </p>
                                        <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>
                                            {health.estimatedRows.toLocaleString()}
                                        </p>
                                    </div>
                                    <div css={statCard}>
                                        <p css={tw`text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500`}>
                                            Total Size
                                        </p>
                                        <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>
                                            {formatBytes(health.sizeBytes)}
                                        </p>
                                    </div>
                                </div>

                                <div css={tw`grid gap-6 xl:grid-cols-[0.95fr_minmax(0,1.05fr)]`}>
                                    <TitledGreyBox title={'Database Health Summary'}>
                                        <div css={tw`space-y-4`}>
                                            <ConnectionField label={'Server Version'} value={health.serverVersion} />
                                            <ConnectionField
                                                label={'Last Checked'}
                                                value={formatDate(health.checkedAt)}
                                            />
                                            <ConnectionField
                                                label={'Table Count'}
                                                value={health.tableCount.toLocaleString()}
                                            />
                                            <ConnectionField
                                                label={'Estimated Size'}
                                                value={formatBytes(health.sizeBytes)}
                                            />
                                        </div>
                                    </TitledGreyBox>

                                    <TitledGreyBox title={'Storage Distribution'}>
                                        {tables.length > 0 ? (
                                            <div css={tw`space-y-3`}>
                                                {[...tables]
                                                    .sort((left, right) => right.sizeBytes - left.sizeBytes)
                                                    .slice(0, 8)
                                                    .map((table) => (
                                                        <div
                                                            key={table.name}
                                                            css={tw`rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4`}
                                                        >
                                                            <div css={tw`flex items-center justify-between gap-3`}>
                                                                <p css={tw`truncate text-sm font-semibold text-[#f8f6ef]`}>
                                                                    {table.name}
                                                                </p>
                                                                <span css={tw`text-xs font-semibold text-[color:var(--primary)]`}>
                                                                    {formatBytes(table.sizeBytes)}
                                                                </span>
                                                            </div>
                                                            <p css={tw`mt-2 text-xs text-neutral-500`}>
                                                                Updated {formatDate(table.updatedAt)}
                                                            </p>
                                                        </div>
                                                    ))}
                                            </div>
                                        ) : (
                                            <p css={tw`text-sm text-neutral-400`}>
                                                Load table metadata to see the largest tables in this database.
                                            </p>
                                        )}
                                    </TitledGreyBox>
                                </div>
                            </>
                        ) : (
                            <p css={tw`text-sm text-neutral-400`}>
                                No health data has been loaded for this database yet.
                            </p>
                        )}
                    </div>
                        )}

                        {activeTab === 'settings' && (
                    <div css={tw`space-y-6`}>
                        <TitledGreyBox title={'Credentials & Access'}>
                            <div css={tw`grid gap-4 md:grid-cols-2`}>
                                <ConnectionField
                                    label={'Database Name'}
                                    value={database.name}
                                    copyValue={database.name}
                                />
                                <ConnectionField
                                    label={'Endpoint'}
                                    value={database.connectionString}
                                    copyValue={database.connectionString}
                                />
                                <ConnectionField
                                    label={'Username'}
                                    value={database.username}
                                    copyValue={database.username}
                                />
                                <ConnectionField label={'Connections From'} value={database.allowConnectionsFrom} />
                                <Can action={'database.view_password'}>
                                    <ConnectionField
                                        label={'Password'}
                                        value={database.password || 'Unavailable'}
                                        copyValue={database.password}
                                    />
                                </Can>
                                <ConnectionField
                                    label={'JDBC Connection String'}
                                    value={jdbcConnectionString}
                                    copyValue={jdbcConnectionString}
                                />
                            </div>

                            <div css={tw`mt-6 flex flex-col justify-end gap-3 sm:flex-row`}>
                                <Can action={'database.update'}>
                                    <RotatePasswordButton databaseId={database.id} onUpdate={appendDatabase} />
                                </Can>
                                <Button isSecondary onClick={() => setActiveTab('overview')}>
                                    Back to Overview
                                </Button>
                            </div>
                        </TitledGreyBox>

                        <Can action={'database.delete'}>
                            <TitledGreyBox title={'Danger Zone'}>
                                <p css={tw`text-sm leading-6 text-neutral-300`}>
                                    Delete this database only if you are sure it is no longer needed. This action
                                    removes the database permanently and cannot be reversed.
                                </p>
                                <div css={tw`mt-6 flex justify-end`}>
                                    <Button color={'red'} isSecondary onClick={() => setDeleteVisible(true)}>
                                        Delete Database
                                    </Button>
                                </div>
                            </TitledGreyBox>
                        </Can>
                    </div>
                        )}
                    </div>
                </div>
            </ServerContentBlock>
        </>
    );
};

export default DatabaseDashboardContainer;
