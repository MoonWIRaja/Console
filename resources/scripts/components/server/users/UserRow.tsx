import React, { useState } from 'react';
import { Subuser } from '@/state/server/subusers';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPencilAlt, faUnlockAlt, faUserLock } from '@fortawesome/free-solid-svg-icons';
import RemoveSubuserButton from '@/components/server/users/RemoveSubuserButton';
import EditSubuserModal from '@/components/server/users/EditSubuserModal';
import Can from '@/components/elements/Can';
import { useStoreState } from 'easy-peasy';
import tw from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';

interface Props {
    subuser: Subuser;
}

export default ({ subuser }: Props) => {
    const uuid = useStoreState((state) => state.user!.data!.uuid);
    const [visible, setVisible] = useState(false);

    return (
        <GreyRowBox css={tw`mb-2`}>
            <EditSubuserModal subuser={subuser} visible={visible} onModalDismissed={() => setVisible(false)} />
            <div css={tw`hidden h-10 w-10 overflow-hidden rounded-full border border-[#1f2a14] bg-[#050505] md:block`}>
                <img css={tw`w-full h-full`} src={`${subuser.image}?s=400`} />
            </div>
            <div css={tw`ml-4 flex-1 overflow-hidden`}>
                <p css={tw`truncate text-sm text-[#f8f6ef]`}>{subuser.email}</p>
            </div>
            <div css={tw`ml-4`}>
                <p css={tw`font-medium text-center`}>
                    &nbsp;
                    <FontAwesomeIcon
                        icon={subuser.twoFactorEnabled ? faUserLock : faUnlockAlt}
                        fixedWidth
                        css={!subuser.twoFactorEnabled ? tw`text-red-400` : undefined}
                    />
                    &nbsp;
                </p>
                <p css={tw`text-2xs text-neutral-500 uppercase hidden md:block`}>2FA Enabled</p>
            </div>
            <div css={tw`ml-4 hidden md:block`}>
                <p css={tw`font-medium text-center`}>
                    {subuser.permissions.filter((permission) => permission !== 'websocket.connect').length}
                </p>
                <p css={tw`text-2xs text-neutral-500 uppercase`}>Permissions</p>
            </div>
            {subuser.uuid !== uuid && (
                <>
                    <Can action={'user.update'}>
                        <button
                            type={'button'}
                            aria-label={'Edit subuser'}
                            css={tw`mx-4 block p-1 text-sm text-neutral-500 transition-colors duration-150 hover:text-[#d9ff93] md:p-2`}
                            onClick={() => setVisible(true)}
                        >
                            <FontAwesomeIcon icon={faPencilAlt} />
                        </button>
                    </Can>
                    <Can action={'user.delete'}>
                        <RemoveSubuserButton subuser={subuser} />
                    </Can>
                </>
            )}
        </GreyRowBox>
    );
};
