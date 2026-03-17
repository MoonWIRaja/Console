import React, { useEffect, useMemo, useRef, useState } from 'react';
import { CreditCard, LifeBuoy, Link2, Paperclip, RotateCcw } from 'lucide-react';
import { Link, useHistory, useLocation, useParams } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import Modal from '@/components/elements/Modal';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { ChatBubble, ChatBubbleAvatar, ChatBubbleMessage } from '@/components/ui/chat-bubble';
import { PromptBox } from '@/components/ui/chatgpt-prompt-input';
import { ChatMessageList } from '@/components/ui/chat-message-list';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { joinDiscordCommunity, useDiscordCommunityStatus } from '@/api/account/discordCommunity';
import { useOAuthAccounts } from '@/api/account/oauth';
import {
    createTicket,
    fetchTicketEligibles,
    postTicketMessage,
    reopenTicket,
    TicketDetail,
    TicketEligibilityItem,
    useTicket,
    useTickets,
} from '@/api/account/tickets';

const COMPOSE_DRAFT_STORAGE_KEY = 'billing-ticket-compose-draft';

type RouteParams = {
    ticketId?: string;
};

type TicketComposeCategory = 'payment' | 'refund' | 'support';

type TicketComposeDraft = {
    category: TicketComposeCategory;
};

const composeModalMeta = (category: TicketComposeCategory) => {
    if (category === 'payment') {
        return {
            eyebrow: 'Billing Queue',
            title: 'Open Payment Ticket',
            copy: 'Pick the invoice you want staff to review and continue the payment conversation from one place.',
            Icon: CreditCard,
            iconClass: 'is-payment',
        };
    }

    if (category === 'refund') {
        return {
            eyebrow: 'Refund Queue',
            title: 'Open Refund Ticket',
            copy: 'Choose the payment that needs refund review so billing history stays attached to the ticket.',
            Icon: RotateCcw,
            iconClass: 'is-refund',
        };
    }

    return {
        eyebrow: 'Support Desk',
        title: 'Open Support Ticket',
        copy: 'Describe the issue clearly and the panel will sync the conversation into a private Discord thread.',
        Icon: LifeBuoy,
        iconClass: 'is-support',
    };
};

const formatFileSize = (size: number): string => {
    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const statusColor = (status: string): string => {
    if (status === 'resolved' || status === 'closed') return 'var(--muted-foreground)';
    if (status === 'waiting_for_staff') return '#f0b90b';
    return 'var(--primary)';
};

const messageTone = (authorType: string) => {
    if (authorType === 'admin') {
        return {
            variant: 'received' as const,
            avatarClass: 'border-sky-400/24 bg-sky-500/16 text-sky-100',
            bubbleClass: 'border-sky-400/22 bg-sky-500/10 text-sky-50',
            eyebrow: 'Staff',
        };
    }

    if (authorType === 'system') {
        return {
            variant: 'received' as const,
            avatarClass: 'border-amber-400/24 bg-amber-500/16 text-amber-100',
            bubbleClass: 'border-amber-400/22 bg-amber-500/10 text-amber-50',
            eyebrow: 'System',
        };
    }

    return {
        variant: 'sent' as const,
        avatarClass: 'border-[rgba(var(--primary-rgb),0.24)] bg-[rgba(var(--primary-rgb),0.16)] text-[#efffc8]',
        bubbleClass:
            'border-[rgba(var(--primary-rgb),0.28)] bg-[linear-gradient(135deg,rgba(var(--primary-rgb),0.28),rgba(var(--primary-rgb),0.08))] text-[#f8f6ef]',
        eyebrow: 'You',
    };
};

const withReturnTo = (url: string, returnTo: string): string => {
    try {
        const parsed = new URL(url, window.location.origin);
        parsed.searchParams.set('return_to', returnTo);

        return parsed.toString();
    } catch {
        return url;
    }
};

const readComposeDraft = (): TicketComposeDraft | null => {
    try {
        const raw = window.sessionStorage.getItem(COMPOSE_DRAFT_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as TicketComposeDraft;
        if (!parsed?.category || !['payment', 'refund', 'support'].includes(parsed.category)) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
};

const writeComposeDraft = (draft: TicketComposeDraft | null): void => {
    if (!draft) {
        window.sessionStorage.removeItem(COMPOSE_DRAFT_STORAGE_KEY);
        return;
    }

    window.sessionStorage.setItem(COMPOSE_DRAFT_STORAGE_KEY, JSON.stringify(draft));
};

const BillingTicketsContainer = () => {
    const history = useHistory();
    const location = useLocation();
    const params = useParams<RouteParams>();
    const ticketId = params.ticketId ? Number(params.ticketId) : null;
    const { addFlash } = useFlash();
    const { clearAndAddHttpError, clearFlashes } = useFlashKey('tickets');
    const composeGuardRef = useRef(false);
    const replyFileInputRef = useRef<HTMLInputElement | null>(null);

    const { data: tickets, error: ticketsError, isValidating: ticketsLoading, mutate: mutateTickets } = useTickets();
    const { data: ticket, error: ticketError, isValidating: ticketLoading, mutate: mutateTicket } = useTicket(ticketId);
    const { data: providers } = useOAuthAccounts();
    const { data: community, mutate: mutateCommunity } = useDiscordCommunityStatus();

    const [composeModalVisible, setComposeModalVisible] = useState(false);
    const [composeCategory, setComposeCategory] = useState<TicketComposeCategory>('support');
    const [composeBusy, setComposeBusy] = useState(false);
    const [messageBusy, setMessageBusy] = useState(false);
    const [eligibles, setEligibles] = useState<TicketEligibilityItem[]>([]);
    const [selectedEligibleId, setSelectedEligibleId] = useState<string>('');
    const [supportSubject, setSupportSubject] = useState('');
    const [supportBody, setSupportBody] = useState('');
    const [composeFiles, setComposeFiles] = useState<File[]>([]);
    const [replyBody, setReplyBody] = useState('');
    const [replyFiles, setReplyFiles] = useState<File[]>([]);
    const [linkPromptVisible, setLinkPromptVisible] = useState(false);
    const [statusFilter, setStatusFilter] = useState<'open' | 'resolved' | 'closed' | 'all'>('open');
    const [categoryFilter, setCategoryFilter] = useState<'all' | 'payment' | 'refund' | 'support'>('all');

    useEffect(() => {
        clearAndAddHttpError(ticketsError || ticketError || null);
    }, [ticketsError, ticketError, clearAndAddHttpError]);

    const discordProvider = useMemo(
        () => (providers || []).find((provider) => provider.provider === 'discord') || null,
        [providers]
    );

    useEffect(() => {
        const search = new URLSearchParams(location.search);
        const oauthStatus = search.get('oauth_status');
        if (!oauthStatus) return;

        if (oauthStatus === 'linked') {
            addFlash({
                key: 'tickets',
                type: 'success',
                title: 'Discord Linked',
                message: 'Your Discord account is linked. You can open or continue support tickets now.',
            });
        } else if (oauthStatus !== 'linked') {
            addFlash({
                key: 'tickets',
                type: 'warning',
                title: 'Discord Link',
                message: `Discord link flow ended with status: ${oauthStatus}.`,
            });
        }
    }, [location.search, addFlash]);

    useEffect(() => {
        if (!discordProvider?.linked) {
            return;
        }

        if (new URLSearchParams(location.search).has('compose')) {
            return;
        }

        const draft = readComposeDraft();
        if (!draft) {
            return;
        }

        writeComposeDraft(null);
        // eslint-disable-next-line @typescript-eslint/no-use-before-define
        void openCompose(draft.category);
    }, [discordProvider?.linked, location.search]);

    useEffect(() => {
        if (!ticket?.id) return;

        const source = new EventSource(`/api/client/account/tickets/${ticket.id}/stream`);
        const refresh = () => {
            void mutateTicket();
            void mutateTickets();
        };

        source.addEventListener('sync', refresh);
        source.addEventListener('heartbeat', () => undefined);

        return () => {
            source.removeEventListener('sync', refresh);
            source.close();
        };
    }, [ticket?.id]);

    useEffect(() => {
        if (composeGuardRef.current) return;

        const search = new URLSearchParams(location.search);
        const compose = search.get('compose');
        const invoiceId = search.get('invoiceId');
        const paymentId = search.get('paymentId');

        if (!compose) return;

        if (!discordProvider?.linked) {
            writeComposeDraft({ category: compose === 'refund' ? 'refund' : 'payment' });
            setLinkPromptVisible(true);
            return;
        }

        composeGuardRef.current = true;

        if (compose === 'payment' && invoiceId) {
            // eslint-disable-next-line @typescript-eslint/no-use-before-define
            void handleCreateTicket({
                category: 'payment',
                billing_invoice_id: Number(invoiceId),
                subject: `Payment help for invoice #${invoiceId}`,
            });
        } else if (compose === 'refund' && paymentId) {
            // eslint-disable-next-line @typescript-eslint/no-use-before-define
            void handleCreateTicket({
                category: 'refund',
                billing_payment_id: Number(paymentId),
                subject: `Refund request for payment #${paymentId}`,
            });
        }
    }, [location.search, discordProvider?.linked]);

    async function openCompose(category: TicketComposeCategory) {
        clearFlashes();

        if (!discordProvider?.linked) {
            setComposeCategory(category);
            writeComposeDraft({ category });
            setLinkPromptVisible(true);
            return;
        }

        setComposeCategory(category);
        setSelectedEligibleId('');
        setSupportSubject('');
        setSupportBody('');
        setComposeFiles([]);
        setEligibles([]);

        if (category === 'payment' || category === 'refund') {
            try {
                const data = await fetchTicketEligibles(category);
                setEligibles(data);
                if (data[0]) {
                    setSelectedEligibleId(String(data[0].invoiceId || data[0].paymentId || ''));
                }
            } catch (error) {
                clearAndAddHttpError(error as Error);
                return;
            }
        }

        setComposeModalVisible(true);
    }

    const handleLinkDiscord = () => {
        if (!discordProvider?.linkUrl) return;
        const returnTo = `${location.pathname}${location.search}`;
        window.location.assign(withReturnTo(discordProvider.linkUrl, returnTo));
    };

    async function handleCreateTicket(payload: Record<string, unknown>, attachments: File[] = []) {
        setComposeBusy(true);
        clearFlashes();

        try {
            const created = await createTicket(payload, attachments);
            writeComposeDraft(null);
            await mutateTickets();
            setComposeModalVisible(false);
            history.replace(`/tickets/${created.id}`);
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setComposeBusy(false);
        }
    }

    const submitCompose = async () => {
        if (composeCategory === 'support') {
            const subject = supportSubject.trim();
            const body = supportBody.trim();

            if (body === '' && composeFiles.length === 0) {
                addFlash({
                    key: 'tickets',
                    type: 'error',
                    title: 'Message Required',
                    message: 'Write a message or attach at least one file before opening a support ticket.',
                });
                return;
            }

            await handleCreateTicket(
                {
                    category: 'support',
                    subject: subject || 'Support request',
                    body,
                },
                composeFiles
            );
            return;
        }

        const selected = eligibles.find((item) => String(item.invoiceId || item.paymentId) === selectedEligibleId);
        if (!selected) {
            addFlash({
                key: 'tickets',
                type: 'error',
                title: 'Selection Required',
                message: 'Choose an eligible invoice or payment first.',
            });
            return;
        }

        if (selected.existingTicketId) {
            writeComposeDraft(null);
            setComposeModalVisible(false);
            history.replace(`/tickets/${selected.existingTicketId}`);
            return;
        }

        await handleCreateTicket(
            {
                category: composeCategory,
                subject: selected.subject,
                billing_invoice_id:
                    composeCategory === 'payment' ? selected.invoiceId : selected.invoiceId ?? undefined,
                billing_payment_id: composeCategory === 'refund' ? selected.paymentId : undefined,
                billing_order_id: selected.orderId ?? undefined,
                billing_subscription_id: selected.subscriptionId ?? undefined,
                body:
                    composeCategory === 'payment'
                        ? `Opened from the support center for invoice ${selected.invoiceNumber}.`
                        : `Opened from the support center for payment ${selected.paymentNumber}.`,
            },
            composeFiles
        );
    };

    const submitReply = async () => {
        if (!ticketId) return;
        if (replyBody.trim() === '' && replyFiles.length === 0) return;

        setMessageBusy(true);
        clearFlashes();

        try {
            await postTicketMessage(ticketId, replyBody, replyFiles);
            setReplyBody('');
            setReplyFiles([]);
            await mutateTicket();
            await mutateTickets();
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setMessageBusy(false);
        }
    };

    const handleReopen = async (detail: TicketDetail) => {
        try {
            await reopenTicket(detail.id);
            await mutateTicket();
            await mutateTickets();
        } catch (error) {
            clearAndAddHttpError(error as Error);
        }
    };

    const selectedTicket = ticket || null;
    const canSubmitReply = replyBody.trim() !== '' || replyFiles.length > 0;
    const filteredTickets = (tickets || []).filter((item) => {
        if (categoryFilter !== 'all' && item.category !== categoryFilter) {
            return false;
        }

        if (statusFilter === 'open') {
            return item.status === 'waiting_for_staff' || item.status === 'waiting_for_user';
        }

        if (statusFilter === 'resolved') {
            return item.status === 'resolved';
        }

        if (statusFilter === 'closed') {
            return item.status === 'closed';
        }

        return true;
    });
    const selectedEligible = eligibles.find((item) => String(item.invoiceId || item.paymentId) === selectedEligibleId);
    const composeMeta = composeModalMeta(composeCategory);
    const ComposeIcon = composeMeta.Icon;

    return (
        <div className={'billing-shell min-h-screen px-4 pb-8 pt-6 text-white md:px-8 md:pt-8'}>
            <style>{`
                .billing-shell {
                    position: relative;
                    overflow: hidden;
                    background:
                        radial-gradient(circle at 8% 0%, rgba(var(--primary-rgb), 0.18), transparent 40%),
                        radial-gradient(circle at 94% 100%, rgba(84, 140, 255, 0.2), transparent 44%),
                        linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1));
                }

                .billing-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        repeating-linear-gradient(
                            90deg,
                            rgba(255, 255, 255, 0.014) 0,
                            rgba(255, 255, 255, 0.014) 1px,
                            transparent 1px,
                            transparent 40px
                        );
                    opacity: 0.2;
                }

                .billing-shell::after {
                    content: '';
                    position: absolute;
                    left: 50%;
                    top: -18%;
                    width: min(1120px, 96vw);
                    height: 110%;
                    transform: translateX(-50%);
                    pointer-events: none;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(112, 168, 255, 0.08) 0%,
                        rgba(112, 168, 255, 0.03) 42%,
                        transparent 72%
                    );
                }

                .billing-wrap {
                    position: relative;
                    z-index: 2;
                    width: 100%;
                }

                .billing-panel,
                .billing-hero {
                    border-radius: 24px;
                    border: 1px solid rgba(255, 255, 255, 0.09);
                    background:
                        linear-gradient(160deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.012) 46%),
                        rgba(4, 8, 14, 0.76);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.08),
                        0 30px 46px -32px rgba(0, 0, 0, 0.82),
                        0 0 56px rgba(var(--primary-rgb), 0.1);
                    backdrop-filter: blur(8px);
                }

                .billing-hero {
                    margin-bottom: 1.5rem;
                    padding: 1.25rem 1.5rem;
                }

                .billing-hero-pill-row {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    margin-bottom: 12px;
                }

                .billing-hero-pill {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 34px;
                    border-radius: 999px;
                    border: 1px solid rgba(var(--primary-rgb), 0.52);
                    background: linear-gradient(120deg, rgba(var(--primary-rgb), 0.36), rgba(var(--primary-rgb), 0.14));
                    color: rgba(230, 252, 180, 0.95);
                    font-size: 0.7rem;
                    font-weight: 800;
                    letter-spacing: 0.2em;
                    text-transform: uppercase;
                    padding: 0 14px;
                    text-shadow: 0 0 10px rgba(var(--primary-rgb), 0.58);
                }

                .billing-hero-route {
                    color: rgba(248, 246, 239, 0.56);
                    font-size: 0.7rem;
                    letter-spacing: 0.18em;
                    text-transform: uppercase;
                    font-weight: 700;
                }

                .billing-hero-title {
                    margin: 0;
                    font-size: clamp(1.5rem, 3.2vw, 2.3rem);
                    line-height: 1.04;
                    letter-spacing: 0.02em;
                    text-transform: uppercase;
                    font-weight: 900;
                    color: rgba(248, 246, 239, 0.97);
                    text-shadow: 0 0 18px rgba(248, 246, 239, 0.19);
                }

                .ticket-modal {
                    position: relative;
                    overflow: hidden;
                }

                .ticket-modal::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        radial-gradient(circle at top right, rgba(var(--primary-rgb), 0.13), transparent 34%),
                        linear-gradient(180deg, rgba(255, 255, 255, 0.02), transparent 38%);
                    opacity: 0.9;
                }

                .ticket-modal-header,
                .ticket-modal-field,
                .ticket-modal-summary,
                .ticket-modal-note,
                .ticket-modal-upload,
                .ticket-modal-footer {
                    position: relative;
                    z-index: 1;
                }

                .ticket-modal-header {
                    display: flex;
                    gap: 1rem;
                    align-items: flex-start;
                    padding-bottom: 1rem;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                }

                .ticket-modal-icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 3.25rem;
                    height: 3.25rem;
                    border-radius: 18px;
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    background: rgba(255, 255, 255, 0.04);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
                    color: rgba(248, 246, 239, 0.94);
                    flex-shrink: 0;
                }

                .ticket-modal-icon.is-payment {
                    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(34, 197, 94, 0.06));
                    border-color: rgba(74, 222, 128, 0.26);
                    color: #bbf7d0;
                }

                .ticket-modal-icon.is-refund {
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.22), rgba(245, 158, 11, 0.06));
                    border-color: rgba(251, 191, 36, 0.24);
                    color: #fde68a;
                }

                .ticket-modal-icon.is-support {
                    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.24), rgba(var(--primary-rgb), 0.06));
                    border-color: rgba(var(--primary-rgb), 0.24);
                    color: rgba(230, 252, 180, 0.96);
                }

                .ticket-modal-eyebrow {
                    color: rgba(248, 246, 239, 0.54);
                    font-size: 0.68rem;
                    font-weight: 800;
                    letter-spacing: 0.18em;
                    text-transform: uppercase;
                }

                .ticket-modal-title {
                    margin: 0.45rem 0 0;
                    font-size: clamp(1.2rem, 2.4vw, 1.7rem);
                    line-height: 1.08;
                    font-weight: 900;
                    color: rgba(248, 246, 239, 0.98);
                }

                .ticket-modal-copy {
                    margin-top: 0.65rem;
                    color: rgba(248, 246, 239, 0.68);
                    font-size: 0.92rem;
                    line-height: 1.7;
                    max-width: 48ch;
                }

                .ticket-modal-body {
                    margin-top: 1.1rem;
                    display: grid;
                    gap: 1rem;
                }

                .ticket-modal-field {
                    display: grid;
                    gap: 0.55rem;
                }

                .ticket-modal-label {
                    color: rgba(248, 246, 239, 0.62);
                    font-size: 0.7rem;
                    font-weight: 800;
                    letter-spacing: 0.16em;
                    text-transform: uppercase;
                }

                .ticket-modal-input,
                .ticket-modal-select,
                .ticket-modal-textarea {
                    width: 100%;
                    border-radius: 18px;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    background: rgba(3, 8, 14, 0.78);
                    color: rgba(248, 246, 239, 0.98);
                    padding: 0.95rem 1rem;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
                    transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
                }

                .ticket-modal-input::placeholder,
                .ticket-modal-textarea::placeholder {
                    color: rgba(248, 246, 239, 0.34);
                }

                .ticket-modal-input:focus,
                .ticket-modal-select:focus,
                .ticket-modal-textarea:focus {
                    outline: none;
                    border-color: rgba(var(--primary-rgb), 0.42);
                    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.12);
                    background: rgba(5, 11, 18, 0.92);
                }

                .ticket-modal-textarea {
                    min-height: 10rem;
                    resize: vertical;
                }

                .ticket-modal-summary,
                .ticket-modal-note,
                .ticket-modal-upload {
                    border-radius: 20px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background: rgba(255, 255, 255, 0.03);
                    padding: 1rem;
                }

                .ticket-modal-summary {
                    display: grid;
                    gap: 0.4rem;
                }

                .ticket-modal-summary-title {
                    color: rgba(248, 246, 239, 0.98);
                    font-size: 0.98rem;
                    font-weight: 800;
                }

                .ticket-modal-summary-meta {
                    color: rgba(248, 246, 239, 0.62);
                    font-size: 0.84rem;
                    line-height: 1.7;
                }

                .ticket-modal-note {
                    color: rgba(248, 246, 239, 0.74);
                    font-size: 0.88rem;
                    line-height: 1.7;
                }

                .ticket-modal-note.is-warning {
                    border-color: rgba(245, 158, 11, 0.22);
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.04));
                    color: #fde68a;
                }

                .ticket-modal-upload-head {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.75rem;
                    margin-bottom: 0.85rem;
                }

                .ticket-modal-upload-title {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.55rem;
                    color: rgba(248, 246, 239, 0.96);
                    font-size: 0.92rem;
                    font-weight: 800;
                }

                .ticket-modal-upload-help {
                    color: rgba(248, 246, 239, 0.46);
                    font-size: 0.75rem;
                    font-weight: 700;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                }

                .ticket-modal-file-input {
                    display: block;
                    width: 100%;
                    color: rgba(248, 246, 239, 0.7);
                    font-size: 0.88rem;
                }

                .ticket-modal-files {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.6rem;
                    margin-top: 0.9rem;
                }

                .ticket-modal-file-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.45rem;
                    border-radius: 999px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background: rgba(255, 255, 255, 0.04);
                    padding: 0.45rem 0.8rem;
                    color: rgba(248, 246, 239, 0.82);
                    font-size: 0.78rem;
                }

                .ticket-modal-file-size {
                    color: rgba(248, 246, 239, 0.42);
                }

                .ticket-modal-footer {
                    margin-top: 1.15rem;
                    display: flex;
                    flex-direction: column-reverse;
                    gap: 0.75rem;
                    align-items: stretch;
                }

                .ticket-modal-secondary {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 2.75rem;
                    border-radius: 999px;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    background: rgba(255, 255, 255, 0.04);
                    padding: 0.75rem 1.25rem;
                    color: rgba(248, 246, 239, 0.88);
                    font-size: 0.9rem;
                    font-weight: 700;
                    transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
                }

                .ticket-modal-secondary:hover,
                .ticket-modal-secondary:focus-visible {
                    border-color: rgba(var(--primary-rgb), 0.32);
                    background: rgba(255, 255, 255, 0.06);
                    transform: translateY(-1px);
                    outline: none;
                }

                @media (min-width: 640px) {
                    .ticket-modal-footer {
                        flex-direction: row;
                        justify-content: flex-end;
                        align-items: center;
                    }
                }
            `}</style>
            <div className={'billing-wrap'}>
                <FlashMessageRender byKey={'tickets'} />

                <div className={'billing-hero'}>
                    <div className={'billing-hero-pill-row'}>
                        <span className={'billing-hero-pill'}>Support Center</span>
                        <span className={'billing-hero-route'}>Route /tickets</span>
                    </div>
                    <div className={'flex flex-col gap-4 md:flex-row md:items-end md:justify-between'}>
                        <div>
                            <h1 className={'billing-hero-title'}>Support Tickets</h1>
                            <p className={'mt-3 max-w-3xl text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                Open private support tickets that sync with Discord threads. Payment, refund, and
                                general support conversations stay in one place, with invoice context and staff replies
                                tied directly to your account history.
                            </p>
                        </div>
                        <div className={'flex w-full flex-wrap gap-3 md:w-auto md:justify-end'}>
                            <InteractiveHoverButton
                                className={'w-full normal-case tracking-normal sm:w-auto'}
                                onClick={() => void openCompose('payment')}
                                text={'Open Payment Ticket'}
                                type={'button'}
                                variant={'success'}
                            />
                            <InteractiveHoverButton
                                className={'w-full normal-case tracking-normal sm:w-auto'}
                                onClick={() => void openCompose('refund')}
                                text={'Open Refund Ticket'}
                                type={'button'}
                                variant={'warning'}
                            />
                            <InteractiveHoverButton
                                className={'w-full normal-case tracking-normal sm:w-auto'}
                                onClick={() => void openCompose('support')}
                                text={'Open Support Ticket'}
                                type={'button'}
                            />
                        </div>
                    </div>
                </div>

                {!discordProvider?.linked ? (
                    <section className={'billing-panel mt-6 p-6'}>
                        <h2 className={'text-xl font-black text-[#f8f6ef]'}>Discord Link Required</h2>
                        <p className={'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                            Tickets are tied to your Discord identity. Link your Discord account before opening a
                            support ticket.
                        </p>
                        <div className={'mt-5'}>
                            <button className={'btn btn-primary'} onClick={handleLinkDiscord} type={'button'}>
                                Link Discord
                            </button>
                        </div>
                    </section>
                ) : community && !community.member ? (
                    <section className={'billing-panel mt-6 p-6'}>
                        <h2 className={'text-xl font-black text-[#f8f6ef]'}>Join Discord Server</h2>
                        <p className={'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                            Your Discord account is linked, but it is not a member of the configured Discord server yet.
                            Join now so private ticket threads can be created properly.
                        </p>
                        <div className={'mt-5'}>
                            <button
                                className={'btn btn-primary'}
                                onClick={async () => {
                                    const result = await joinDiscordCommunity();
                                    if (!result.success) {
                                        clearAndAddHttpError(result.error || 'Failed to join the Discord community.');
                                        return;
                                    }

                                    await mutateCommunity();
                                    addFlash({
                                        key: 'tickets',
                                        type: 'success',
                                        title: 'Discord Joined',
                                        message: 'Your Discord account has been added to the configured community.',
                                    });
                                }}
                                type={'button'}
                            >
                                Join Discord Community
                            </button>
                        </div>
                    </section>
                ) : null}

                <div className={'mt-6 grid gap-6 xl:grid-cols-[360px,minmax(0,1fr)]'}>
                    <aside className={'billing-panel p-4'}>
                        <div className={'mb-4 flex items-center justify-between'}>
                            <h2 className={'text-lg font-black text-[#f8f6ef]'}>My Support Tickets</h2>
                            {ticketsLoading && !tickets ? <Spinner size={Spinner.Size.SMALL} /> : null}
                        </div>

                        <div className={'mb-4 grid gap-3 md:grid-cols-2'}>
                            <select
                                className={
                                    'w-full rounded-2xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[#f8f6ef]'
                                }
                                onChange={(event) => setStatusFilter(event.currentTarget.value as typeof statusFilter)}
                                value={statusFilter}
                            >
                                <option value={'open'}>Open</option>
                                <option value={'resolved'}>Resolved</option>
                                <option value={'closed'}>Closed</option>
                                <option value={'all'}>All Statuses</option>
                            </select>
                            <select
                                className={
                                    'w-full rounded-2xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[#f8f6ef]'
                                }
                                onChange={(event) =>
                                    setCategoryFilter(event.currentTarget.value as typeof categoryFilter)
                                }
                                value={categoryFilter}
                            >
                                <option value={'all'}>All Categories</option>
                                <option value={'payment'}>Payment</option>
                                <option value={'refund'}>Refund</option>
                                <option value={'support'}>Support</option>
                            </select>
                        </div>

                        <div className={'space-y-3'}>
                            {filteredTickets.map((item) => (
                                <Link
                                    key={item.id}
                                    to={`/tickets/${item.id}`}
                                    className={`block rounded-2xl border p-4 transition-colors ${
                                        item.id === ticketId
                                            ? 'border-[color:var(--primary)] bg-[color:var(--accent)]'
                                            : 'border-[color:var(--border)] bg-[color:var(--card)] hover:border-[color:var(--primary)]/50'
                                    }`}
                                >
                                    <div className={'flex items-start justify-between gap-3'}>
                                        <div>
                                            <div
                                                className={
                                                    'text-xs font-black uppercase tracking-[0.2em] text-[color:var(--primary)]'
                                                }
                                            >
                                                {item.ticketNumber}
                                            </div>
                                            <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>
                                                {item.subject}
                                            </div>
                                        </div>
                                        {item.unread ? (
                                            <span
                                                className={
                                                    'inline-flex h-2.5 w-2.5 rounded-full bg-[color:var(--primary)]'
                                                }
                                            />
                                        ) : null}
                                    </div>
                                    <div
                                        className={
                                            'mt-3 flex flex-wrap items-center gap-2 text-xs text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        <span style={{ color: statusColor(item.status) }}>{item.status}</span>
                                        <span>•</span>
                                        <span>{item.category}</span>
                                        {item.serverName ? (
                                            <>
                                                <span>•</span>
                                                <span>{item.serverName}</span>
                                            </>
                                        ) : null}
                                    </div>
                                </Link>
                            ))}

                            {!ticketsLoading && tickets && filteredTickets.length === 0 ? (
                                <div
                                    className={
                                        'rounded-2xl border border-dashed border-[color:var(--border)] p-6 text-sm text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    No support tickets match the current filters.
                                </div>
                            ) : null}
                        </div>
                    </aside>

                    <section className={'billing-panel p-6'}>
                        {ticketLoading && !selectedTicket ? (
                            <Spinner centered />
                        ) : selectedTicket ? (
                            <>
                                <div
                                    className={
                                        'flex flex-col gap-4 border-b border-[color:var(--border)] pb-5 md:flex-row md:items-start md:justify-between'
                                    }
                                >
                                    <div>
                                        <div
                                            className={
                                                'text-xs font-black uppercase tracking-[0.25em] text-[color:var(--primary)]'
                                            }
                                        >
                                            {selectedTicket.ticketNumber}
                                        </div>
                                        <h2 className={'mt-2 text-2xl font-black tracking-tight text-[#f8f6ef]'}>
                                            {selectedTicket.subject}
                                        </h2>
                                        <div
                                            className={
                                                'mt-3 flex flex-wrap gap-3 text-sm text-[color:var(--muted-foreground)]'
                                            }
                                        >
                                            <span>
                                                Status:{' '}
                                                <strong style={{ color: statusColor(selectedTicket.status) }}>
                                                    {selectedTicket.status}
                                                </strong>
                                            </span>
                                            {selectedTicket.invoiceNumber ? (
                                                <span>Invoice: {selectedTicket.invoiceNumber}</span>
                                            ) : null}
                                            {selectedTicket.paymentNumber ? (
                                                <span>Payment: {selectedTicket.paymentNumber}</span>
                                            ) : null}
                                            {selectedTicket.serverName ? (
                                                <span>Server: {selectedTicket.serverName}</span>
                                            ) : null}
                                        </div>
                                        {selectedTicket.discordLastError ? (
                                            <p className={'mt-3 text-sm text-amber-200'}>
                                                Discord sync issue: {selectedTicket.discordLastError}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className={'flex flex-wrap gap-3'}>
                                        {selectedTicket.discordThreadUrl ? (
                                            <InteractiveHoverButton
                                                className={'w-full normal-case tracking-normal sm:w-auto'}
                                                onClick={() =>
                                                    window.open(
                                                        selectedTicket.discordThreadUrl || '',
                                                        '_blank',
                                                        'noopener,noreferrer'
                                                    )
                                                }
                                                text={'Open Discord Thread'}
                                                type={'button'}
                                            />
                                        ) : null}
                                        {selectedTicket.status === 'resolved' || selectedTicket.status === 'closed' ? (
                                            <button
                                                className={'btn btn-default'}
                                                onClick={() => void handleReopen(selectedTicket)}
                                                type={'button'}
                                            >
                                                Reopen Ticket
                                            </button>
                                        ) : null}
                                    </div>
                                </div>

                                <div
                                    className={
                                        'mt-6 overflow-hidden rounded-[28px] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.035),rgba(255,255,255,0.02))] shadow-[0_34px_80px_-48px_rgba(0,0,0,0.9)]'
                                    }
                                >
                                    <div
                                        className={
                                            'h-[32rem] bg-[radial-gradient(circle_at_top,rgba(var(--primary-rgb),0.08),transparent_34%),linear-gradient(180deg,rgba(4,8,14,0.96),rgba(2,4,8,0.96))] md:h-[40rem]'
                                        }
                                    >
                                        <ChatMessageList smooth>
                                            {selectedTicket.messages.length > 0 ? (
                                                selectedTicket.messages.map((message) => {
                                                    const tone = messageTone(message.authorType);
                                                    const displayName =
                                                        message.authorDisplayName || tone.eyebrow || message.authorType;

                                                    return (
                                                        <ChatBubble
                                                            key={message.id}
                                                            variant={tone.variant}
                                                            className={'items-start'}
                                                        >
                                                            <ChatBubbleAvatar
                                                                className={tone.avatarClass}
                                                                fallback={displayName}
                                                                src={message.authorAvatarUrl}
                                                            />
                                                            <div
                                                                className={`flex max-w-[min(84%,46rem)] flex-col gap-2 ${
                                                                    tone.variant === 'sent'
                                                                        ? 'items-end'
                                                                        : 'items-start'
                                                                }`}
                                                            >
                                                                <div
                                                                    className={`flex flex-wrap items-center gap-2 px-1 text-[11px] font-bold uppercase tracking-[0.18em] text-[color:var(--muted-foreground)] ${
                                                                        tone.variant === 'sent'
                                                                            ? 'justify-end text-right'
                                                                            : 'justify-start'
                                                                    }`}
                                                                >
                                                                    <span>{displayName}</span>
                                                                    <span className={'opacity-40'}>•</span>
                                                                    <span
                                                                        className={
                                                                            'text-[10px] font-semibold tracking-[0.12em]'
                                                                        }
                                                                    >
                                                                        {message.createdAt?.toLocaleString() || 'Now'}
                                                                    </span>
                                                                </div>
                                                                <ChatBubbleMessage
                                                                    className={tone.bubbleClass}
                                                                    variant={tone.variant}
                                                                >
                                                                    {message.body ? (
                                                                        <p
                                                                            className={
                                                                                'whitespace-pre-wrap text-sm leading-7'
                                                                            }
                                                                        >
                                                                            {message.body}
                                                                        </p>
                                                                    ) : (
                                                                        <p className={'text-sm italic opacity-80'}>
                                                                            Attachment only message
                                                                        </p>
                                                                    )}

                                                                    {message.attachments.length > 0 ? (
                                                                        <div className={'mt-4 flex flex-wrap gap-2'}>
                                                                            {message.attachments.map((attachment) => (
                                                                                <a
                                                                                    key={attachment.id}
                                                                                    className={
                                                                                        'inline-flex items-center gap-2 rounded-full border border-white/10 bg-[rgba(255,255,255,0.08)] px-3 py-1.5 text-xs font-semibold text-[#f8f6ef] transition hover:border-[rgba(var(--primary-rgb),0.28)] hover:bg-[rgba(var(--primary-rgb),0.12)]'
                                                                                    }
                                                                                    href={attachment.downloadUrl}
                                                                                    rel={'noreferrer'}
                                                                                    target={'_blank'}
                                                                                >
                                                                                    <Paperclip size={12} />
                                                                                    <span>{attachment.name}</span>
                                                                                    <span
                                                                                        className={
                                                                                            'text-[color:var(--muted-foreground)]'
                                                                                        }
                                                                                    >
                                                                                        {formatFileSize(
                                                                                            attachment.sizeBytes
                                                                                        )}
                                                                                    </span>
                                                                                </a>
                                                                            ))}
                                                                        </div>
                                                                    ) : null}
                                                                </ChatBubbleMessage>
                                                            </div>
                                                        </ChatBubble>
                                                    );
                                                })
                                            ) : (
                                                <div
                                                    className={
                                                        'flex h-full items-center justify-center rounded-[24px] border border-dashed border-white/10 bg-[rgba(255,255,255,0.02)] px-6 text-center text-sm text-[color:var(--muted-foreground)]'
                                                    }
                                                >
                                                    No replies yet. Start the conversation from the reply box below.
                                                </div>
                                            )}
                                        </ChatMessageList>
                                    </div>

                                    <div
                                        className={
                                            'border-t border-white/8 bg-[linear-gradient(180deg,rgba(255,255,255,0.03),rgba(255,255,255,0.015))] p-4 sm:p-5'
                                        }
                                    >
                                        {selectedTicket.status === 'resolved' || selectedTicket.status === 'closed' ? (
                                            <div className={'mb-4 flex justify-end'}>
                                                <span
                                                    className={
                                                        'rounded-full border border-amber-400/24 bg-amber-500/12 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-100'
                                                    }
                                                >
                                                    Reopen to reply
                                                </span>
                                            </div>
                                        ) : null}

                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                void submitReply();
                                            }}
                                        >
                                            <input
                                                hidden
                                                multiple
                                                ref={replyFileInputRef}
                                                type={'file'}
                                                onChange={(event) => {
                                                    const selectedFiles = Array.from(event.currentTarget.files || []);
                                                    if (selectedFiles.length < 1) {
                                                        return;
                                                    }

                                                    setReplyFiles((current) => [...current, ...selectedFiles]);
                                                    event.currentTarget.value = '';
                                                }}
                                            />
                                            <PromptBox
                                                value={replyBody}
                                                onChange={(event) => setReplyBody(event.currentTarget.value)}
                                                placeholder={'Write your message here...'}
                                                attachments={replyFiles.map((file, index) => ({
                                                    id: `${file.name}-${file.size}-${index}`,
                                                    name: file.name,
                                                    meta: formatFileSize(file.size),
                                                }))}
                                                onAttachClick={() => replyFileInputRef.current?.click()}
                                                onRemoveAttachment={(attachmentId) =>
                                                    setReplyFiles((current) =>
                                                        current.filter(
                                                            (file, index) =>
                                                                `${file.name}-${file.size}-${index}` !== attachmentId
                                                        )
                                                    )
                                                }
                                                statusLabel={messageBusy ? 'Sending Reply' : 'Discord Sync'}
                                                submitDisabled={messageBusy || !canSubmitReply}
                                            />
                                        </form>
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div
                                className={
                                    'flex min-h-[360px] items-center justify-center text-sm text-[color:var(--muted-foreground)]'
                                }
                            >
                                Select a ticket from the left to view the conversation.
                            </div>
                        )}
                    </section>
                </div>

                <Modal visible={composeModalVisible} onDismissed={() => setComposeModalVisible(false)}>
                    <div className={'ticket-modal'}>
                        <div className={'ticket-modal-header'}>
                            <div className={`ticket-modal-icon ${composeMeta.iconClass}`}>
                                <ComposeIcon size={18} />
                            </div>
                            <div>
                                <div className={'ticket-modal-eyebrow'}>{composeMeta.eyebrow}</div>
                                <h2 className={'ticket-modal-title'}>{composeMeta.title}</h2>
                                <p className={'ticket-modal-copy'}>{composeMeta.copy}</p>
                            </div>
                        </div>

                        <div className={'ticket-modal-body'}>
                            {composeCategory === 'support' ? (
                                <>
                                    <div className={'ticket-modal-field'}>
                                        <label className={'ticket-modal-label'}>Subject</label>
                                        <input
                                            className={'ticket-modal-input'}
                                            placeholder={'Support request'}
                                            value={supportSubject}
                                            onChange={(event) => setSupportSubject(event.currentTarget.value)}
                                        />
                                    </div>
                                    <div className={'ticket-modal-field'}>
                                        <label className={'ticket-modal-label'}>Message</label>
                                        <textarea
                                            className={'ticket-modal-textarea'}
                                            placeholder={'Tell us what you need help with'}
                                            value={supportBody}
                                            onChange={(event) => setSupportBody(event.currentTarget.value)}
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className={'ticket-modal-field'}>
                                        <label className={'ticket-modal-label'}>
                                            {composeCategory === 'payment' ? 'Invoice' : 'Payment'}
                                        </label>
                                        <select
                                            className={'ticket-modal-select'}
                                            value={selectedEligibleId}
                                            onChange={(event) => setSelectedEligibleId(event.currentTarget.value)}
                                        >
                                            {eligibles.map((item) => (
                                                <option
                                                    key={item.invoiceId || item.paymentId}
                                                    value={item.invoiceId || item.paymentId}
                                                >
                                                    {item.invoiceNumber || item.paymentNumber} - {item.currency}{' '}
                                                    {item.amount.toFixed(2)}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {selectedEligible ? (
                                        <div className={'ticket-modal-summary'}>
                                            <div className={'ticket-modal-summary-title'}>
                                                {selectedEligible.subject}
                                            </div>
                                            <div className={'ticket-modal-summary-meta'}>
                                                {selectedEligible.invoiceNumber
                                                    ? `Invoice ${selectedEligible.invoiceNumber}`
                                                    : `Payment ${selectedEligible.paymentNumber}`}
                                                {' • '}
                                                {selectedEligible.currency} {selectedEligible.amount.toFixed(2)}
                                                {selectedEligible.serverName ? ` • ${selectedEligible.serverName}` : ''}
                                            </div>
                                        </div>
                                    ) : null}

                                    {eligibles.length === 0 ? (
                                        <div className={'ticket-modal-note'}>
                                            No eligible {composeCategory === 'payment' ? 'invoices' : 'payments'} are
                                            available for this action right now.
                                        </div>
                                    ) : null}

                                    {selectedEligible?.existingTicketId ? (
                                        <div className={'ticket-modal-note is-warning'}>
                                            An active ticket already exists for this item. Continuing will reopen the
                                            existing conversation instead of creating a duplicate thread.
                                        </div>
                                    ) : null}
                                </>
                            )}

                            <div className={'ticket-modal-upload'}>
                                <div className={'ticket-modal-upload-head'}>
                                    <div className={'ticket-modal-upload-title'}>
                                        <Paperclip size={16} />
                                        <span>Attachments</span>
                                    </div>
                                    <span className={'ticket-modal-upload-help'}>Optional</span>
                                </div>
                                <input
                                    className={'ticket-modal-file-input'}
                                    multiple
                                    type={'file'}
                                    onChange={(event) => setComposeFiles(Array.from(event.currentTarget.files || []))}
                                />
                                {composeFiles.length > 0 ? (
                                    <div className={'ticket-modal-files'}>
                                        {composeFiles.map((file) => (
                                            <span
                                                key={`${file.name}-${file.size}`}
                                                className={'ticket-modal-file-chip'}
                                            >
                                                <span>{file.name}</span>
                                                <span className={'ticket-modal-file-size'}>
                                                    {formatFileSize(file.size)}
                                                </span>
                                            </span>
                                        ))}
                                    </div>
                                ) : (
                                    <div className={'ticket-modal-summary-meta'}>
                                        Add screenshots, invoices, receipts, or any file that helps staff review faster.
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className={'ticket-modal-footer'}>
                            <button
                                className={'ticket-modal-secondary'}
                                onClick={() => setComposeModalVisible(false)}
                                type={'button'}
                            >
                                Cancel
                            </button>
                            <InteractiveHoverButton
                                className={'w-full normal-case tracking-normal sm:w-auto'}
                                disabled={composeBusy}
                                onClick={() => void submitCompose()}
                                text={
                                    composeBusy
                                        ? 'Opening...'
                                        : selectedEligible?.existingTicketId
                                        ? 'Continue Ticket'
                                        : 'Open Ticket'
                                }
                                type={'button'}
                                variant={
                                    composeCategory === 'payment'
                                        ? 'success'
                                        : composeCategory === 'refund'
                                        ? 'warning'
                                        : 'neutral'
                                }
                            />
                        </div>
                    </div>
                </Modal>

                <Modal visible={linkPromptVisible} onDismissed={() => setLinkPromptVisible(false)}>
                    <div className={'ticket-modal'}>
                        <div className={'ticket-modal-header'}>
                            <div className={'ticket-modal-icon is-support'}>
                                <Link2 size={18} />
                            </div>
                            <div>
                                <div className={'ticket-modal-eyebrow'}>Discord Required</div>
                                <h2 className={'ticket-modal-title'}>Link Discord First</h2>
                                <p className={'ticket-modal-copy'}>
                                    Support tickets sync into private Discord threads. Connect your Discord account
                                    first so staff can continue the workflow with the right identity.
                                </p>
                            </div>
                        </div>

                        <div className={'ticket-modal-body'}>
                            <div className={'ticket-modal-note'}>
                                Once linked, the panel will return you here and you can continue opening the ticket
                                without restarting the whole flow.
                            </div>
                        </div>

                        <div className={'ticket-modal-footer'}>
                            <button
                                className={'ticket-modal-secondary'}
                                onClick={() => setLinkPromptVisible(false)}
                                type={'button'}
                            >
                                Close
                            </button>
                            <InteractiveHoverButton
                                className={'w-full normal-case tracking-normal sm:w-auto'}
                                onClick={handleLinkDiscord}
                                text={'Link Discord'}
                                type={'button'}
                            />
                        </div>
                    </div>
                </Modal>
            </div>
        </div>
    );
};

export default BillingTicketsContainer;
