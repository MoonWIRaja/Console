export type PendingSignupStage = 'pending_google_link' | 'pending_discord_link' | 'pending_email_verification';

export interface PendingSignupData {
    stage: PendingSignupStage;
    email: string;
    verificationToken?: string;
    googleLinkUrl: string;
    discordLinkUrl: string;
    googleLinked: boolean;
    discordLinked: boolean;
    googleAvailable: boolean;
    discordAvailable: boolean;
}

const stages: PendingSignupStage[] = ['pending_google_link', 'pending_discord_link', 'pending_email_verification'];

export const transformPendingSignup = (value: unknown): PendingSignupData | undefined => {
    if (!value || typeof value !== 'object') {
        return undefined;
    }

    const item = value as Record<string, unknown>;
    const stage =
        typeof item.stage === 'string' && stages.includes(item.stage as PendingSignupStage)
            ? (item.stage as PendingSignupStage)
            : undefined;

    if (!stage || typeof item.email !== 'string') {
        return undefined;
    }

    return {
        stage,
        email: item.email,
        verificationToken: typeof item.verification_token === 'string' ? item.verification_token : undefined,
        googleLinkUrl: typeof item.google_link_url === 'string' ? item.google_link_url : '',
        discordLinkUrl: typeof item.discord_link_url === 'string' ? item.discord_link_url : '',
        googleLinked: !!item.google_linked,
        discordLinked: !!item.discord_linked,
        googleAvailable: !!item.google_available,
        discordAvailable: !!item.discord_available,
    };
};
