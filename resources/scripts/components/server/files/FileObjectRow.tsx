import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faFileAlt,
    faFileImport,
} from '@fortawesome/free-solid-svg-icons';
import { encodePathSegments } from '@/helpers';
import { differenceInHours, format, formatDistanceToNow } from 'date-fns';
import React, { memo } from 'react';
import { FileObject } from '@/api/server/files/loadDirectory';
import FileDropdownMenu from '@/components/server/files/FileDropdownMenu';
import { ServerContext } from '@/state/server';
import { NavLink, useRouteMatch } from 'react-router-dom';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';
import SelectFileCheckbox from '@/components/server/files/SelectFileCheckbox';
import { usePermissions } from '@/plugins/usePermissions';
import { join } from 'pathe';
import { bytesToString } from '@/lib/formatters';
import styles from './style.module.css';
import {
    DEFAULT_FILE,
    DEFAULT_FOLDER,
    getIconForFile,
    getIconForFolder,
    getIconForOpenFolder,
} from 'vscode-icons-js';

const VSCODE_ICON_BASE = 'https://raw.githubusercontent.com/vscode-icons/vscode-icons/master/icons';

const getVscodeIconName = (file: FileObject): string => {
    if (!file.isFile) return getIconForOpenFolder(file.name) || getIconForFolder(file.name) || DEFAULT_FOLDER;
    return getIconForFile(file.name) || DEFAULT_FILE;
};

const Clickable: React.FC<{ file: FileObject }> = memo(({ file, children }) => {
    const [canRead] = usePermissions(['file.read']);
    const [canReadContents] = usePermissions(['file.read-content']);
    const directory = ServerContext.useStoreState((state) => state.files.directory);

    const match = useRouteMatch();

    return (file.isFile && (!file.isEditable() || !canReadContents)) || (!file.isFile && !canRead) ? (
        <div className={styles.details}>{children}</div>
    ) : (
        <NavLink
            className={styles.details}
            to={`${match.url}${file.isFile ? '/edit' : ''}#${encodePathSegments(join(directory, file.name))}`}
        >
            {children}
        </NavLink>
    );
}, isEqual);

const FileObjectRow = ({ file }: { file: FileObject }) => {
    const iconName = getVscodeIconName(file);
    const iconUrl = `${VSCODE_ICON_BASE}/${iconName}`;

    return (
        <div
            className={`${styles.file_row} group`}
            key={file.name}
            onContextMenu={(e) => {
                e.preventDefault();
                window.dispatchEvent(
                    new CustomEvent(`pterodactyl:files:ctx:${file.key}`, { detail: { x: e.clientX, y: e.clientY } })
                );
            }}
        >
            <SelectFileCheckbox name={file.name} />
            <Clickable file={file}>
                <div
                    css={tw`mr-4 flex-none pl-1 text-lg transition-colors duration-150`}
                >
                    {file.isSymlink ? (
                        <FontAwesomeIcon icon={faFileImport} className={'text-[#22d3ee]'} />
                    ) : (
                        <img
                            src={iconUrl}
                            alt={''}
                            css={tw`h-[2rem] w-[2rem] object-contain`}
                            loading={'lazy'}
                            onError={(event) => {
                                const target = event.currentTarget;
                                target.onerror = null;
                                target.src = `${VSCODE_ICON_BASE}/${file.isFile ? DEFAULT_FILE : DEFAULT_FOLDER}`;
                            }}
                        />
                    )}
                </div>
                <div css={tw`flex-1 truncate`}>{file.name}</div>
                {file.isFile && (
                    <div css={tw`mr-4 hidden w-1/6 text-right text-xs text-gray-400 sm:block`}>
                        {bytesToString(file.size)}
                    </div>
                )}
                <div css={tw`mr-4 hidden w-1/5 text-right text-xs text-gray-400 md:block`} title={file.modifiedAt.toString()}>
                    {Math.abs(differenceInHours(file.modifiedAt, new Date())) > 48
                        ? format(file.modifiedAt, 'MMM do, yyyy h:mma')
                        : formatDistanceToNow(file.modifiedAt, { addSuffix: true })}
                </div>
            </Clickable>
            <FileDropdownMenu file={file} />
        </div>
    );
};

export default memo(FileObjectRow, (prevProps, nextProps) => {
    /* eslint-disable @typescript-eslint/no-unused-vars */
    const { isArchiveType, isEditable, ...prevFile } = prevProps.file;
    const { isArchiveType: nextIsArchiveType, isEditable: nextIsEditable, ...nextFile } = nextProps.file;
    /* eslint-enable @typescript-eslint/no-unused-vars */

    return isEqual(prevFile, nextFile);
});
