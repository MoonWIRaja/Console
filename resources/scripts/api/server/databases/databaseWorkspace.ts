import http, { getPaginationSet, PaginationDataSet } from '@/api/http';

export interface DatabaseWorkspaceTable {
    name: string;
    engine: string;
    rows: number;
    collation: string;
    sizeBytes: number;
    dataLength: number;
    indexLength: number;
    createdAt: string | null;
    updatedAt: string | null;
    comment: string;
}

export interface DatabaseWorkspaceColumn {
    name: string;
    type: string;
    nullable: boolean;
    key: string;
    defaultValue: string | null;
    extra: string;
    comment: string;
}

export interface DatabaseWorkspaceRows {
    table: string;
    columns: DatabaseWorkspaceColumn[];
    rows: Record<string, unknown>[];
    pagination: PaginationDataSet;
}

export interface DatabaseWorkspaceHealth {
    reachable: boolean;
    latencyMs: number;
    serverVersion: string;
    tableCount: number;
    estimatedRows: number;
    sizeBytes: number;
    checkedAt: string;
    errorMessage?: string | null;
}

export interface DatabaseWorkspaceQueryResult {
    type: string;
    mode: 'read' | 'write';
    columns: string[];
    rows: Record<string, unknown>[];
    rowCount: number;
    truncated: boolean;
    affectedRows: number | null;
    executionTimeMs: number;
    message: string;
}

export interface DatabaseWorkspaceImportResult {
    message: string;
    bytes: number;
    executionTimeMs: number;
    output: string;
}

const rawDataToTable = (data: any): DatabaseWorkspaceTable => ({
    name: data.name,
    engine: data.engine,
    rows: data.rows,
    collation: data.collation,
    sizeBytes: data.size_bytes,
    dataLength: data.data_length,
    indexLength: data.index_length,
    createdAt: data.created_at || null,
    updatedAt: data.updated_at || null,
    comment: data.comment || '',
});

const rawDataToColumn = (data: any): DatabaseWorkspaceColumn => ({
    name: data.name,
    type: data.type,
    nullable: data.nullable,
    key: data.key,
    defaultValue: data.default,
    extra: data.extra || '',
    comment: data.comment || '',
});

const rawDataToRows = (data: any): DatabaseWorkspaceRows => ({
    table: data.table,
    columns: (data.columns || []).map(rawDataToColumn),
    rows: data.rows || [],
    pagination: getPaginationSet(data.pagination),
});

const rawDataToHealth = (data: any): DatabaseWorkspaceHealth => ({
    reachable: data.reachable,
    latencyMs: data.latency_ms,
    serverVersion: data.server_version,
    tableCount: data.table_count,
    estimatedRows: data.estimated_rows,
    sizeBytes: data.size_bytes,
    checkedAt: data.checked_at,
    errorMessage: data.error_message || null,
});

const rawDataToQueryResult = (data: any): DatabaseWorkspaceQueryResult => ({
    type: data.type,
    mode: data.mode,
    columns: data.columns || [],
    rows: data.rows || [],
    rowCount: data.row_count,
    truncated: data.truncated,
    affectedRows: data.affected_rows,
    executionTimeMs: data.execution_time_ms,
    message: data.message,
});

const rawDataToImportResult = (data: any): DatabaseWorkspaceImportResult => ({
    message: data.message,
    bytes: data.bytes,
    executionTimeMs: data.execution_time_ms,
    output: data.output || '',
});

const normalizeBlobError = async (error: any) => {
    if (error?.response?.data instanceof Blob) {
        const text = await error.response.data.text();
        try {
            error.response.data = JSON.parse(text);
        } catch {
            error.response.data = { errors: [{ detail: text || error.message }] };
        }
    }

    throw error;
};

export const getDatabaseWorkspaceTables = async (uuid: string, database: string): Promise<DatabaseWorkspaceTable[]> => {
    const response = await http.get(`/api/client/servers/${uuid}/databases/${database}/tables`);

    return (response.data.data || []).map(rawDataToTable);
};

export const getDatabaseWorkspaceRows = async (
    uuid: string,
    database: string,
    table: string,
    page = 1,
    perPage = 25
): Promise<DatabaseWorkspaceRows> => {
    const response = await http.get(`/api/client/servers/${uuid}/databases/${database}/rows`, {
        params: {
            table,
            page,
            per_page: perPage,
        },
    });

    return rawDataToRows(response.data.data);
};

export const getDatabaseWorkspaceHealth = async (uuid: string, database: string): Promise<DatabaseWorkspaceHealth> => {
    const response = await http.get(`/api/client/servers/${uuid}/databases/${database}/health`);

    return rawDataToHealth(response.data.data);
};

export const executeDatabaseWorkspaceQuery = async (
    uuid: string,
    database: string,
    query: string
): Promise<DatabaseWorkspaceQueryResult> => {
    const response = await http.post(`/api/client/servers/${uuid}/databases/${database}/query`, { query });

    return rawDataToQueryResult(response.data.data);
};

export const importDatabaseWorkspaceSql = async (
    uuid: string,
    database: string,
    payload: { sql?: string; file?: File | null }
): Promise<DatabaseWorkspaceImportResult> => {
    const form = new FormData();

    if (payload.sql) {
        form.append('sql', payload.sql);
    }

    if (payload.file) {
        form.append('sql_file', payload.file);
    }

    const response = await http.post(`/api/client/servers/${uuid}/databases/${database}/import`, form, {
        headers: {
            'Content-Type': 'multipart/form-data',
            Accept: 'application/json',
        },
    });

    return rawDataToImportResult(response.data.data);
};

export const downloadDatabaseWorkspaceExport = async (
    uuid: string,
    database: string,
    schemaOnly = false
): Promise<void> => {
    try {
        const response = await http.get(`/api/client/servers/${uuid}/databases/${database}/export`, {
            params: schemaOnly ? { schema_only: 1 } : undefined,
            responseType: 'blob',
            headers: {
                Accept: 'application/sql, application/json',
            },
        });

        const disposition = response.headers['content-disposition'] || '';
        const matches = disposition.match(/filename="?([^"]+)"?/i);
        const filename = matches?.[1] || `${database}.sql`;
        const url = window.URL.createObjectURL(response.data);
        const anchor = document.createElement('a');

        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();

        window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
    } catch (error) {
        await normalizeBlobError(error);
    }
};
