import React, { createRef } from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import Fade from '@/components/elements/Fade';
import { createPortal } from 'react-dom';
import { readThemeVariables, serverThemeStyle } from '@/components/server/serverTheme';

interface Props {
    children: React.ReactNode;
    renderToggle: (onClick: (e: React.MouseEvent<any, MouseEvent>) => void) => React.ReactChild;
}

export const DropdownButtonRow = styled.button<{ danger?: boolean }>`
    ${tw`flex w-full items-center rounded-md border border-transparent p-2 text-[color:var(--foreground)]`};
    transition: 150ms all ease;

    &:hover {
        ${(props) =>
            props.danger
                ? tw`border-red-500 bg-red-500/10 text-red-700`
                : tw`border-[color:var(--surface-border)] bg-[color:var(--surface-subtle)] text-[color:var(--primary)]`};
    }
`;

interface State {
    posX: number;
    posY: number;
    visible: boolean;
    themeStyle: React.CSSProperties;
}

class DropdownMenu extends React.PureComponent<Props, State> {
    menu = createRef<HTMLDivElement>();
    ignoreContextMenuUntil = 0;

    state: State = {
        posX: 0,
        posY: 0,
        visible: false,
        themeStyle: serverThemeStyle,
    };

    componentWillUnmount() {
        this.removeListeners();
    }

    componentDidUpdate(prevProps: Readonly<Props>, prevState: Readonly<State>) {
        const menu = this.menu.current;

        if (
            this.state.visible &&
            menu &&
            (!prevState.visible || prevState.posX !== this.state.posX || prevState.posY !== this.state.posY)
        ) {
            document.addEventListener('click', this.windowListener);
            document.addEventListener('contextmenu', this.contextMenuListener);
            const margin = 8;
            const cursorOffset = 6;
            const menuWidth = menu.clientWidth;
            const menuHeight = menu.clientHeight;

            let left = Math.round(this.state.posX + cursorOffset);
            let top = Math.round(this.state.posY + cursorOffset);

            if (left + menuWidth > window.innerWidth - margin) {
                left = Math.max(margin, this.state.posX - menuWidth - cursorOffset);
            }
            if (left < margin) left = margin;

            if (top + menuHeight > window.innerHeight - margin) {
                top = Math.max(margin, this.state.posY - menuHeight - cursorOffset);
            }
            if (top < margin) top = margin;

            menu.style.left = `${left}px`;
            menu.style.top = `${top}px`;
        }

        if (!this.state.visible && prevState.visible) {
            this.removeListeners();
        }
    }

    removeListeners = () => {
        document.removeEventListener('click', this.windowListener);
        document.removeEventListener('contextmenu', this.contextMenuListener);
    };

    onClickHandler = (e: React.MouseEvent<any, MouseEvent>) => {
        e.preventDefault();
        this.triggerMenu(e.clientX, e.clientY, 'toggle', e.currentTarget as HTMLElement);
    };

    contextMenuListener = (e: MouseEvent) => {
        if (Date.now() < this.ignoreContextMenuUntil) {
            return;
        }

        const menu = this.menu.current;
        if (menu && (e.target === menu || menu.contains(e.target as Node))) {
            return;
        }

        this.setState({ visible: false });
    };

    windowListener = (e: MouseEvent) => {
        const menu = this.menu.current;

        if (e.button === 2 || !this.state.visible || !menu) {
            return;
        }

        if (e.target === menu || menu.contains(e.target as Node)) {
            return;
        }

        if (e.target !== menu && !menu.contains(e.target as Node)) {
            this.setState({ visible: false });
        }
    };

    triggerMenu = (
        posX: number,
        posY: number,
        mode: 'toggle' | 'open' = 'toggle',
        source?: HTMLElement | null
    ) =>
        this.setState((s) => {
            const visible = mode === 'open' ? true : !s.visible;

            if (visible) {
                this.ignoreContextMenuUntil = Date.now() + 150;
            }

            return {
                posX,
                posY,
                visible,
                themeStyle: visible ? (readThemeVariables(source || null) as React.CSSProperties) : s.themeStyle,
            };
        });

    render() {
        const menu = (
            <Fade timeout={150} in={this.state.visible} unmountOnExit>
                <div
                    ref={this.menu}
                    onClick={(e) => {
                        e.stopPropagation();
                        this.setState({ visible: false });
                    }}
                    style={{
                        ...this.state.themeStyle,
                        width: '12rem',
                    }}
                    css={tw`fixed z-50 rounded-xl p-2 text-[#742220]`}
                    style={{
                        border: '2px solid #2D4A3E',
                        backgroundColor: '#FEF9E1',
                        backgroundImage: [
                            'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                            'repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                            'repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                        ].join(', '),
                        boxShadow: '4px 4px 0px 0px #2D4A3E',
                    }}
                >
                    {this.props.children}
                </div>
            </Fade>
        );

        return (
            <div>
                {this.props.renderToggle(this.onClickHandler)}
                {typeof document !== 'undefined' ? createPortal(menu, document.body) : menu}
            </div>
        );
    }
}

export default DropdownMenu;
