import React, { useRef } from 'react';
import { createPortal } from 'react-dom';

export default ({ children }: { children: React.ReactNode }) => {
    const element = useRef<HTMLElement | null>(
        typeof document === 'undefined' ? null : document.getElementById('modal-portal') || document.body
    );

    return element.current ? createPortal(children, element.current) : <>{children}</>;
};
