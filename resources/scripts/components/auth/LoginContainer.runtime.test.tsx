import React from 'react';
import ReactDOMServer from 'react-dom/server';
import { MemoryRouter, Route } from 'react-router-dom';
import LoginContainer from './LoginContainer';

jest.mock('@/api/auth/login', () => jest.fn());
jest.mock('@/api/auth/signup', () => jest.fn());
jest.mock('@/api/auth/verifyEmailPin', () => jest.fn());

jest.mock('easy-peasy', () => {
    const state = {
        settings: {
            data: {
                name: 'BURHAN CONSOLE',
                logo: '/logo.svg',
                captcha: {
                    enabled: false,
                    provider: 'turnstile',
                    siteKey: '',
                },
                oauth: {
                    google: {
                        enabled: true,
                        label: 'Google',
                    },
                    discord: {
                        enabled: true,
                        label: 'Discord',
                    },
                },
            },
        },
        flashes: {
            items: [],
        },
    };

    return {
        useStoreState: (selector: (input: typeof state) => unknown) => selector(state),
        useStoreActions: (selector: (actions: { flashes: { removeFlash: jest.Mock } }) => unknown) =>
            selector({
                flashes: {
                    removeFlash: jest.fn(),
                },
            }),
        Actions: {},
    };
});

jest.mock('@/plugins/useFlash', () => () => ({
    addFlash: jest.fn(),
    clearFlashes: jest.fn(),
    clearAndAddHttpError: jest.fn(),
}));

describe('LoginContainer runtime', () => {
    it('renders without crashing', () => {
        const html = ReactDOMServer.renderToString(
            <MemoryRouter initialEntries={['/auth/login']}>
                <Route path='/auth/login' component={LoginContainer} />
            </MemoryRouter>
        );

        expect(html).toContain('BURHAN CONSOLE');
        expect(html).toContain('LOG IN');
    });
});
