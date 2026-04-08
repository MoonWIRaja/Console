import React, { memo, useState } from 'react';
import isEqual from 'react-fast-compare';
import tw from 'twin.macro';
import Modal from '@/components/elements/Modal';
import GreyRowBox from '@/components/elements/GreyRowBox';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Code from '@/components/elements/Code';
import Button from '@/components/elements/Button';
import Can from '@/components/elements/Can';
import { faSitemap, faTrashAlt } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import deleteServerSubdomain from '@/api/server/network/deleteServerSubdomain';
import { NetworkSubdomain } from '@/api/server/network/getServerSubdomains';

interface Props {
    subdomain: NetworkSubdomain;
    onDeleted: () => Promise<unknown>;
}

const Label = ({ children }: { children: React.ReactNode }) => (
    <p css={tw`mt-1 block select-none px-1 text-xs uppercase text-[color:var(--text-subtle)]`}>{children}</p>
);

const SubdomainRow = ({ subdomain, onDeleted }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { addFlash, clearFlashes } = useFlash();
    const [visible, setVisible] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const destroy = async () => {
        clearFlashes('server:network');
        setSubmitting(true);

        try {
            await deleteServerSubdomain(uuid, subdomain.id);
            await onDeleted();

            addFlash({
                key: 'server:network',
                type: 'success',
                title: 'Subdomains',
                message: `Subdomain ${subdomain.fullDomain} deleted successfully.`,
            });

            setVisible(false);
        } catch (error) {
            addFlash({
                key: 'server:network',
                type: 'error',
                title: 'Subdomains',
                message: httpErrorToHuman(error),
            });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <Modal
                visible={visible}
                dismissable={!submitting}
                showSpinnerOverlay={submitting}
                onDismissed={() => setVisible(false)}
            >
                <h2 css={tw`mb-6 text-2xl text-[#f8f6ef]`}>Delete subdomain</h2>
                <p css={tw`text-sm leading-6 text-[color:var(--text-subtle)]`}>
                    This will remove <strong css={tw`text-[#f8f6ef]`}>{subdomain.fullDomain}</strong> from DNS and from
                    this server.
                </p>

                <div css={tw`mt-6 flex flex-wrap justify-end`}>
                    <Button
                        type={'button'}
                        isSecondary
                        disabled={submitting}
                        css={tw`w-full sm:w-auto sm:mr-2`}
                        onClick={() => setVisible(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        css={tw`mt-4 w-full sm:mt-0 sm:w-auto`}
                        type={'button'}
                        color={'red'}
                        disabled={submitting}
                        onClick={destroy}
                    >
                        Delete Subdomain
                    </Button>
                </div>
            </Modal>

            <GreyRowBox $hoverable={false} className={'mt-2 flex-wrap md:flex-nowrap'}>
                <div className={'flex w-full items-center md:w-auto'}>
                    <div className={'pl-4 pr-6 text-[color:var(--text-subtle)]'}>
                        <FontAwesomeIcon icon={faSitemap} />
                    </div>
                    <div className={'mr-4 flex-1 md:w-56'}>
                        <CopyOnClick text={subdomain.fullDomain}>
                            <Code dark className={'w-full truncate'}>
                                {subdomain.fullDomain}
                            </Code>
                        </CopyOnClick>
                        <Label>Hostname</Label>
                    </div>
                    <div className={'w-24 overflow-hidden'}>
                        <Code dark>{subdomain.recordType}</Code>
                        <Label>Record</Label>
                    </div>
                </div>

                <div className={'mt-4 w-full md:mt-0 md:flex-1'}>
                    <div
                        css={tw`rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3`}
                    >
                        <p css={tw`text-sm font-medium text-[#f8f6ef]`}>{subdomain.record.name}</p>
                        <p css={tw`mt-1 text-xs text-[color:var(--text-subtle)]`}>
                            Routes to {subdomain.resolvedTarget ?? 'the server primary allocation'}
                        </p>
                    </div>
                </div>

                <div className={'mt-4 flex w-full justify-end md:mt-0 md:w-24'}>
                    <Can action={'subdomain.delete'}>
                        <Button
                            type={'button'}
                            color={'red'}
                            isSecondary
                            size={'small'}
                            onClick={() => setVisible(true)}
                        >
                            <FontAwesomeIcon icon={faTrashAlt} fixedWidth />
                        </Button>
                    </Can>
                </div>
            </GreyRowBox>
        </>
    );
};

export default memo(SubdomainRow, isEqual);
