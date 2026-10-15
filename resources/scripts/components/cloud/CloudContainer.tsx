import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useHistory, useLocation } from 'react-router-dom';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import { Dialog } from '@/components/elements/dialog';
import FlashMessageRender from '@/components/FlashMessageRender';
import {
    CloudFile,
    CloudOverview,
    deleteCloudFile,
    formatBytes,
    getCloud,
    leaveSharedFile,
    shareCloudFile,
    uploadCloudFile,
} from '@/api/cloud';

type Section = 'files' | 'recent' | 'anney' | 'shared';
type View = 'grid' | 'list';

interface UploadItem {
    id: number;
    name: string;
    size: number;
    progress: number;
    error?: string;
    done?: boolean;
}

const SECTIONS: { key: Section; label: string; icon: string }[] = [
    { key: 'files', label: 'My Cloud', icon: 'cloud' },
    { key: 'recent', label: 'Recent', icon: 'schedule' },
    { key: 'anney', label: 'From Anney', icon: 'smart_toy' },
    { key: 'shared', label: 'Shared with me', icon: 'group' },
];

const kindOf = (file: CloudFile): { icon: string; color: string; label: string } => {
    const name = file.name.toLowerCase();
    const ext = name.includes('.') ? name.split('.').pop() || '' : '';
    const mime = file.mime || '';

    if (mime.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext))
        return { icon: 'image', color: '#C0392B', label: 'Image' };
    if (mime.startsWith('video/') || ['mp4', 'mkv', 'mov', 'webm'].includes(ext))
        return { icon: 'movie', color: '#B03A2E', label: 'Video' };
    if (mime.startsWith('audio/') || ['mp3', 'wav', 'ogg', 'flac'].includes(ext))
        return { icon: 'music_note', color: '#8E44AD', label: 'Audio' };
    if (ext === 'pdf') return { icon: 'picture_as_pdf', color: '#C0392B', label: 'PDF' };
    if (['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'mrpack', 'jar'].includes(ext))
        return { icon: 'folder_zip', color: '#7F6A3A', label: 'Archive' };
    if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) return { icon: 'table_chart', color: '#2D6A4F', label: 'Sheet' };
    if (['doc', 'docx', 'odt', 'rtf', 'md', 'txt'].includes(ext))
        return { icon: 'description', color: '#2F5D8A', label: 'Document' };
    if (
        [
            'json',
            'yml',
            'yaml',
            'toml',
            'properties',
            'cfg',
            'conf',
            'ini',
            'xml',
            'log',
            'js',
            'ts',
            'php',
            'py',
            'java',
            'lua',
            'sh',
        ].includes(ext)
    )
        return { icon: 'code', color: '#2D4A3E', label: 'Code / config' };

    return { icon: 'draft', color: '#742220', label: 'File' };
};

const Icon = ({ name, size = 20, color }: { name: string; size?: number; color?: string }) => (
    <span className='material-icons-round' aria-hidden style={{ fontSize: size, color, lineHeight: 1 }}>
        {name}
    </span>
);

const dateOf = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '';

const CloudContainer = () => {
    const location = useLocation();
    const params = new URLSearchParams(location.search);
    const history = useHistory();
    const sectionParam = params.get('section') || (params.get('tab') === 'shared' ? 'shared' : 'files');
    const section: Section = (['files', 'recent', 'anney', 'shared'] as Section[]).includes(sectionParam as Section)
        ? (sectionParam as Section)
        : 'files';
    const setSection = (next: Section) => history.push(`/cloud?section=${next}`);
    const [view, setView] = useState<View>(() => {
        try {
            return window.localStorage.getItem('cloud:view') === 'list' ? 'list' : 'grid';
        } catch {
            return 'grid';
        }
    });
    const [data, setData] = useState<CloudOverview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(
        params.get('missing') ? 'That share link is no longer valid.' : null
    );
    const { addFlash } = useFlash();

    // Messages show as the panel's bottom-right toasts (same as a wrong password on login).
    useEffect(() => {
        if (!error && !notice) return;
        if (error) addFlash({ key: 'cloud', type: 'error', title: 'Error', message: error });
        if (notice) addFlash({ key: 'cloud', type: 'success', title: 'Success', message: notice });
        setError(null);
        setNotice(null);
    }, [error, notice]);
    const [uploads, setUploads] = useState<UploadItem[]>([]);
    const [uploadsOpen, setUploadsOpen] = useState(true);
    const [menu, setMenu] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const highlight = params.get('file');

    const load = useCallback(
        () =>
            getCloud()
                .then(setData)
                .catch((e) => setError(httpErrorToHuman(e))),
        []
    );

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        try {
            window.localStorage.setItem('cloud:view', view);
        } catch {
            // ignore
        }
    }, [view]);

    useEffect(() => {
        if (!menu) return;
        const close = () => setMenu(null);
        window.addEventListener('click', close);

        return () => window.removeEventListener('click', close);
    }, [menu]);

    const startUpload = (list: FileList | File[]) => {
        setUploadsOpen(true);
        Array.from(list).forEach((file) => {
            const id = Date.now() + Math.random();
            setUploads((u) => [...u, { id, name: file.name, size: file.size, progress: 0 }]);
            uploadCloudFile(file, (progress) =>
                setUploads((u) => u.map((item) => (item.id === id ? { ...item, progress } : item)))
            )
                .then(() => {
                    setUploads((u) =>
                        u.map((item) => (item.id === id ? { ...item, progress: 100, done: true } : item))
                    );
                    load();
                })
                .catch((e) =>
                    setUploads((u) =>
                        u.map((item) => (item.id === id ? { ...item, error: httpErrorToHuman(e) } : item))
                    )
                );
        });
    };

    const copyLink = (url: string) => {
        navigator.clipboard?.writeText(url).catch(() => undefined);
        setNotice('Link copied. People need to sign in (or sign up) to download it.');
    };

    const toggleShare = (file: CloudFile) => {
        const enable = !file.share_url;
        shareCloudFile(file.uuid, enable)
            .then((updated) => {
                if (enable && updated.share_url) copyLink(updated.share_url);
                else setNotice('Sharing stopped. People who opened the link lost access.');
                load();
            })
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    const [pendingDelete, setPendingDelete] = useState<CloudFile | null>(null);

    const remove = (file: CloudFile) => setPendingDelete(file);

    const doDelete = (file: CloudFile) => {
        setPendingDelete(null);
        deleteCloudFile(file.uuid)
            .then(load)
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    const leave = (file: CloudFile) => {
        leaveSharedFile(file.uuid)
            .then(load)
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    const rows = useMemo(() => {
        if (!data) return [];
        let list: CloudFile[] =
            section === 'shared'
                ? data.shared
                : section === 'anney'
                ? data.files.filter((f) => f.source === 'anney')
                : data.files;
        if (section === 'recent')
            list = [...list].sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')).slice(0, 30);
        const q = search.trim().toLowerCase();

        return q ? list.filter((f) => f.name.toLowerCase().includes(q)) : list;
    }, [data, section, search]);

    const percent = data ? Math.min(100, Math.round((data.used / Math.max(1, data.quota)) * 100)) : 0;
    const canUpload = !!data?.enabled && section !== 'shared';
    const title = SECTIONS.find((s) => s.key === section)?.label;
    const activeUploads = uploads.filter((u) => !u.done && !u.error).length;

    // Once everything has finished, the uploads panel closes itself after 30 seconds.
    useEffect(() => {
        if (uploads.length === 0 || activeUploads > 0) return;
        const timer = setTimeout(() => setUploads([]), 30000);
        return () => clearTimeout(timer);
    }, [uploads, activeUploads]);

    const actions = (file: CloudFile) => (
        <div className='cloud-menu' onClick={(e) => e.stopPropagation()}>
            <a href={file.download_url} className='cloud-menu-item'>
                <Icon name='download' size={18} /> Download
            </a>
            {section !== 'shared' ? (
                <>
                    {file.share_url && (
                        <button
                            type='button'
                            className='cloud-menu-item'
                            onClick={() => {
                                copyLink(file.share_url as string);
                                setMenu(null);
                            }}
                        >
                            <Icon name='link' size={18} /> Copy link
                        </button>
                    )}
                    <button
                        type='button'
                        className='cloud-menu-item'
                        onClick={() => {
                            toggleShare(file);
                            setMenu(null);
                        }}
                    >
                        <Icon name={file.share_url ? 'link_off' : 'person_add'} size={18} />
                        {file.share_url ? 'Stop sharing' : 'Share'}
                    </button>
                    <div className='cloud-menu-sep' />
                    <button
                        type='button'
                        className='cloud-menu-item cloud-danger'
                        onClick={() => {
                            remove(file);
                            setMenu(null);
                        }}
                    >
                        <Icon name='delete' size={18} /> Delete
                    </button>
                </>
            ) : (
                <button
                    type='button'
                    className='cloud-menu-item'
                    onClick={() => {
                        leave(file);
                        setMenu(null);
                    }}
                >
                    <Icon name='remove_circle_outline' size={18} /> Remove from list
                </button>
            )}
        </div>
    );

    const kebab = (file: CloudFile) => (
        <div className='cloud-kebab-wrap'>
            <button
                type='button'
                className='cloud-icon-btn'
                title='More actions'
                onClick={(e) => {
                    e.stopPropagation();
                    setMenu(menu === file.uuid ? null : file.uuid);
                }}
            >
                <Icon name='more_vert' size={20} />
            </button>
            {menu === file.uuid && actions(file)}
        </div>
    );

    return (
        <div
            className='cloud-shell'
            onDragOver={(e) => {
                if (!canUpload) return;
                e.preventDefault();
                setDragging(true);
            }}
            onDragLeave={(e) => {
                if (e.currentTarget === e.target) setDragging(false);
            }}
            onDrop={(e) => {
                if (!canUpload) return;
                e.preventDefault();
                setDragging(false);
                startUpload(e.dataTransfer.files);
            }}
        >
            <style>{`
                .cloud-shell { position: relative; display: flex; flex-direction: column; height: 100%; min-height: 0; overflow-y: auto; background: #D6D2C7; padding: 24px 16px 32px; color: #742220; font-family: var(--font-sans, 'Geist Mono', monospace); }
                .cloud-shell::before { content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0; background-image: repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px); }
                @media (min-width: 768px) { .cloud-shell { padding: 32px 32px 40px; } }
                .cloud-layout { position: relative; z-index: 1; display: grid; gap: 24px; grid-template-columns: 1fr; width: 100%; flex: 1 1 auto; min-height: 0; }
                .cloud-side { padding: 18px; display: flex; flex-direction: column; min-height: 0; }
                .cloud-main { padding: 22px; min-width: 0; display: flex; flex-direction: column; min-height: 0; }
                .cloud-main-body { flex: 1 1 auto; min-height: 0; }
                @media (min-width: 1024px) {
                    .cloud-shell { overflow: hidden; }
                    .cloud-layout { grid-template-columns: 280px minmax(0, 1fr); height: 100%; }
                    .cloud-side, .cloud-main { height: 100%; }
                    .cloud-main-body { overflow-y: auto; margin-right: -8px; padding-right: 8px; }
                    .cloud-storage { margin-top: auto !important; }
                }
                .cloud-panel { border-radius: 12px; border: 2px solid #2D4A3E; background: #FEF9E1; box-shadow: 4px 4px 0 0 #2D4A3E; }
                .cloud-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: rgba(116,34,32,.55); }
                .cloud-new { display: inline-flex; align-items: center; gap: 10px; width: 100%; justify-content: center; border-radius: 999px; border: 2px solid #5a1a18; background: #742220; color: #FEF9E1; padding: 12px 18px; font-size: 11px; font-weight: 900; letter-spacing: .22em; text-transform: uppercase; box-shadow: 3px 3px 0 0 #5a1a18; cursor: pointer; transition: all .2s; font-family: inherit; }
                .cloud-new:hover:not(:disabled) { transform: translate(-1px,-1px); box-shadow: 5px 5px 0 0 #5a1a18; }
                .cloud-new:disabled { opacity: .5; cursor: not-allowed; }
                .cloud-nav { display: flex; flex-direction: column; gap: 4px; margin-top: 18px; }
                @media (max-width: 1023px) { .cloud-nav { flex-direction: row; overflow-x: auto; } .cloud-nav-item { white-space: nowrap; } }
                .cloud-nav-item { display: flex; align-items: center; gap: 12px; border: 2px solid transparent; background: transparent; border-radius: 999px; padding: 9px 14px; color: #742220; font-size: 12px; font-weight: 800; cursor: pointer; text-align: left; font-family: inherit; }
                .cloud-nav-item:hover { background: rgba(45,74,62,.08); }
                .cloud-nav-item.active { background: #F5EFD5; border-color: #2D4A3E; color: #2D4A3E; }
                .cloud-meter { height: 8px; border-radius: 999px; background: #EDE6D0; border: 1px solid #C8BCA0; overflow: hidden; margin: 10px 0 8px; }
                .cloud-meter > div { height: 100%; border-radius: 999px; transition: width .3s; }
                .cloud-search { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px; background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 999px; padding: 8px 16px; }
                .cloud-search:focus-within { border-color: #2D4A3E; }
                .cloud-search input { flex: 1; border: none; outline: none; background: transparent; color: #742220; font-size: 13px; font-family: inherit; }
                .cloud-toggle { display: inline-flex; border: 2px solid #2D4A3E; border-radius: 999px; overflow: hidden; }
                .cloud-toggle button { border: none; background: #FEF9E1; color: #2D4A3E; padding: 6px 12px; cursor: pointer; display: flex; }
                .cloud-toggle button.on { background: #2D4A3E; color: #FEF9E1; }
                .cloud-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); }
                .cloud-card { position: relative; border-radius: 12px; border: 2px solid #C8BCA0; background: #F5EFD5; transition: all .15s; }
                .cloud-card:hover { border-color: #2D4A3E; box-shadow: 3px 3px 0 0 #2D4A3E; transform: translate(-1px,-1px); }
                .cloud-card.hl { border-color: #742220; box-shadow: 3px 3px 0 0 #742220; }
                .cloud-card-head { display: flex; align-items: center; gap: 8px; padding: 10px 6px 8px 12px; }
                .cloud-card-name { flex: 1; min-width: 0; font-size: 12px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #4B3A2F; }
                .cloud-thumb { display: flex; align-items: center; justify-content: center; height: 120px; margin: 0 10px; border-radius: 8px; background: #FEF9E1; border: 1px solid #E2D8BD; text-decoration: none; }
                .cloud-card-foot { display: flex; justify-content: space-between; gap: 6px; padding: 8px 12px 10px; font-size: 10px; color: rgba(116,34,32,.55); }
                .cloud-icon-btn { border: none; background: transparent; color: #742220; border-radius: 999px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
                .cloud-icon-btn:hover { background: rgba(45,74,62,.12); }
                .cloud-kebab-wrap { position: relative; }
                .cloud-menu { position: absolute; right: 0; top: 34px; z-index: 30; min-width: 190px; padding: 6px; border-radius: 10px; border: 2px solid #2D4A3E; background: #FEF9E1; box-shadow: 4px 4px 0 0 #2D4A3E; }
                .cloud-menu-item { display: flex; align-items: center; gap: 12px; width: 100%; border: none; background: transparent; padding: 8px 10px; border-radius: 6px; color: #4B3A2F; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; text-align: left; }
                .cloud-menu-item:hover { background: #F5EFD5; }
                .cloud-danger { color: #A03A2F; }
                .cloud-menu-sep { height: 1px; background: #C8BCA0; margin: 4px 0; }
                .cloud-table { width: 100%; border-collapse: collapse; }
                .cloud-table th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #2D4A3E; }
                .cloud-table td { padding: 10px 12px; border-bottom: 1px solid #E2D8BD; font-size: 12px; color: #4B3A2F; }
                .cloud-table tr:hover td { background: #F5EFD5; }
                .cloud-table tr.hl td { background: rgba(116,34,32,.06); }
                .cloud-badge { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 2px 8px; font-size: 9px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; border: 1px solid; }
                .cloud-drop { position: fixed; inset: 0; z-index: 60; background: rgba(45,74,62,.18); display: flex; align-items: center; justify-content: center; pointer-events: none; }
                .cloud-drop > div { border: 3px dashed #2D4A3E; background: #FEF9E1; border-radius: 16px; padding: 28px 40px; box-shadow: 6px 6px 0 0 #2D4A3E; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: #2D4A3E; display: flex; align-items: center; gap: 14px; }
                .cloud-uploads { position: fixed; right: 24px; bottom: 100px; z-index: 50; width: 360px; max-width: calc(100vw - 32px); overflow: hidden; padding: 0 !important; }
                .cu-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #F5EFD5; }
                .cu-title { font-size: 12px; font-weight: 900; color: #4B3A2F; }
                .cu-sub { font-size: 10px; color: rgba(116,34,32,.55); margin-top: 1px; }
                .cu-list { max-height: 240px; overflow-y: auto; scrollbar-width: thin; }
                .cu-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-top: 1px solid #E2D8BD; }
                .cu-item:first-child { border-top: 0; }
                .cu-file { width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; background: #F5EFD5; border: 1px solid #C8BCA0; display: flex; align-items: center; justify-content: center; }
                .cu-name { font-size: 12px; font-weight: 800; color: #4B3A2F; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                .cu-meta { font-size: 10px; color: rgba(116,34,32,.6); margin-top: 2px; }
                .cu-progress { display: flex; align-items: center; gap: 8px; margin-top: 5px; }
                .cu-progress span { font-size: 10px; font-weight: 800; color: #2D4A3E; min-width: 30px; text-align: right; }
            `}</style>

            <FlashMessageRender byKey='cloud' />
            <Dialog.Confirm
                open={!!pendingDelete}
                onClose={() => setPendingDelete(null)}
                title='Delete File'
                confirm='Delete'
                onConfirmed={() => pendingDelete && doDelete(pendingDelete)}
            >
                <span className='font-semibold text-[color:var(--primary)]'>{pendingDelete?.name}</span> will be
                deleted permanently from your cloud storage. This cannot be undone.
            </Dialog.Confirm>

            <div className='cloud-layout'>
                <aside className='cloud-panel cloud-side'>
                    <button
                        type='button'
                        className='cloud-new'
                        disabled={!data?.enabled}
                        onClick={() => inputRef.current?.click()}
                    >
                        <Icon name='add' size={18} /> New upload
                    </button>
                    <input
                        ref={inputRef}
                        type='file'
                        multiple
                        style={{ display: 'none' }}
                        onChange={(e) => {
                            if (e.target.files) startUpload(e.target.files);
                            e.target.value = '';
                        }}
                    />
                    <nav className='cloud-nav'>
                        {SECTIONS.map((s) => (
                            <button
                                key={s.key}
                                type='button'
                                className={`cloud-nav-item ${section === s.key ? 'active' : ''}`}
                                onClick={() => setSection(s.key)}
                            >
                                <Icon name={s.icon} size={20} />
                                {s.label}
                                {s.key === 'shared' && (data?.shared.length || 0) > 0 && (
                                    <span style={{ marginLeft: 'auto', fontSize: 10 }}>{data?.shared.length}</span>
                                )}
                            </button>
                        ))}
                    </nav>
                    {data && (
                        <div
                            className='cloud-storage'
                            style={{ marginTop: 20, paddingTop: 16, borderTop: '2px solid #C8BCA0' }}
                        >
                            <div className='cloud-eyebrow' style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                <Icon name='cloud_queue' size={14} /> Storage
                            </div>
                            <div className='cloud-meter'>
                                <div
                                    style={{ width: `${percent}%`, background: percent > 90 ? '#A03A2F' : '#2D4A3E' }}
                                />
                            </div>
                            <div style={{ fontSize: 12, fontWeight: 700, color: '#4B3A2F' }}>
                                {formatBytes(data.used)} of {formatBytes(data.quota)} used
                            </div>
                            <div style={{ fontSize: 10, marginTop: 4, color: 'rgba(116,34,32,.55)' }}>
                                {formatBytes(Math.max(0, data.quota - data.used))} free · no file size limit
                            </div>
                        </div>
                    )}
                </aside>

                <section className='cloud-panel cloud-main'>
                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12, marginBottom: 18 }}>
                        <div className='cloud-search'>
                            <Icon name='search' size={20} color='#742220' />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={`Search in ${title}`}
                            />
                            {search && (
                                <button
                                    type='button'
                                    className='cloud-icon-btn'
                                    style={{ width: 24, height: 24 }}
                                    onClick={() => setSearch('')}
                                >
                                    <Icon name='close' size={16} />
                                </button>
                            )}
                        </div>
                        <div className='cloud-toggle'>
                            <button
                                type='button'
                                className={view === 'list' ? 'on' : ''}
                                title='List view'
                                onClick={() => setView('list')}
                            >
                                <Icon name='view_list' size={18} />
                            </button>
                            <button
                                type='button'
                                className={view === 'grid' ? 'on' : ''}
                                title='Grid view'
                                onClick={() => setView('grid')}
                            >
                                <Icon name='grid_view' size={18} />
                            </button>
                        </div>
                    </div>

                    <div className='cloud-main-body'>
                        {!data && <div style={{ padding: 40, textAlign: 'center', fontSize: 12 }}>Loading…</div>}
                        {data && !data.enabled && section !== 'shared' && (
                            <div
                                style={{
                                    padding: '12px 14px',
                                    borderRadius: 10,
                                    border: '2px dashed #C8BCA0',
                                    fontSize: 12,
                                    marginBottom: 16,
                                }}
                            >
                                Cloud Storage is not available right now. Please check back later.
                            </div>
                        )}

                        {data && rows.length === 0 && (
                            <div style={{ padding: '56px 16px', textAlign: 'center' }}>
                                <Icon
                                    name={section === 'shared' ? 'folder_shared' : 'cloud_upload'}
                                    size={64}
                                    color='#C8BCA0'
                                />
                                <div style={{ marginTop: 12, fontSize: 14, fontWeight: 800 }}>
                                    {search
                                        ? 'No files match your search'
                                        : section === 'shared'
                                        ? 'Nothing shared with you yet'
                                        : section === 'anney'
                                        ? 'No files from Anney yet'
                                        : 'Drop files here'}
                                </div>
                                <div style={{ marginTop: 6, fontSize: 11, color: 'rgba(116,34,32,.55)' }}>
                                    {section === 'shared'
                                        ? 'When someone sends you a share link, the file appears here after you open it.'
                                        : section === 'anney'
                                        ? 'Files you attach in the Anney chat are saved here.'
                                        : 'or use the New upload button.'}
                                </div>
                            </div>
                        )}

                        {rows.length > 0 && view === 'grid' && (
                            <div className='cloud-grid'>
                                {rows.map((file) => {
                                    const kind = kindOf(file);

                                    return (
                                        <div
                                            key={file.uuid}
                                            className={`cloud-card ${highlight === file.uuid ? 'hl' : ''}`}
                                        >
                                            <div className='cloud-card-head'>
                                                <Icon name={kind.icon} size={20} color={kind.color} />
                                                <span className='cloud-card-name' title={file.name}>
                                                    {file.name}
                                                </span>
                                                {kebab(file)}
                                            </div>
                                            <a
                                                className='cloud-thumb'
                                                href={file.download_url}
                                                title={`Download ${file.name}`}
                                            >
                                                <Icon name={kind.icon} size={56} color={kind.color} />
                                            </a>
                                            <div className='cloud-card-foot'>
                                                <span>{formatBytes(file.size)}</span>
                                                <span>
                                                    {section === 'shared' && file.owner
                                                        ? file.owner
                                                        : file.share_url
                                                        ? 'Shared'
                                                        : dateOf(file.created_at)}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {rows.length > 0 && view === 'list' && (
                            <div style={{ overflowX: 'auto' }}>
                                <table className='cloud-table'>
                                    <thead>
                                        <tr>
                                            <th className='cloud-eyebrow'>Name</th>
                                            <th className='cloud-eyebrow'>
                                                {section === 'shared' ? 'Owner' : 'Sharing'}
                                            </th>
                                            <th className='cloud-eyebrow'>Added</th>
                                            <th className='cloud-eyebrow'>Size</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((file) => {
                                            const kind = kindOf(file);

                                            return (
                                                <tr key={file.uuid} className={highlight === file.uuid ? 'hl' : ''}>
                                                    <td style={{ maxWidth: 420 }}>
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                alignItems: 'center',
                                                                gap: 10,
                                                                minWidth: 0,
                                                            }}
                                                        >
                                                            <Icon name={kind.icon} size={22} color={kind.color} />
                                                            <span
                                                                style={{
                                                                    fontWeight: 800,
                                                                    overflow: 'hidden',
                                                                    textOverflow: 'ellipsis',
                                                                    whiteSpace: 'nowrap',
                                                                }}
                                                            >
                                                                {file.name}
                                                            </span>
                                                            {file.source === 'anney' && (
                                                                <span
                                                                    className='cloud-badge'
                                                                    style={{ color: '#742220', borderColor: '#C8BCA0' }}
                                                                >
                                                                    Anney
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        {section === 'shared' ? (
                                                            file.owner
                                                        ) : file.share_url ? (
                                                            <span
                                                                className='cloud-badge'
                                                                style={{
                                                                    color: '#166534',
                                                                    borderColor: 'rgba(16,185,129,.45)',
                                                                    background: 'rgba(16,185,129,.12)',
                                                                }}
                                                            >
                                                                <Icon name='link' size={12} /> Link
                                                            </span>
                                                        ) : (
                                                            <span style={{ color: 'rgba(116,34,32,.45)' }}>
                                                                Private
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td>{dateOf(file.created_at)}</td>
                                                    <td>{formatBytes(file.size)}</td>
                                                    <td style={{ width: 90, textAlign: 'right' }}>
                                                        <div style={{ display: 'inline-flex', alignItems: 'center' }}>
                                                            <a
                                                                href={file.download_url}
                                                                className='cloud-icon-btn'
                                                                title='Download'
                                                            >
                                                                <Icon name='download' size={18} />
                                                            </a>
                                                            {kebab(file)}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </section>
            </div>

            {dragging && canUpload && (
                <div className='cloud-drop'>
                    <div>
                        <Icon name='cloud_upload' size={32} /> Drop files to upload
                    </div>
                </div>
            )}

            {uploads.length > 0 && (
                <div className='cloud-uploads cloud-panel'>
                    <div className='cu-head' style={{ borderBottom: uploadsOpen ? '2px solid #2D4A3E' : 'none' }}>
                        <Icon
                            name={activeUploads > 0 ? 'cloud_upload' : 'cloud_done'}
                            size={20}
                            color={activeUploads > 0 ? '#742220' : '#2D6A4F'}
                        />
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div className='cu-title'>
                                {activeUploads > 0
                                    ? `Uploading ${activeUploads} item${activeUploads > 1 ? 's' : ''}`
                                    : 'Uploads complete'}
                            </div>
                            {activeUploads === 0 && <div className='cu-sub'>Closes automatically in 30 seconds</div>}
                        </div>
                        <button
                            type='button'
                            className='cloud-icon-btn'
                            style={{ width: 28, height: 28 }}
                            onClick={() => setUploadsOpen((o) => !o)}
                            aria-label={uploadsOpen ? 'Collapse' : 'Expand'}
                        >
                            <Icon name={uploadsOpen ? 'expand_more' : 'expand_less'} size={18} />
                        </button>
                        {activeUploads === 0 && (
                            <button
                                type='button'
                                className='cloud-icon-btn'
                                style={{ width: 28, height: 28 }}
                                onClick={() => setUploads([])}
                                aria-label='Close'
                            >
                                <Icon name='close' size={18} />
                            </button>
                        )}
                    </div>
                    {uploadsOpen && (
                        <div className='cu-list'>
                            {uploads.map((u) => (
                                <div key={u.id} className='cu-item'>
                                    <span className='cu-file'>
                                        <Icon name='description' size={18} color='#742220' />
                                    </span>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div className='cu-name'>{u.name}</div>
                                        {u.error ? (
                                            <div className='cu-meta' style={{ color: '#A03A2F' }}>
                                                {u.error}
                                            </div>
                                        ) : !u.done ? (
                                            <div className='cu-progress'>
                                                <div className='cloud-meter' style={{ flex: 1, margin: 0 }}>
                                                    <div style={{ width: `${u.progress}%`, background: '#2D4A3E' }} />
                                                </div>
                                                <span>{Math.round(u.progress)}%</span>
                                            </div>
                                        ) : (
                                            <div className='cu-meta'>{formatBytes(u.size)} · Uploaded</div>
                                        )}
                                    </div>
                                    <Icon
                                        name={u.error ? 'error' : u.done ? 'check_circle' : 'sync'}
                                        size={20}
                                        color={u.error ? '#A03A2F' : u.done ? '#2D6A4F' : '#742220'}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default CloudContainer;
