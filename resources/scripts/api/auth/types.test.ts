import { transformPendingSignup } from './types';

describe('transformPendingSignup', () => {
    it('parses a valid pending signup payload', () => {
        expect(
            transformPendingSignup({
                stage: 'pending_google_link',
                email: 'user@example.com',
                verification_token: 'abc123',
                google_link_url: '/oauth/google/redirect?intent=signup',
                discord_link_url: '/oauth/discord/redirect?intent=signup',
                google_linked: false,
                discord_linked: false,
                google_available: true,
                discord_available: true,
            })
        ).toEqual({
            stage: 'pending_google_link',
            email: 'user@example.com',
            verificationToken: 'abc123',
            googleLinkUrl: '/oauth/google/redirect?intent=signup',
            discordLinkUrl: '/oauth/discord/redirect?intent=signup',
            googleLinked: false,
            discordLinked: false,
            googleAvailable: true,
            discordAvailable: true,
        });
    });

    it('returns undefined for an invalid stage', () => {
        expect(
            transformPendingSignup({
                stage: 'complete',
                email: 'user@example.com',
            })
        ).toBeUndefined();
    });
});
