import React, { memo } from 'react';
import isEqual from 'react-fast-compare';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import Switch from '@/components/elements/Switch';
import Select from '@/components/elements/Select';
import { BillingGameVariable } from '@/api/account/billing';

interface Props {
    variable: BillingGameVariable;
    value: string;
    onChange: (value: string) => void;
}

const BillingVariableBox = ({ variable, value, onChange }: Props) => {
    const useSwitch = variable.rules.some(
        (rule) => rule === 'boolean' || rule === 'in:0,1' || rule === 'in:1,0' || rule === 'in:true,false' || rule === 'in:false,true'
    );
    const isStringSwitch = variable.rules.some((rule) => rule === 'string');
    const selectValues = variable.rules.find((rule) => rule.startsWith('in:'))?.split(',') || [];

    return (
        <TitledGreyBox
            title={
                <p className='text-sm uppercase'>
                    {!variable.isEditable && (
                        <span className='bg-neutral-700 text-xs py-1 px-2 rounded-full mr-2 mb-1'>Read Only</span>
                    )}
                    {variable.name}
                </p>
            }
        >
            {useSwitch ? (
                <Switch
                    key={`${variable.envVariable}:${value}`}
                    readOnly={!variable.isEditable}
                    name={variable.envVariable}
                    defaultChecked={isStringSwitch ? value === 'true' : value === '1'}
                    onChange={() => {
                        if (!variable.isEditable) {
                            return;
                        }

                        onChange(isStringSwitch ? (value === 'true' ? 'false' : 'true') : value === '1' ? '0' : '1');
                    }}
                />
            ) : selectValues.length > 0 ? (
                <Select
                    onChange={(event) => onChange(event.currentTarget.value)}
                    value={value || variable.defaultValue}
                    disabled={!variable.isEditable}
                >
                    {selectValues.map((selectValue) => {
                        const optionValue = selectValue.replace('in:', '');

                        return (
                            <option key={`${variable.envVariable}_${optionValue}`} value={optionValue}>
                                {optionValue}
                            </option>
                        );
                    })}
                </Select>
            ) : (
                <Input
                    value={value}
                    onChange={(event) => {
                        if (!variable.isEditable) {
                            return;
                        }

                        onChange(event.currentTarget.value);
                    }}
                    readOnly={!variable.isEditable}
                    name={variable.envVariable}
                    placeholder={variable.defaultValue}
                />
            )}

            <p className='mt-1 text-xs text-neutral-300'>{variable.description}</p>
        </TitledGreyBox>
    );
};

export default memo(BillingVariableBox, isEqual);
