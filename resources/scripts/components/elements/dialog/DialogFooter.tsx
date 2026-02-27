import React, { useContext } from 'react';
import { DialogContext } from './';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';

export default ({ children }: { children: React.ReactNode }) => {
    const { setFooter } = useContext(DialogContext);

    useDeepCompareEffect(() => {
        setFooter(
            <div className={'flex items-center justify-end space-x-3 border-t border-[#1f2a14] bg-[#000000] px-6 py-4'}>
                {children}
            </div>
        );
    }, [children]);

    return null;
};
