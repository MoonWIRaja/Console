import http from '@/api/http';

export interface CloudFile {
    uuid: string;
    name: string;
    mime: string;
    size: number;
    source: 'upload' | 'anney';
    created_at: string | null;
    download_url: string;
    share_url: string | null;
    owner?: string | null;
}

export interface CloudOverview {
    enabled: boolean;
    quota: number;
    used: number;
    max_upload: number | null;
    files: CloudFile[];
    shared: CloudFile[];
}

export const getCloud = (): Promise<CloudOverview> => http.get('/api/client/cloud').then(({ data }) => data);

const CHUNK_SIZE = 16 * 1024 * 1024; // must be a multiple of 256 KB for Google resumable uploads

interface ChunkResult {
    done: boolean;
    next: number;
    fileId?: string;
}

const putChunk = (url: string, file: File, start: number, onBytes: (sent: number) => void): Promise<ChunkResult> =>
    new Promise((resolve, reject) => {
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url);
        xhr.setRequestHeader('Content-Range', `bytes ${start}-${end - 1}/${file.size}`);
        xhr.upload.onprogress = (event) => onBytes(start + event.loaded);
        xhr.onload = () => {
            if (xhr.status === 200 || xhr.status === 201) {
                try {
                    resolve({ done: true, next: file.size, fileId: JSON.parse(xhr.responseText).id });
                } catch {
                    reject(new Error('Google Drive returned an unreadable response.'));
                }
            } else if (xhr.status === 308) {
                const range = xhr.getResponseHeader('Range');
                const match = range ? range.match(/bytes=0-(\d+)/) : null;
                resolve({ done: false, next: match ? Number(match[1]) + 1 : end });
            } else {
                reject(new Error(`Upload failed (HTTP ${xhr.status}).`));
            }
        };
        xhr.onerror = () => reject(new Error('Network error while uploading.'));
        xhr.send(file.slice(start, end));
    });

/**
 * Uploads straight from the browser to Google Drive in chunks; the panel only
 * reserves quota before and verifies the file after, so there is no size cap.
 */
export const uploadCloudFile = async (file: File, onProgress?: (percent: number) => void): Promise<CloudFile> => {
    const { data: session } = await http.post('/api/client/cloud/uploads', {
        name: file.name,
        size: file.size,
        mime: file.type || 'application/octet-stream',
    });

    let offset = 0;
    let fileId: string | undefined;
    let failures = 0;

    while (!fileId) {
        try {
            const result = await putChunk(session.upload_url, file, offset, (sent) =>
                onProgress?.(Math.min(99, Math.round((sent / file.size) * 100)))
            );
            failures = 0;
            offset = result.next;
            fileId = result.fileId;
        } catch (error) {
            if (++failures > 3) throw error;
            await new Promise((r) => setTimeout(r, 1500 * failures));
        }
    }

    onProgress?.(100);
    const { data } = await http.post(`/api/client/cloud/uploads/${session.token}/complete`, { file_id: fileId });

    return data;
};

export const deleteCloudFile = (uuid: string): Promise<void> =>
    http.delete(`/api/client/cloud/files/${uuid}`).then(() => undefined);

export const shareCloudFile = (uuid: string, enabled: boolean): Promise<CloudFile> =>
    http.post(`/api/client/cloud/files/${uuid}/share`, { enabled }).then(({ data }) => data);

export const leaveSharedFile = (uuid: string): Promise<void> =>
    http.delete(`/api/client/cloud/shared/${uuid}`).then(() => undefined);

export const formatBytes = (bytes: number): string => {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }

    return `${i === 0 ? value : value.toFixed(1)} ${units[i]}`;
};
