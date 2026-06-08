import React, { memo, useState } from 'react';
import { ServerEggVariable } from '@/api/server/types';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { usePermissions } from '@/plugins/usePermissions';
import InputSpinner from '@/components/elements/InputSpinner';
import Input from '@/components/elements/Input';
import Switch from '@/components/elements/Switch';
import { debounce } from 'debounce';
import updateStartupVariable from '@/api/server/updateStartupVariable';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import getServerStartup from '@/api/swr/getServerStartup';
import isEqual from 'react-fast-compare';
import { ServerContext } from '@/state/server';
import Select from '@/components/ui/select';

interface Props {
    variable: ServerEggVariable;
}

const VariableBox = ({ variable }: Props) => {
    const FLASH_KEY = `server:startup:${variable.envVariable}`;

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [loading, setLoading] = useState(false);
    const [canEdit] = usePermissions(['startup.update']);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { mutate } = getServerStartup(uuid);

    const setVariableValue = debounce((value: string) => {
        setLoading(true);
        clearFlashes(FLASH_KEY);

        updateStartupVariable(uuid, variable.envVariable, value)
            .then(([response, invocation]) =>
                mutate(
                    (data) => ({
                        ...data,
                        invocation,
                        variables: (data.variables || []).map((v) =>
                            v.envVariable === response.envVariable ? response : v
                        ),
                    }),
                    false
                )
            )
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ error, key: FLASH_KEY });
            })
            .then(() => setLoading(false));
    }, 500);

    const useSwitch = variable.rules.some(
        (v) => v === 'boolean' || v === 'in:0,1' || v === 'in:1,0' || v === 'in:true,false' || v === 'in:false,true'
    );
    const isStringSwitch = variable.rules.some((v) => v === 'string');
    const selectValues = variable.rules.find((v) => v.startsWith('in:'))?.split(',') || [];

    return (
        <TitledGreyBox
            title={
                <p className='text-sm uppercase'>
                    {!variable.isEditable && (
                        <span className='mr-2 mb-1 sc-card-inner rounded-full px-2 py-1 text-xs sc-text'>
                            Read Only
                        </span>
                    )}
                    {variable.name}
                </p>
            }
        >
            <FlashMessageRender byKey={FLASH_KEY} className='mb-2 md:mb-4' />
            <InputSpinner visible={loading}>
                {useSwitch ? (
                    <>
                        <Switch
                            readOnly={!canEdit || !variable.isEditable}
                            name={variable.envVariable}
                            defaultChecked={
                                isStringSwitch ? variable.serverValue === 'true' : variable.serverValue === '1'
                            }
                            onChange={() => {
                                if (canEdit && variable.isEditable) {
                                    if (isStringSwitch) {
                                        setVariableValue(variable.serverValue === 'true' ? 'false' : 'true');
                                    } else {
                                        setVariableValue(variable.serverValue === '1' ? '0' : '1');
                                    }
                                }
                            }}
                        />
                    </>
                ) : (
                    <>
                        {selectValues.length > 0 ? (
                            <>
                                <Select
                                    onChange={(value) => setVariableValue(value)}
                                    value={variable.serverValue ?? variable.defaultValue}
                                    defaultValue={variable.serverValue ?? variable.defaultValue}
                                    disabled={!canEdit || !variable.isEditable}
                                    title={`Choose ${variable.name}`}
                                    openDirection={'down'}
                                    data={selectValues.map((selectValue) => {
                                        const value = selectValue.replace('in:', '');

                                        return {
                                            id: `${variable.envVariable}_${value}`,
                                            label: value,
                                            value,
                                        };
                                    })}
                                />
                            </>
                        ) : (
                            <>
                                <Input
                                    onKeyUp={(e) => {
                                        if (canEdit && variable.isEditable) {
                                            setVariableValue(e.currentTarget.value);
                                        }
                                    }}
                                    readOnly={!canEdit || !variable.isEditable}
                                    name={variable.envVariable}
                                    defaultValue={variable.serverValue ?? ''}
                                    placeholder={variable.defaultValue}
                                />
                            </>
                        )}
                    </>
                )}
            </InputSpinner>

            <p className='mt-1 text-xs text-[color:var(--text-subtle)]'>{variable.description}</p>
        </TitledGreyBox>
    );
};

export default memo(VariableBox, isEqual);
