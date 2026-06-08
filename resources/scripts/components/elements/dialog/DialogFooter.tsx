import React, { useContext } from 'react';
import { DialogContext } from './';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';

export default ({ children }: { children: React.ReactNode }) => {
    const { setFooter } = useContext(DialogContext);

    useDeepCompareEffect(() => {
        setFooter(
            <div
                className={
                    'mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-[#2D4A3E] bg-transparent pt-4'
                }
            >
                {children}
            </div>
        );
    }, [children]);

    return null;
};
