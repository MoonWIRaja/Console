import React, { useEffect, useMemo, useState } from 'react';
import Modal from '@/components/elements/Modal';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import createServerSubdomain from '@/api/server/network/createServerSubdomain';
import { NetworkSubdomainTemplate } from '@/api/server/network/getServerSubdomains';

interface Props {
    templates: NetworkSubdomainTemplate[];
    onCreated: () => Promise<unknown>;
}

const hostnamePattern = /^(?!-)[a-z0-9-]{3,63}(?<!-)$/;

const CreateSubdomainButton = ({ templates, onCreated }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { addFlash, clearFlashes } = useFlash();
    const [visible, setVisible] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [hostname, setHostname] = useState('');
    const [recordId, setRecordId] = useState<number>(templates[0]?.id ?? 0);

    useEffect(() => {
        if (!templates.length) {
            setRecordId(0);
            return;
        }

        setRecordId((current) => (templates.some((template) => template.id === current) ? current : templates[0].id));
    }, [templates]);

    const selectedTemplate = useMemo(
        () => templates.find((template) => template.id === recordId) ?? templates[0] ?? null,
        [recordId, templates]
    );

    const preview =
        hostname.trim() && selectedTemplate ? `${hostname.trim().toLowerCase()}.${selectedTemplate.domain.name}` : '';

    const reset = () => {
        setHostname('');
        setVisible(false);
    };

    const submit = async () => {
        const trimmed = hostname.trim().toLowerCase();
        clearFlashes('server:network');

        if (!selectedTemplate) {
            addFlash({
                key: 'server:network',
                type: 'error',
                title: 'Subdomains',
                message: 'No subdomain template is available for this server.',
            });

            return;
        }

        if (!hostnamePattern.test(trimmed)) {
            addFlash({
                key: 'server:network',
                type: 'error',
                title: 'Subdomains',
                message:
                    'Subdomain names must be 3 to 63 characters and contain only lowercase letters, numbers, and dashes.',
            });

            return;
        }

        setSubmitting(true);

        try {
            await createServerSubdomain(uuid, selectedTemplate.id, trimmed);
            await onCreated();

            addFlash({
                key: 'server:network',
                type: 'success',
                title: 'Subdomains',
                message: `Subdomain ${trimmed}.${selectedTemplate.domain.name} created successfully.`,
            });

            reset();
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
                onDismissed={() => {
                    if (!submitting) {
                        reset();
                    }
                }}
            >
                <h2 css={tw`mb-6 text-2xl text-[#f8f6ef]`}>Create subdomain</h2>

                <div css={tw`space-y-5`}>
                    <div>
                        <Label>Subdomain</Label>
                        <Input
                            value={hostname}
                            onChange={(event) => setHostname(event.currentTarget.value)}
                            placeholder={'play'}
                            disabled={submitting}
                        />
                        <p className={'input-help'}>
                            This becomes the left-hand label players will use before the configured base domain.
                        </p>
                    </div>

                    <div>
                        <Label>Template</Label>
                        <Select
                            value={selectedTemplate?.id ?? ''}
                            disabled={submitting || templates.length === 0}
                            onChange={(event) => setRecordId(Number(event.currentTarget.value))}
                        >
                            {templates.map((template) => (
                                <option key={template.id} value={template.id}>
                                    {template.name} • {template.domain.name} • {template.recordType}
                                </option>
                            ))}
                        </Select>
                    </div>

                    {preview !== '' && (
                        <div css={tw`rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4`}>
                            <p css={tw`text-xs uppercase tracking-[0.25em] text-[color:var(--text-subtle)]`}>Preview</p>
                            <p css={tw`mt-2 text-base font-semibold text-[#f8f6ef]`}>{preview}</p>
                        </div>
                    )}
                </div>

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
                        disabled={submitting || !selectedTemplate}
                        onClick={submit}
                    >
                        Create Subdomain
                    </Button>
                </div>
            </Modal>

            <InteractiveHoverButton
                text={'Create Subdomain'}
                variant={'success'}
                onClick={() => setVisible(true)}
                disabled={templates.length === 0}
            />
        </>
    );
};

export default CreateSubdomainButton;
