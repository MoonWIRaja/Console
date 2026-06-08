import React, { useEffect, useMemo, useRef, useState } from 'react';
import Spinner from '@/components/elements/Spinner';
import tw from 'twin.macro';
import styled, { css } from 'styled-components/macro';
import { breakpoint } from '@/theme';
import Fade from '@/components/elements/Fade';
import { createPortal } from 'react-dom';

export interface RequiredModalProps {
    visible: boolean;
    onDismissed: () => void;
    appear?: boolean;
    top?: boolean;
}

export interface ModalProps extends RequiredModalProps {
    dismissable?: boolean;
    closeOnEscape?: boolean;
    closeOnBackground?: boolean;
    showSpinnerOverlay?: boolean;
}

export const ModalMask = styled.div`
    ${tw`fixed z-50 overflow-auto flex w-full inset-0`};
    background: rgba(84, 63, 38, 0.36);
    backdrop-filter: blur(10px);
`;

const ModalContainer = styled.div<{ alignTop?: boolean }>`
    max-width: 95%;
    max-height: calc(100vh - 8rem);
    ${breakpoint('md')`max-width: 75%`};
    ${breakpoint('lg')`max-width: 50%`};

    ${tw`relative flex flex-col w-full m-auto`};
    ${(props) =>
        props.alignTop &&
        css`
            margin-top: 20%;
            ${breakpoint('md')`margin-top: 10%`};
        `};

    margin-bottom: auto;

    & > .close-icon {
        ${tw`absolute flex items-center justify-center cursor-pointer transition-all duration-150 ease-linear`};
        top: 1rem;
        right: 1rem;
        z-index: 20;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.6rem;
        border: 1px solid #2d4a3e;
        background: #f5efd5;
        color: #742220;

        &:hover {
            background: #ede6d0;
            color: #2d4a3e;
            transform: rotate(90deg);
        }

        & > svg {
            ${tw`w-5 h-5`};
        }
    }
`;

const Modal: React.FC<ModalProps> = ({
    visible,
    appear,
    dismissable,
    showSpinnerOverlay,
    top = false,
    closeOnBackground = true,
    closeOnEscape = true,
    onDismissed,
    children,
}) => {
    const [render, setRender] = useState(visible);

    const isDismissable = useMemo(() => {
        return (dismissable || true) && !(showSpinnerOverlay || false);
    }, [dismissable, showSpinnerOverlay]);

    useEffect(() => {
        if (!isDismissable || !closeOnEscape) return;

        const handler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setRender(false);
        };

        window.addEventListener('keydown', handler);
        return () => {
            window.removeEventListener('keydown', handler);
        };
    }, [isDismissable, closeOnEscape, render]);

    useEffect(() => setRender(visible), [visible]);

    return (
        <Fade in={render} timeout={150} appear={appear || true} unmountOnExit onExited={() => onDismissed()}>
            <ModalMask
                onClick={(e: React.MouseEvent<HTMLDivElement>) => e.stopPropagation()}
                onContextMenu={(e: React.MouseEvent<HTMLDivElement>) => e.stopPropagation()}
                onMouseDown={(e: React.MouseEvent<HTMLDivElement>) => {
                    if (isDismissable && closeOnBackground) {
                        e.stopPropagation();
                        if (e.target === e.currentTarget) {
                            setRender(false);
                        }
                    }
                }}
            >
                <ModalContainer alignTop={top}>
                    {isDismissable && (
                        <button
                            type={'button'}
                            className={'close-icon'}
                            aria-label={'Close modal'}
                            onClick={() => setRender(false)}
                        >
                            <svg
                                xmlns={'http://www.w3.org/2000/svg'}
                                fill={'none'}
                                viewBox={'0 0 24 24'}
                                stroke={'currentColor'}
                            >
                                <path
                                    strokeLinecap={'round'}
                                    strokeLinejoin={'round'}
                                    strokeWidth={'2'}
                                    d={'M6 18L18 6M6 6l12 12'}
                                />
                            </svg>
                        </button>
                    )}
                    {showSpinnerOverlay && (
                        <Fade timeout={150} appear in>
                            <div
                                css={tw`absolute flex h-full w-full items-center justify-center rounded-xl`}
                                style={{ background: 'rgba(84, 63, 38, 0.28)', zIndex: 9999 }}
                            >
                                <Spinner />
                            </div>
                        </Fade>
                    )}
                    <div
                        css={tw`max-h-[calc(100vh-8rem)] overflow-y-auto rounded-[1.35rem] border-2 p-3 text-[#742220] shadow-none transition-all duration-150 sm:p-4 md:p-6`}
                        style={{
                            borderColor: '#2D4A3E',
                            backgroundColor: '#FEF9E1',
                            backgroundImage: [
                                'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                'repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                'repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                            ].join(', '),
                            boxShadow: '4px 4px 0px 0px #2D4A3E',
                        }}
                    >
                        {children}
                    </div>
                </ModalContainer>
            </ModalMask>
        </Fade>
    );
};

const PortaledModal: React.FC<ModalProps> = ({ children, ...props }) => {
    const element = useRef<HTMLElement | null>(
        typeof document === 'undefined' ? null : document.getElementById('modal-portal') || document.body
    );

    return element.current ? createPortal(<Modal {...props}>{children}</Modal>, element.current) : null;
};

export default PortaledModal;
