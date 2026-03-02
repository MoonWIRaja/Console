import React, { useState } from 'react';
import EditSubuserModal from '@/components/server/users/EditSubuserModal';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

export default () => {
    const [visible, setVisible] = useState(false);

    return (
        <>
            <EditSubuserModal visible={visible} onModalDismissed={() => setVisible(false)} />
            <InteractiveHoverButton text={'New User'} onClick={() => setVisible(true)} />
        </>
    );
};
