import React, { createRef } from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import Fade from '@/components/elements/Fade';
import { createPortal } from 'react-dom';

interface Props {
    children: React.ReactNode;
    renderToggle: (onClick: (e: React.MouseEvent<any, MouseEvent>) => void) => React.ReactChild;
}

export const DropdownButtonRow = styled.button<{ danger?: boolean }>`
    ${tw`flex w-full items-center rounded-md border border-transparent p-2 text-gray-300`};
    transition: 150ms all ease;

    &:hover {
        ${(props) =>
            props.danger
                ? tw`border-red-500 bg-[#2b1111] text-red-300`
                : tw`border-[#2d3c1f] bg-[color:var(--background)] text-[color:var(--primary)]`};
    }
`;

interface State {
    posX: number;
    posY: number;
    visible: boolean;
}

class DropdownMenu extends React.PureComponent<Props, State> {
    menu = createRef<HTMLDivElement>();

    state: State = {
        posX: 0,
        posY: 0,
        visible: false,
    };

    componentWillUnmount() {
        this.removeListeners();
    }

    componentDidUpdate(prevProps: Readonly<Props>, prevState: Readonly<State>) {
        const menu = this.menu.current;

        if (this.state.visible && menu && (!prevState.visible || prevState.posX !== this.state.posX || prevState.posY !== this.state.posY)) {
            document.addEventListener('click', this.windowListener);
            document.addEventListener('contextmenu', this.contextMenuListener);
            const margin = 8;
            const menuWidth = menu.clientWidth;
            const menuHeight = menu.clientHeight;

            let left = Math.round(this.state.posX - menuWidth);
            let top = Math.round(this.state.posY);

            if (left < margin) left = margin;
            if (left + menuWidth > window.innerWidth - margin) {
                left = Math.max(margin, window.innerWidth - menuWidth - margin);
            }

            if (top + menuHeight > window.innerHeight - margin) {
                top = Math.max(margin, this.state.posY - menuHeight);
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
        this.triggerMenu(e.clientX, e.clientY, 'toggle');
    };

    contextMenuListener = () => this.setState({ visible: false });

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

    triggerMenu = (posX: number, posY: number, mode: 'toggle' | 'open' = 'toggle') =>
        this.setState((s) => ({
            posX,
            posY,
            visible: mode === 'open' ? true : !s.visible,
        }));

    render() {
        const menu = (
            <Fade timeout={150} in={this.state.visible} unmountOnExit>
                <div
                    ref={this.menu}
                    onClick={(e) => {
                        e.stopPropagation();
                        this.setState({ visible: false });
                    }}
                    style={{ width: '12rem' }}
                    css={tw`fixed z-50 rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] p-2 text-gray-300 shadow-xl`}
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
