import React, { useContext } from 'react';
import { DialogContext } from './';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';

export default ({ children }: { children: React.ReactNode }) => {
    const { setFooter } = useContext(DialogContext);

    useDeepCompareEffect(() => {
        setFooter(
            <div
                className={
                    'flex items-center justify-end space-x-3 border-t-2 border-[#2D4A3E] bg-transparent px-5 py-4 md:px-6'
                }
            >
                {children}
            </div>
        );
    }, [children]);

    return null;
};
