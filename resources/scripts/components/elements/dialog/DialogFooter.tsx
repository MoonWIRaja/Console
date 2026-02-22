import React, { useContext } from 'react';
import { DialogContext } from './';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';

export default ({ children }: { children: React.ReactNode }) => {
    const { setFooter } = useContext(DialogContext);

    useDeepCompareEffect(() => {
        setFooter(
            <div className={'px-6 py-4 bg-white border-t-2 border-black flex items-center justify-end space-x-3'}>
                {children}
            </div>
        );
    }, [children]);

    return null;
};
