import React, { useState } from 'react';
import { WithClassname } from '@/components/types';
import RecycleBinModal from './RecycleBinModal';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

export default ({ className }: WithClassname) => {
    const [open, setOpen] = useState(false);

    return (
        <>
            <RecycleBinModal open={open} onClose={setOpen.bind(this, false)} />
            <InteractiveHoverButton type={'button'} onClick={setOpen.bind(this, true)} text={'Recycle Bin'} className={className} />
        </>
    );
};
