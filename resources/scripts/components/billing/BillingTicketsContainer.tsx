import React, { useEffect, useMemo, useRef, useState } from 'react';
import { CreditCard, LifeBuoy, Link2, Paperclip, RotateCcw } from 'lucide-react';
import { Link, useHistory, useLocation, useParams } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
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
type TicketSupportView = 'tickets' | 'chat';
type TicketWizardStep = 'category' | 'details' | 'message' | 'review';

type TicketComposeDraft = {
    category: TicketComposeCategory;
    selectedEligibleId?: string;
    subject?: string;
    body?: string;
    step?: TicketWizardStep;
};

const TICKET_CATEGORY_ORDER: TicketComposeCategory[] = ['payment', 'refund', 'support'];
const TICKET_WIZARD_STEP_ORDER: TicketWizardStep[] = ['category', 'details', 'message', 'review'];
const TICKET_WIZARD_STEP_CONTENT: Record<
    TicketWizardStep,
    {
        eyebrow: string;
        title: string;
        copy: string;
    }
> = {
    category: {
        eyebrow: 'Step 1',
        title: 'Choose Ticket Type',
        copy: 'Start with the queue that matches what you need so the panel can preload the right billing or server context.',
    },
    details: {
        eyebrow: 'Step 2',
        title: 'Select Linked Item',
        copy: 'Choose the invoice, payment, or server that this ticket should stay attached to after it moves into chat.',
    },
    message: {
        eyebrow: 'Step 3',
        title: 'Write Your Request',
        copy: 'Add the message and any files staff need before the ticket is opened in the chat workspace.',
    },
    review: {
        eyebrow: 'Step 4',
        title: 'Review & Open Ticket',
        copy: 'Check the full ticket summary before the panel creates the thread and redirects you into chat.',
    },
};

const composeCategoryMeta = (category: TicketComposeCategory) => {
    if (category === 'payment') {
        return {
            eyebrow: 'Billing Queue',
            title: 'Payment Ticket',
            copy: 'Open a payment conversation tied directly to a specific invoice.',
            actionCopy: 'Choose the invoice you need help paying or confirming.',
            emptyCopy: 'No eligible invoices are available for payment tickets right now.',
            Icon: CreditCard,
            iconClass: 'is-payment',
        };
    }

    if (category === 'refund') {
        return {
            eyebrow: 'Refund Queue',
            title: 'Refund Ticket',
            copy: 'Start a refund review that stays linked to the payment record.',
            actionCopy: 'Choose the payment that should go through refund review.',
            emptyCopy: 'No eligible payments are available for refund tickets right now.',
            Icon: RotateCcw,
            iconClass: 'is-refund',
        };
    }

    return {
        eyebrow: 'Support Desk',
        title: 'Support Ticket',
        copy: 'Open a general or server-linked support request that syncs into Discord.',
        actionCopy: 'Choose a server or keep it as a general support request.',
        emptyCopy: 'No support targets are available right now.',
        Icon: LifeBuoy,
        iconClass: 'is-support',
    };
};

const isComposeCategory = (value: string | null): value is TicketComposeCategory =>
    !!value && TICKET_CATEGORY_ORDER.includes(value as TicketComposeCategory);

const isSupportView = (value: string | null): value is TicketSupportView => value === 'tickets' || value === 'chat';

const isWizardStep = (value: string | null): value is TicketWizardStep =>
    !!value && TICKET_WIZARD_STEP_ORDER.includes(value as TicketWizardStep);

const getTicketBasePath = (pathname: string): string =>
    pathname.startsWith('/billing/tickets') ? '/billing/tickets' : '/tickets';

const getEligibleIdentity = (item: TicketEligibilityItem): string =>
    String(item.invoiceId ?? item.paymentId ?? item.serverId ?? '');

const buildTicketViewHref = (
    basePath: string,
    view: TicketSupportView,
    options: {
        ticketId?: number | null;
        category?: TicketComposeCategory | null;
        invoiceId?: number | null;
        paymentId?: number | null;
    } = {}
): string => {
    const search = new URLSearchParams();
    search.set('view', view);

    if (view === 'chat' && options.ticketId) {
        search.set('ticket', String(options.ticketId));
    }

    if (view === 'tickets' && options.category) {
        search.set('compose', options.category);
    }

    if (view === 'tickets' && options.invoiceId) {
        search.set('invoiceId', String(options.invoiceId));
    }

    if (view === 'tickets' && options.paymentId) {
        search.set('paymentId', String(options.paymentId));
    }

    return `${basePath}?${search.toString()}`;
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
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(COMPOSE_DRAFT_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as TicketComposeDraft;
        if (!parsed?.category || !isComposeCategory(parsed.category)) {
            return null;
        }

        return {
            category: parsed.category,
            selectedEligibleId: parsed.selectedEligibleId || '',
            subject: parsed.subject || '',
            body: parsed.body || '',
            step: isWizardStep(parsed.step ?? null) ? parsed.step : undefined,
        };
    } catch {
        return null;
    }
};

const writeComposeDraft = (draft: TicketComposeDraft | null): void => {
    if (typeof window === 'undefined') {
        return;
    }

    if (!draft) {
        window.sessionStorage.removeItem(COMPOSE_DRAFT_STORAGE_KEY);
        return;
    }

    window.sessionStorage.setItem(COMPOSE_DRAFT_STORAGE_KEY, JSON.stringify(draft));
};

const describeEligible = (item: TicketEligibilityItem, category: TicketComposeCategory): string => {
    if (category === 'support') {
        if ((item.serverId ?? 0) > 0) {
            return item.serverUuidShort
                ? `Server #${item.serverId} • ${item.serverUuidShort}`
                : `Server #${item.serverId}`;
        }

        return 'General support request';
    }

    const amount = `${item.currency} ${Number(item.amount || 0).toFixed(2)}`;
    if (category === 'payment') {
        return `${item.invoiceNumber || 'Invoice'} • ${amount}`;
    }

    return `${item.paymentNumber || 'Payment'} • ${amount}`;
};

const defaultSubjectFor = (category: TicketComposeCategory, selectedEligible: TicketEligibilityItem | null): string => {
    if (selectedEligible?.subject) {
        return selectedEligible.subject;
    }

    if (category === 'payment') {
        return 'Payment help request';
    }

    if (category === 'refund') {
        return 'Refund request';
    }

    return 'Support request';
};

const defaultBodyFor = (category: TicketComposeCategory, selectedEligible: TicketEligibilityItem | null): string => {
    if (!selectedEligible) {
        return '';
    }

    if (category === 'payment') {
        return `Opened from the support center for invoice ${selectedEligible.invoiceNumber}.`;
    }

    if (category === 'refund') {
        return `Opened from the support center for payment ${selectedEligible.paymentNumber}.`;
    }

    return '';
};

const BillingTicketsContainer = () => {
    const history = useHistory();
    const location = useLocation();
    const params = useParams<RouteParams>();
    const search = useMemo(() => new URLSearchParams(location.search), [location.search]);
    const ticketBasePath = useMemo(() => getTicketBasePath(location.pathname), [location.pathname]);
    const requestedView = search.get('view');
    const selectedTicketId = useMemo(() => {
        if (params.ticketId) {
            const parsed = Number(params.ticketId);
            return Number.isFinite(parsed) ? parsed : null;
        }

        const ticket = search.get('ticket');
        if (!ticket) {
            return null;
        }

        const parsed = Number(ticket);

        return Number.isFinite(parsed) ? parsed : null;
    }, [params.ticketId, search]);
    const currentView: TicketSupportView = useMemo(() => {
        if (selectedTicketId) {
            return 'chat';
        }

        if (isSupportView(requestedView)) {
            return requestedView;
        }

        return search.has('compose') ? 'tickets' : 'tickets';
    }, [requestedView, search, selectedTicketId]);

    const composeFromSearch = search.get('compose');
    const defaultComposeCategory: TicketComposeCategory = isComposeCategory(composeFromSearch)
        ? composeFromSearch
        : 'support';
    const defaultWizardStep: TicketWizardStep = isComposeCategory(composeFromSearch) ? 'details' : 'category';

    const { addFlash } = useFlash();
    const { clearAndAddHttpError, clearFlashes } = useFlashKey('tickets');
    const replyFileInputRef = useRef<HTMLInputElement | null>(null);
    const restoredDraftRef = useRef(false);
    const lastAutoSubjectRef = useRef('');

    const { data: tickets, error: ticketsError, isValidating: ticketsLoading, mutate: mutateTickets } = useTickets();
    const {
        data: ticket,
        error: ticketError,
        isValidating: ticketLoading,
        mutate: mutateTicket,
    } = useTicket(selectedTicketId);
    const { data: providers } = useOAuthAccounts();
    const { data: community, mutate: mutateCommunity } = useDiscordCommunityStatus();

    const [composeCategory, setComposeCategory] = useState<TicketComposeCategory>(defaultComposeCategory);
    const [wizardStep, setWizardStep] = useState<TicketWizardStep>(defaultWizardStep);
    const [composeBusy, setComposeBusy] = useState(false);
    const [messageBusy, setMessageBusy] = useState(false);
    const [eligiblesLoading, setEligiblesLoading] = useState(false);
    const [eligibles, setEligibles] = useState<TicketEligibilityItem[]>([]);
    const [selectedEligibleId, setSelectedEligibleId] = useState('');
    const [supportSubject, setSupportSubject] = useState('');
    const [composeBody, setComposeBody] = useState('');
    const [composeFiles, setComposeFiles] = useState<File[]>([]);
    const [replyBody, setReplyBody] = useState('');
    const [replyFiles, setReplyFiles] = useState<File[]>([]);
    const [statusFilter, setStatusFilter] = useState<'open' | 'resolved' | 'closed' | 'all'>('open');
    const [categoryFilter, setCategoryFilter] = useState<'all' | 'payment' | 'refund' | 'support'>('all');

    const discordProvider = useMemo(
        () => (providers || []).find((provider) => provider.provider === 'discord') || null,
        [providers]
    );

    const selectedEligible = useMemo(
        () => eligibles.find((item) => getEligibleIdentity(item) === selectedEligibleId) || null,
        [eligibles, selectedEligibleId]
    );

    const currentWizardStepIndex = TICKET_WIZARD_STEP_ORDER.indexOf(wizardStep);
    const currentWizardStepContent = TICKET_WIZARD_STEP_CONTENT[wizardStep];
    const previousWizardStep = currentWizardStepIndex > 0 ? TICKET_WIZARD_STEP_ORDER[currentWizardStepIndex - 1] : null;

    const selectedTicket = ticket || null;
    const canSubmitReply = replyBody.trim() !== '' || replyFiles.length > 0;
    const composeMeta = composeCategoryMeta(composeCategory);
    const ComposeIcon = composeMeta.Icon;
    const wizardProgressWidth = `${(((currentWizardStepIndex + 1) / TICKET_WIZARD_STEP_ORDER.length) * 100).toFixed(
        2
    )}%`;
    const supportBlockedByDiscord = !discordProvider?.linked;
    const supportBlockedByCommunity = !!discordProvider?.linked && !!community && !community.member;
    const composeBlocked = supportBlockedByDiscord || supportBlockedByCommunity;
    const showSupportGate = composeBlocked;

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

    const persistComposeDraft = (step: TicketWizardStep = wizardStep): void => {
        writeComposeDraft({
            category: composeCategory,
            selectedEligibleId,
            subject: supportSubject,
            body: composeBody,
            step,
        });
    };

    const goToChatView = (ticketId?: number | null) => {
        history.replace(buildTicketViewHref(ticketBasePath, 'chat', { ticketId: ticketId ?? null }));
    };

    const openWizard = (
        category?: TicketComposeCategory,
        options: {
            invoiceId?: number | null;
            paymentId?: number | null;
        } = {}
    ) => {
        clearFlashes();
        const nextCategory = category ?? 'support';

        setComposeCategory(nextCategory);
        setWizardStep(category ? 'details' : 'category');
        setSelectedEligibleId('');
        setSupportSubject('');
        setComposeBody('');
        setComposeFiles([]);
        writeComposeDraft(null);
        history.replace(
            buildTicketViewHref(ticketBasePath, 'tickets', {
                category: category ?? null,
                invoiceId: options.invoiceId ?? null,
                paymentId: options.paymentId ?? null,
            })
        );
    };

    const selectComposeCategory = (category: TicketComposeCategory) => {
        setComposeCategory(category);
        setWizardStep('details');
        setSelectedEligibleId('');
        setSupportSubject('');
        setComposeBody('');
        setComposeFiles([]);
        writeComposeDraft(null);
        history.replace(buildTicketViewHref(ticketBasePath, 'tickets', { category }));
    };

    const handleLinkDiscord = () => {
        if (!discordProvider?.linkUrl) {
            addFlash({
                key: 'tickets',
                type: 'error',
                title: 'Discord Link',
                message: 'Discord linking is not available right now. Contact an administrator.',
            });
            return;
        }

        persistComposeDraft();
        const returnTo = `${location.pathname}${location.search}`;
        window.location.assign(withReturnTo(discordProvider.linkUrl, returnTo));
    };

    useEffect(() => {
        clearAndAddHttpError(ticketsError || ticketError || null);
    }, [ticketsError, ticketError, clearAndAddHttpError]);

    useEffect(() => {
        const oauthStatus = search.get('oauth_status');
        if (!oauthStatus) {
            return;
        }

        if (oauthStatus === 'linked') {
            addFlash({
                key: 'tickets',
                type: 'success',
                title: 'Discord Linked',
                message: 'Your Discord account is linked. You can continue the support flow now.',
            });
        } else {
            addFlash({
                key: 'tickets',
                type: 'warning',
                title: 'Discord Link',
                message: `Discord link flow ended with status: ${oauthStatus}.`,
            });
        }
    }, [search, addFlash]);

    useEffect(() => {
        if (currentView !== 'tickets') {
            return;
        }

        if (isComposeCategory(composeFromSearch)) {
            setComposeCategory(composeFromSearch);
            setWizardStep((current) => (current === 'review' || current === 'message' ? current : 'details'));
            return;
        }

        setComposeCategory('support');
        setWizardStep((current) => (current === 'category' ? current : 'category'));
    }, [currentView, composeFromSearch]);

    useEffect(() => {
        if (restoredDraftRef.current || currentView !== 'tickets') {
            return;
        }

        restoredDraftRef.current = true;
        const draft = readComposeDraft();
        if (!draft) {
            return;
        }

        writeComposeDraft(null);
        setComposeCategory(draft.category);
        setSelectedEligibleId(draft.selectedEligibleId || '');
        setSupportSubject(draft.subject || '');
        setComposeBody(draft.body || '');
        if (draft.step) {
            setWizardStep(draft.step);
        }

        if (!search.has('compose')) {
            history.replace(buildTicketViewHref(ticketBasePath, 'tickets', { category: draft.category }));
        }
    }, [currentView, history, search, ticketBasePath]);

    useEffect(() => {
        if (currentView !== 'tickets' || !discordProvider?.linked) {
            return;
        }

        let mounted = true;
        setEligiblesLoading(true);

        fetchTicketEligibles(composeCategory)
            .then((data) => {
                if (!mounted) {
                    return;
                }

                setEligibles(data);
                const preferredId =
                    composeCategory === 'payment'
                        ? search.get('invoiceId')
                        : composeCategory === 'refund'
                        ? search.get('paymentId')
                        : null;

                setSelectedEligibleId((current) => {
                    if (preferredId && data.some((item) => getEligibleIdentity(item) === preferredId)) {
                        return preferredId;
                    }

                    if (current && data.some((item) => getEligibleIdentity(item) === current)) {
                        return current;
                    }

                    return data[0] ? getEligibleIdentity(data[0]) : '';
                });
            })
            .catch((error) => {
                if (mounted) {
                    clearAndAddHttpError(error as Error);
                    setEligibles([]);
                    setSelectedEligibleId('');
                }
            })
            .finally(() => {
                if (mounted) {
                    setEligiblesLoading(false);
                }
            });

        return () => {
            mounted = false;
        };
    }, [clearAndAddHttpError, composeCategory, currentView, discordProvider?.linked, search]);

    useEffect(() => {
        const nextAutoSubject = defaultSubjectFor(composeCategory, selectedEligible);
        if (!nextAutoSubject) {
            return;
        }

        setSupportSubject((current) => {
            if (current.trim() === '' || current === lastAutoSubjectRef.current) {
                return nextAutoSubject;
            }

            return current;
        });
        lastAutoSubjectRef.current = nextAutoSubject;
    }, [composeCategory, selectedEligible]);

    useEffect(() => {
        if (!selectedTicket?.id) {
            return;
        }

        const source = new EventSource(`/api/client/account/tickets/${selectedTicket.id}/stream`);
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
    }, [selectedTicket?.id, mutateTicket, mutateTickets]);

    const submitCompose = async () => {
        if (composeBlocked) {
            return;
        }

        if (!selectedEligible) {
            addFlash({
                key: 'tickets',
                type: 'error',
                title: 'Selection Required',
                message: 'Choose the linked invoice, payment, or server before continuing.',
            });
            return;
        }

        if (composeCategory === 'support' && composeBody.trim() === '' && composeFiles.length < 1) {
            addFlash({
                key: 'tickets',
                type: 'error',
                title: 'Message Required',
                message: 'Write a message or attach at least one file before opening a support ticket.',
            });
            return;
        }

        if (composeCategory !== 'support' && selectedEligible.existingTicketId) {
            writeComposeDraft(null);
            addFlash({
                key: 'tickets',
                type: 'success',
                title: 'Existing Ticket Reused',
                message:
                    'An active ticket already exists for this billing item. Opening the chat instead of creating a duplicate thread.',
            });
            goToChatView(selectedEligible.existingTicketId);
            return;
        }

        setComposeBusy(true);
        clearFlashes();

        try {
            const created = await createTicket(
                composeCategory === 'support'
                    ? {
                          category: 'support',
                          subject: supportSubject.trim() || defaultSubjectFor('support', selectedEligible),
                          body: composeBody.trim(),
                          support_server_id: selectedEligible.serverId ?? 0,
                      }
                    : {
                          category: composeCategory,
                          subject: supportSubject.trim() || defaultSubjectFor(composeCategory, selectedEligible),
                          billing_invoice_id:
                              composeCategory === 'payment'
                                  ? selectedEligible.invoiceId
                                  : selectedEligible.invoiceId ?? undefined,
                          billing_payment_id: composeCategory === 'refund' ? selectedEligible.paymentId : undefined,
                          billing_order_id: selectedEligible.orderId ?? undefined,
                          billing_subscription_id: selectedEligible.subscriptionId ?? undefined,
                          body: composeBody.trim() || defaultBodyFor(composeCategory, selectedEligible),
                      },
                composeFiles
            );

            writeComposeDraft(null);
            setComposeFiles([]);
            setComposeBody('');
            await mutateTickets();
            addFlash({
                key: 'tickets',
                type: 'success',
                title: 'Ticket Opened',
                message: `${created.ticketNumber} is ready. The panel has moved you into the chat workspace.`,
            });
            goToChatView(created.id);
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setComposeBusy(false);
        }
    };

    const submitReply = async () => {
        if (!selectedTicketId || (replyBody.trim() === '' && replyFiles.length < 1)) {
            return;
        }

        setMessageBusy(true);
        clearFlashes();

        try {
            await postTicketMessage(selectedTicketId, replyBody, replyFiles);
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

    const handleWizardContinue = async () => {
        if (wizardStep === 'category') {
            setWizardStep('details');
            return;
        }

        if (wizardStep === 'details') {
            if (!selectedEligible) {
                addFlash({
                    key: 'tickets',
                    type: 'error',
                    title: 'Selection Required',
                    message: 'Choose an item before continuing to the message step.',
                });
                return;
            }

            setWizardStep('message');
            return;
        }

        if (wizardStep === 'message') {
            if (composeCategory === 'support' && composeBody.trim() === '' && composeFiles.length < 1) {
                addFlash({
                    key: 'tickets',
                    type: 'error',
                    title: 'Message Required',
                    message: 'Support tickets need a message or at least one attachment before review.',
                });
                return;
            }

            setWizardStep('review');
            return;
        }

        await submitCompose();
    };

    return (
        <div className={'billing-shell h-full min-h-0 px-4 pb-8 pt-6 text-white md:px-8 md:pt-8'}>
            <style>{`
                .billing-shell {
                    position: relative;
                    height: 100%;
                    min-height: 0;
                    overflow-y: auto;
                    overflow-x: hidden;
                    -webkit-overflow-scrolling: touch;
                    background:
                        radial-gradient(circle at 8% 0%, rgba(var(--primary-rgb), 0.08), transparent 40%),
                        linear-gradient(180deg, rgba(var(--background-rgb), 1), rgba(var(--background-rgb), 0.985));
                }

                .billing-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        repeating-linear-gradient(
                            90deg,
                            rgba(var(--primary-rgb), 0.02) 0,
                            rgba(var(--primary-rgb), 0.02) 1px,
                            transparent 1px,
                            transparent 40px
                        );
                    opacity: 0.1;
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
                        rgba(var(--primary-rgb), 0.05) 0%,
                        rgba(var(--primary-rgb), 0.015) 42%,
                        transparent 72%
                    );
                }

                .billing-wrap {
                    position: relative;
                    z-index: 2;
                    width: 100%;
                    flex: 1 1 0%;
                    min-height: 100%;
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                }

                .billing-panel {
                    border-radius: 24px;
                    border: 1px solid var(--surface-border);
                    background:
                        linear-gradient(160deg, rgba(var(--primary-rgb), 0.035), rgba(var(--primary-rgb), 0.012) 46%),
                        var(--surface-elevated);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.04),
                        0 30px 46px -32px rgba(0, 0, 0, 0.82);
                    backdrop-filter: blur(8px);
                }

                .ticket-layout {
                    display: grid;
                    gap: 1.5rem;
                    flex: 1 1 0%;
                    min-height: 0;
                    align-items: stretch;
                }

                .ticket-main-shell,
                .ticket-inbox-shell,
                .ticket-chat-shell {
                    display: flex;
                    flex-direction: column;
                    min-height: 0;
                }

                .ticket-main-shell,
                .ticket-chat-shell {
                    height: 100%;
                }

                .ticket-layout > * {
                    min-height: 0;
                }

                .ticket-main-scroll,
                .ticket-inbox-scroll,
                .ticket-summary-scroll {
                    flex: 1 1 0%;
                    min-height: 0;
                    overflow-x: hidden;
                    overflow-y: auto;
                    scrollbar-width: none;
                    -ms-overflow-style: none;
                }

                .ticket-main-scroll::-webkit-scrollbar,
                .ticket-inbox-scroll::-webkit-scrollbar,
                .ticket-summary-scroll::-webkit-scrollbar {
                    display: none;
                    width: 0;
                    height: 0;
                }

                .ticket-summary-scroll {
                    display: grid;
                    gap: 1rem;
                    align-content: start;
                }

                .ticket-chat-stage,
                .ticket-conversation-shell {
                    display: flex;
                    flex: 1 1 0%;
                    min-height: 0;
                    flex-direction: column;
                }

                .ticket-conversation-feed {
                    flex: 1 1 0%;
                    min-height: 0;
                }

                .ticket-gate-shell {
                    display: flex;
                    justify-content: flex-start;
                }

                .ticket-gate-card {
                    width: min(100%, 980px);
                }

                @media (min-width: 1280px) {
                    .ticket-layout {
                        grid-template-rows: minmax(0, 1fr);
                    }

                    .ticket-layout-wizard {
                        grid-template-columns: minmax(0, 2.1fr) minmax(280px, 320px);
                    }

                    .ticket-layout-chat {
                        grid-template-columns: minmax(300px, 340px) minmax(0, 2fr);
                    }
                }

            `}</style>

            <div className={'billing-wrap'}>
                <FlashMessageRender byKey={'tickets'} />

                {showSupportGate ? (
                    <div className={'ticket-gate-shell'}>
                        <section className={'billing-panel ticket-gate-card p-6 md:p-8'}>
                            {supportBlockedByDiscord ? (
                                <div className={'flex flex-col gap-6 md:flex-row md:items-center md:justify-between'}>
                                    <div className={'max-w-3xl'}>
                                        <div className={'inline-flex items-center gap-3 text-[color:var(--primary)]'}>
                                            <Link2 size={18} />
                                            <span className={'text-xs font-black uppercase tracking-[0.22em]'}>
                                                Discord Required
                                            </span>
                                        </div>
                                        <h2 className={'mt-4 text-3xl font-black tracking-tight text-[#f8f6ef]'}>
                                            Link Discord First
                                        </h2>
                                        <p
                                            className={
                                                'mt-4 max-w-2xl text-sm leading-7 text-[color:var(--muted-foreground)]'
                                            }
                                        >
                                            Tickets are tied to your Discord identity before they move into chat. Link
                                            the account now and the support center will reopen in the correct state
                                            without leaving empty space on this page.
                                        </p>
                                    </div>
                                    <div className={'flex flex-wrap gap-3'}>
                                        <button
                                            className={'btn btn-primary'}
                                            onClick={handleLinkDiscord}
                                            type={'button'}
                                        >
                                            Link Discord
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <div className={'flex flex-col gap-6 md:flex-row md:items-center md:justify-between'}>
                                    <div className={'max-w-3xl'}>
                                        <div className={'inline-flex items-center gap-3 text-[color:var(--primary)]'}>
                                            <LifeBuoy size={18} />
                                            <span className={'text-xs font-black uppercase tracking-[0.22em]'}>
                                                Discord Community
                                            </span>
                                        </div>
                                        <h2 className={'mt-4 text-3xl font-black tracking-tight text-[#f8f6ef]'}>
                                            Join Discord Server
                                        </h2>
                                        <p
                                            className={
                                                'mt-4 max-w-2xl text-sm leading-7 text-[color:var(--muted-foreground)]'
                                            }
                                        >
                                            Your Discord account is linked, but it has not joined the configured Discord
                                            server yet. Join now so private ticket threads can be created and synced
                                            correctly before you continue this support flow.
                                        </p>
                                    </div>
                                    <div className={'flex flex-wrap gap-3'}>
                                        <button
                                            className={'btn btn-primary'}
                                            onClick={async () => {
                                                const result = await joinDiscordCommunity();
                                                if (!result.success) {
                                                    clearAndAddHttpError(
                                                        result.error || 'Failed to join the Discord community.'
                                                    );
                                                    return;
                                                }

                                                await mutateCommunity();
                                                addFlash({
                                                    key: 'tickets',
                                                    type: 'success',
                                                    title: 'Discord Joined',
                                                    message:
                                                        'Your Discord account has been added to the configured community.',
                                                });
                                            }}
                                            type={'button'}
                                        >
                                            Join Discord Community
                                        </button>
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>
                ) : null}

                {currentView === 'tickets' ? (
                    <div className={'ticket-layout ticket-layout-wizard'}>
                        <section className={'billing-panel ticket-main-shell p-6 md:p-8'}>
                            <div className={'ticket-main-scroll'}>
                                <div className={'flex flex-col gap-5 border-b border-[color:var(--border)] pb-6'}>
                                    <div className={'flex flex-wrap items-start justify-between gap-4'}>
                                        <div>
                                            <p
                                                className={
                                                    'text-xs font-black uppercase tracking-[0.22em] text-[color:var(--primary)]'
                                                }
                                            >
                                                {currentWizardStepContent.eyebrow} of {TICKET_WIZARD_STEP_ORDER.length}
                                            </p>
                                            <h2 className={'mt-3 text-2xl font-black tracking-tight text-[#f8f6ef]'}>
                                                {currentWizardStepContent.title}
                                            </h2>
                                            <p
                                                className={
                                                    'mt-3 max-w-3xl text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                }
                                            >
                                                {currentWizardStepContent.copy}
                                            </p>
                                        </div>
                                        <div className={'flex flex-wrap gap-2'}>
                                            {TICKET_WIZARD_STEP_ORDER.map((step, index) => (
                                                <button
                                                    key={step}
                                                    className={`rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] transition ${
                                                        step === wizardStep
                                                            ? 'border-[color:var(--primary)] bg-[rgba(var(--primary-rgb),0.16)] text-[#efffc8]'
                                                            : index < currentWizardStepIndex
                                                            ? 'border-white/12 bg-white/6 text-[#f8f6ef]'
                                                            : 'border-white/10 bg-transparent text-[color:var(--muted-foreground)]'
                                                    }`}
                                                    onClick={() => {
                                                        if (index <= currentWizardStepIndex) {
                                                            setWizardStep(step);
                                                        }
                                                    }}
                                                    type={'button'}
                                                >
                                                    {step}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    <div className={'h-2 overflow-hidden rounded-full bg-white/8'}>
                                        <div
                                            className={
                                                'h-full rounded-full bg-[linear-gradient(90deg,rgba(var(--primary-rgb),0.92),rgba(var(--primary-rgb),0.36))]'
                                            }
                                            style={{ width: wizardProgressWidth }}
                                        />
                                    </div>
                                </div>

                                <div className={'mt-6'}>
                                    {wizardStep === 'category' ? (
                                        <div className={'grid gap-4 lg:grid-cols-3'}>
                                            {TICKET_CATEGORY_ORDER.map((category) => {
                                                const meta = composeCategoryMeta(category);
                                                const Icon = meta.Icon;

                                                return (
                                                    <button
                                                        key={category}
                                                        className={`rounded-[24px] border p-5 text-left transition ${
                                                            composeCategory === category
                                                                ? 'border-[color:var(--primary)] bg-[rgba(var(--primary-rgb),0.1)]'
                                                                : 'border-[color:var(--border)] bg-[color:var(--card)] hover:border-[rgba(var(--primary-rgb),0.38)]'
                                                        }`}
                                                        onClick={() => selectComposeCategory(category)}
                                                        type={'button'}
                                                    >
                                                        <div
                                                            className={`inline-flex h-12 w-12 items-center justify-center rounded-[18px] border ${
                                                                meta.iconClass === 'is-payment'
                                                                    ? 'border-emerald-400/24 bg-emerald-500/12 text-emerald-100'
                                                                    : meta.iconClass === 'is-refund'
                                                                    ? 'border-amber-400/24 bg-amber-500/12 text-amber-100'
                                                                    : 'border-[rgba(var(--primary-rgb),0.24)] bg-[rgba(var(--primary-rgb),0.12)] text-[#efffc8]'
                                                            }`}
                                                        >
                                                            <Icon size={18} />
                                                        </div>
                                                        <div
                                                            className={
                                                                'mt-5 text-xs font-black uppercase tracking-[0.22em] text-[color:var(--primary)]'
                                                            }
                                                        >
                                                            {meta.eyebrow}
                                                        </div>
                                                        <h3
                                                            className={
                                                                'mt-3 text-lg font-black tracking-tight text-[#f8f6ef]'
                                                            }
                                                        >
                                                            {meta.title}
                                                        </h3>
                                                        <p
                                                            className={
                                                                'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                            }
                                                        >
                                                            {meta.copy}
                                                        </p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    ) : null}

                                    {wizardStep === 'details' ? (
                                        <div className={'space-y-4'}>
                                            <div className={'flex flex-wrap items-center justify-between gap-3'}>
                                                <div>
                                                    <div
                                                        className={
                                                            'text-xs font-black uppercase tracking-[0.22em] text-[color:var(--primary)]'
                                                        }
                                                    >
                                                        {composeMeta.eyebrow}
                                                    </div>
                                                    <h3 className={'mt-2 text-xl font-black text-[#f8f6ef]'}>
                                                        {composeMeta.title}
                                                    </h3>
                                                    <p
                                                        className={
                                                            'mt-2 text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                        }
                                                    >
                                                        {composeMeta.actionCopy}
                                                    </p>
                                                </div>
                                                {eligiblesLoading ? <Spinner size={Spinner.Size.SMALL} /> : null}
                                            </div>

                                            {eligibles.length > 0 ? (
                                                <div className={'grid gap-4'}>
                                                    {eligibles.map((item) => {
                                                        const active = getEligibleIdentity(item) === selectedEligibleId;
                                                        return (
                                                            <button
                                                                key={`${composeCategory}-${getEligibleIdentity(item)}`}
                                                                className={`rounded-[24px] border p-5 text-left transition ${
                                                                    active
                                                                        ? 'border-[color:var(--primary)] bg-[rgba(var(--primary-rgb),0.1)]'
                                                                        : 'border-[color:var(--border)] bg-[color:var(--card)] hover:border-[rgba(var(--primary-rgb),0.32)]'
                                                                }`}
                                                                onClick={() =>
                                                                    setSelectedEligibleId(getEligibleIdentity(item))
                                                                }
                                                                type={'button'}
                                                            >
                                                                <div
                                                                    className={
                                                                        'flex flex-col gap-4 md:flex-row md:items-start md:justify-between'
                                                                    }
                                                                >
                                                                    <div>
                                                                        <div
                                                                            className={
                                                                                'text-sm font-black text-[#f8f6ef]'
                                                                            }
                                                                        >
                                                                            {composeCategory === 'support'
                                                                                ? item.serverLabel || item.subject
                                                                                : item.subject}
                                                                        </div>
                                                                        <div
                                                                            className={
                                                                                'mt-2 text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                                            }
                                                                        >
                                                                            {describeEligible(item, composeCategory)}
                                                                        </div>
                                                                        {item.serverName &&
                                                                        composeCategory !== 'support' ? (
                                                                            <div
                                                                                className={
                                                                                    'mt-2 text-xs uppercase tracking-[0.14em] text-[color:var(--muted-foreground)]'
                                                                                }
                                                                            >
                                                                                Server {item.serverName}
                                                                            </div>
                                                                        ) : null}
                                                                    </div>
                                                                    <div
                                                                        className={'flex flex-wrap items-center gap-2'}
                                                                    >
                                                                        <span
                                                                            className={
                                                                                'rounded-full border border-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-[color:var(--muted-foreground)]'
                                                                            }
                                                                        >
                                                                            {item.status.replace(/_/g, ' ')}
                                                                        </span>
                                                                        {item.existingTicketId ? (
                                                                            <span
                                                                                className={
                                                                                    'rounded-full border border-amber-400/22 bg-amber-500/12 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-amber-100'
                                                                                }
                                                                            >
                                                                                Existing Ticket
                                                                            </span>
                                                                        ) : null}
                                                                    </div>
                                                                </div>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            ) : (
                                                <div
                                                    className={
                                                        'rounded-[24px] border border-dashed border-[color:var(--border)] p-6 text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                    }
                                                >
                                                    {composeMeta.emptyCopy}
                                                </div>
                                            )}
                                        </div>
                                    ) : null}

                                    {wizardStep === 'message' ? (
                                        <div className={'grid gap-5'}>
                                            <div
                                                className={
                                                    'rounded-[24px] border border-[color:var(--border)] bg-[color:var(--card)] p-5'
                                                }
                                            >
                                                <label
                                                    className={
                                                        'block text-xs font-black uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                                    }
                                                >
                                                    Subject
                                                </label>
                                                <input
                                                    className={
                                                        'mt-3 w-full rounded-[18px] border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[#f8f6ef]'
                                                    }
                                                    onChange={(event) => setSupportSubject(event.currentTarget.value)}
                                                    placeholder={defaultSubjectFor(composeCategory, selectedEligible)}
                                                    value={supportSubject}
                                                />
                                                <p
                                                    className={
                                                        'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                    }
                                                >
                                                    {composeCategory === 'support'
                                                        ? 'Support subjects can stay general or be tied to the selected server.'
                                                        : 'Billing tickets can include a clearer subject if you want staff to recognise the issue faster.'}
                                                </p>
                                            </div>

                                            <div
                                                className={
                                                    'rounded-[24px] border border-[color:var(--border)] bg-[color:var(--card)] p-5'
                                                }
                                            >
                                                <label
                                                    className={
                                                        'block text-xs font-black uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                                    }
                                                >
                                                    {composeCategory === 'support' ? 'Message' : 'Message Or Note'}
                                                </label>
                                                <textarea
                                                    className={
                                                        'mt-3 min-h-[220px] w-full rounded-[18px] border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm leading-7 text-[#f8f6ef]'
                                                    }
                                                    onChange={(event) => setComposeBody(event.currentTarget.value)}
                                                    placeholder={
                                                        composeCategory === 'support'
                                                            ? 'Describe the issue clearly so staff can continue the conversation in chat.'
                                                            : 'Optional: add payment proof notes, refund context, or anything staff should review before replying.'
                                                    }
                                                    value={composeBody}
                                                />
                                                <p
                                                    className={
                                                        'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                    }
                                                >
                                                    {composeCategory === 'support'
                                                        ? 'Support tickets require a message or at least one attachment.'
                                                        : 'Billing tickets can be opened with the default linked note, but you can add extra detail here.'}
                                                </p>
                                            </div>

                                            <div
                                                className={
                                                    'rounded-[24px] border border-[color:var(--border)] bg-[color:var(--card)] p-5'
                                                }
                                            >
                                                <div className={'flex items-center justify-between gap-3'}>
                                                    <label
                                                        className={
                                                            'text-xs font-black uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                                        }
                                                    >
                                                        Attachments
                                                    </label>
                                                    <span
                                                        className={
                                                            'text-[10px] font-black uppercase tracking-[0.16em] text-[color:var(--muted-foreground)]'
                                                        }
                                                    >
                                                        Optional
                                                    </span>
                                                </div>
                                                <input
                                                    className={
                                                        'mt-4 block w-full text-sm text-[color:var(--muted-foreground)]'
                                                    }
                                                    multiple
                                                    onChange={(event) =>
                                                        setComposeFiles(Array.from(event.currentTarget.files || []))
                                                    }
                                                    type={'file'}
                                                />
                                                {composeFiles.length > 0 ? (
                                                    <div className={'mt-4 flex flex-wrap gap-2'}>
                                                        {composeFiles.map((file) => (
                                                            <span
                                                                key={`${file.name}-${file.size}`}
                                                                className={
                                                                    'inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/6 px-3 py-1.5 text-xs text-[#f8f6ef]'
                                                                }
                                                            >
                                                                <Paperclip size={12} />
                                                                <span>{file.name}</span>
                                                                <span
                                                                    className={'text-[color:var(--muted-foreground)]'}
                                                                >
                                                                    {formatFileSize(file.size)}
                                                                </span>
                                                            </span>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p
                                                        className={
                                                            'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'
                                                        }
                                                    >
                                                        Screenshots, receipts, invoices, and console captures help staff
                                                        review faster.
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ) : null}

                                    {wizardStep === 'review' ? (
                                        <div className={'grid gap-5'}>
                                            <div
                                                className={
                                                    'rounded-[24px] border border-[color:var(--border)] bg-[color:var(--card)] p-5'
                                                }
                                            >
                                                <div
                                                    className={
                                                        'text-xs font-black uppercase tracking-[0.22em] text-[color:var(--primary)]'
                                                    }
                                                >
                                                    Ticket Summary
                                                </div>
                                                <div className={'mt-4 grid gap-4 sm:grid-cols-2'}>
                                                    <div>
                                                        <div
                                                            className={
                                                                'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                                            }
                                                        >
                                                            Queue
                                                        </div>
                                                        <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>
                                                            {composeMeta.title}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div
                                                            className={
                                                                'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                                            }
                                                        >
                                                            Linked Item
                                                        </div>
                                                        <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>
                                                            {selectedEligible
                                                                ? composeCategory === 'support'
                                                                    ? selectedEligible.serverLabel ||
                                                                      selectedEligible.subject
                                                                    : selectedEligible.subject
                                                                : 'Not selected'}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div
                                                            className={
                                                                'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                                            }
                                                        >
                                                            Subject
                                                        </div>
                                                        <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>
                                                            {supportSubject.trim() ||
                                                                defaultSubjectFor(composeCategory, selectedEligible)}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div
                                                            className={
                                                                'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                                            }
                                                        >
                                                            Attachments
                                                        </div>
                                                        <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>
                                                            {composeFiles.length} file
                                                            {composeFiles.length === 1 ? '' : 's'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                className={
                                                    'rounded-[24px] border border-[color:var(--border)] bg-[color:var(--card)] p-5'
                                                }
                                            >
                                                <div
                                                    className={
                                                        'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                                    }
                                                >
                                                    Message Preview
                                                </div>
                                                <div
                                                    className={
                                                        'mt-4 whitespace-pre-wrap text-sm leading-7 text-[#f8f6ef]'
                                                    }
                                                >
                                                    {composeBody.trim() ||
                                                        defaultBodyFor(composeCategory, selectedEligible) ||
                                                        'No extra message provided.'}
                                                </div>
                                            </div>

                                            {selectedEligible?.existingTicketId ? (
                                                <div
                                                    className={
                                                        'rounded-[24px] border border-amber-400/24 bg-amber-500/10 p-5 text-sm leading-7 text-amber-100'
                                                    }
                                                >
                                                    An existing ticket already covers this billing item. Submitting now
                                                    will take you into the existing chat instead of opening a duplicate
                                                    ticket.
                                                </div>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            </div>

                            <div
                                className={
                                    'mt-6 flex flex-col gap-3 border-t border-[color:var(--border)] pt-6 sm:flex-row sm:items-center sm:justify-between'
                                }
                            >
                                <div className={'flex flex-wrap gap-3'}>
                                    {previousWizardStep ? (
                                        <button
                                            className={
                                                'inline-flex min-h-[2.75rem] items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--card)] px-5 py-3 text-sm font-bold text-[#f8f6ef] transition hover:border-[rgba(var(--primary-rgb),0.32)] hover:bg-[rgba(var(--primary-rgb),0.08)]'
                                            }
                                            onClick={() => setWizardStep(previousWizardStep)}
                                            type={'button'}
                                        >
                                            Back
                                        </button>
                                    ) : (
                                        <button
                                            className={
                                                'inline-flex min-h-[2.75rem] items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--card)] px-5 py-3 text-sm font-bold text-[#f8f6ef] transition hover:border-[rgba(var(--primary-rgb),0.32)] hover:bg-[rgba(var(--primary-rgb),0.08)]'
                                            }
                                            onClick={() => goToChatView(selectedTicketId)}
                                            type={'button'}
                                        >
                                            Go To Chat
                                        </button>
                                    )}
                                </div>
                                <InteractiveHoverButton
                                    className={'w-full normal-case tracking-normal sm:w-auto'}
                                    disabled={
                                        composeBusy || composeBlocked || (wizardStep === 'details' && !selectedEligible)
                                    }
                                    onClick={() => void handleWizardContinue()}
                                    text={
                                        composeBusy
                                            ? 'Opening...'
                                            : wizardStep === 'review'
                                            ? selectedEligible?.existingTicketId
                                                ? 'Continue In Chat'
                                                : 'Open Ticket & Go To Chat'
                                            : 'Continue'
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
                        </section>

                        <aside className={'billing-panel p-6'}>
                            <div
                                className={`inline-flex h-12 w-12 items-center justify-center rounded-[18px] border ${
                                    composeMeta.iconClass === 'is-payment'
                                        ? 'border-emerald-400/24 bg-emerald-500/12 text-emerald-100'
                                        : composeMeta.iconClass === 'is-refund'
                                        ? 'border-amber-400/24 bg-amber-500/12 text-amber-100'
                                        : 'border-[rgba(var(--primary-rgb),0.24)] bg-[rgba(var(--primary-rgb),0.12)] text-[#efffc8]'
                                }`}
                            >
                                <ComposeIcon size={18} />
                            </div>
                            <div
                                className={
                                    'mt-5 text-xs font-black uppercase tracking-[0.22em] text-[color:var(--primary)]'
                                }
                            >
                                Workflow Summary
                            </div>
                            <h2 className={'mt-3 text-xl font-black tracking-tight text-[#f8f6ef]'}>
                                {composeMeta.title}
                            </h2>
                            <p className={'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                Complete the guided ticket flow here, then the panel will move the conversation into the
                                chat workspace automatically.
                            </p>

                            <div className={'ticket-summary-scroll mt-6'}>
                                <div
                                    className={
                                        'rounded-[20px] border border-[color:var(--border)] bg-[color:var(--card)] p-4'
                                    }
                                >
                                    <div
                                        className={
                                            'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Selected Queue
                                    </div>
                                    <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>{composeMeta.title}</div>
                                </div>
                                <div
                                    className={
                                        'rounded-[20px] border border-[color:var(--border)] bg-[color:var(--card)] p-4'
                                    }
                                >
                                    <div
                                        className={
                                            'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Linked Item
                                    </div>
                                    <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>
                                        {selectedEligible
                                            ? composeCategory === 'support'
                                                ? selectedEligible.serverLabel || selectedEligible.subject
                                                : selectedEligible.subject
                                            : 'Choose an item in Step 2'}
                                    </div>
                                    {selectedEligible ? (
                                        <div className={'mt-2 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                            {describeEligible(selectedEligible, composeCategory)}
                                        </div>
                                    ) : null}
                                </div>
                                <div
                                    className={
                                        'rounded-[20px] border border-[color:var(--border)] bg-[color:var(--card)] p-4'
                                    }
                                >
                                    <div
                                        className={
                                            'text-[11px] font-black uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Destination
                                    </div>
                                    <div className={'mt-2 text-sm font-bold text-[#f8f6ef]'}>/tickets?view=chat</div>
                                    <div className={'mt-2 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                        After submit, the panel opens the new or existing thread inside the chat view.
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                ) : (
                    <div className={'ticket-layout ticket-layout-chat'}>
                        <aside className={'billing-panel ticket-inbox-shell p-4'}>
                            <div className={'mb-4 flex items-center justify-between'}>
                                <h2 className={'text-lg font-black text-[#f8f6ef]'}>My Support Tickets</h2>
                                {ticketsLoading && !tickets ? <Spinner size={Spinner.Size.SMALL} /> : null}
                            </div>

                            <div className={'mb-4 grid gap-3 md:grid-cols-2'}>
                                <select
                                    className={
                                        'w-full rounded-2xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[#f8f6ef]'
                                    }
                                    onChange={(event) =>
                                        setStatusFilter(event.currentTarget.value as typeof statusFilter)
                                    }
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

                            <div className={'ticket-inbox-scroll space-y-3'}>
                                {filteredTickets.map((item) => (
                                    <Link
                                        key={item.id}
                                        to={buildTicketViewHref(ticketBasePath, 'chat', { ticketId: item.id })}
                                        className={`block rounded-2xl border p-4 transition-colors ${
                                            item.id === selectedTicketId
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

                        <section className={'billing-panel ticket-chat-shell p-6'}>
                            {ticketLoading && !selectedTicket ? (
                                <Spinner centered />
                            ) : selectedTicket ? (
                                <div className={'ticket-chat-stage'}>
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
                                            <InteractiveHoverButton
                                                className={'w-full normal-case tracking-normal sm:w-auto'}
                                                onClick={() => openWizard()}
                                                text={'Open Another Ticket'}
                                                type={'button'}
                                            />
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
                                            {selectedTicket.status === 'resolved' ||
                                            selectedTicket.status === 'closed' ? (
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
                                            'ticket-conversation-shell mt-6 overflow-hidden rounded-[28px] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.035),rgba(255,255,255,0.02))] shadow-[0_34px_80px_-48px_rgba(0,0,0,0.9)]'
                                        }
                                    >
                                        <div
                                            className={
                                                'ticket-conversation-feed min-h-[24rem] bg-[radial-gradient(circle_at_top,rgba(var(--primary-rgb),0.08),transparent_34%),linear-gradient(180deg,rgba(4,8,14,0.96),rgba(2,4,8,0.96))]'
                                            }
                                        >
                                            <ChatMessageList smooth>
                                                {selectedTicket.messages.length > 0 ? (
                                                    selectedTicket.messages.map((message) => {
                                                        const tone = messageTone(message.authorType);
                                                        const displayName =
                                                            message.authorDisplayName ||
                                                            tone.eyebrow ||
                                                            message.authorType;

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
                                                                            {message.createdAt?.toLocaleString() ||
                                                                                'Now'}
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
                                                                            <div
                                                                                className={'mt-4 flex flex-wrap gap-2'}
                                                                            >
                                                                                {message.attachments.map(
                                                                                    (attachment) => (
                                                                                        <a
                                                                                            key={attachment.id}
                                                                                            className={
                                                                                                'inline-flex items-center gap-2 rounded-full border border-white/10 bg-[rgba(255,255,255,0.08)] px-3 py-1.5 text-xs font-semibold text-[#f8f6ef] transition hover:border-[rgba(var(--primary-rgb),0.28)] hover:bg-[rgba(var(--primary-rgb),0.12)]'
                                                                                            }
                                                                                            href={
                                                                                                attachment.downloadUrl
                                                                                            }
                                                                                            rel={'noreferrer'}
                                                                                            target={'_blank'}
                                                                                        >
                                                                                            <Paperclip size={12} />
                                                                                            <span>
                                                                                                {attachment.name}
                                                                                            </span>
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
                                                                                    )
                                                                                )}
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
                                            {selectedTicket.status === 'resolved' ||
                                            selectedTicket.status === 'closed' ? (
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
                                                        const selectedFiles = Array.from(
                                                            event.currentTarget.files || []
                                                        );
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
                                                                    `${file.name}-${file.size}-${index}` !==
                                                                    attachmentId
                                                            )
                                                        )
                                                    }
                                                    statusLabel={messageBusy ? 'Sending Reply' : 'Discord Sync'}
                                                    submitDisabled={messageBusy || !canSubmitReply}
                                                />
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div
                                    className={
                                        'flex flex-1 min-h-0 flex-col items-center justify-center gap-4 text-center'
                                    }
                                >
                                    <div className={'text-sm text-[color:var(--muted-foreground)]'}>
                                        Select a ticket from the left to view the conversation, or open a new one from
                                        the ticket flow.
                                    </div>
                                    <InteractiveHoverButton
                                        className={'normal-case tracking-normal'}
                                        onClick={() => openWizard()}
                                        text={'Open Ticket Flow'}
                                        type={'button'}
                                    />
                                </div>
                            )}
                        </section>
                    </div>
                )}
            </div>
        </div>
    );
};

export default BillingTicketsContainer;
