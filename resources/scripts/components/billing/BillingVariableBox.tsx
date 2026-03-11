import React, { memo } from 'react';
import isEqual from 'react-fast-compare';
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
        (rule) =>
            rule === 'boolean' ||
            rule === 'in:0,1' ||
            rule === 'in:1,0' ||
            rule === 'in:true,false' ||
            rule === 'in:false,true'
    );
    const isStringSwitch = variable.rules.some((rule) => rule === 'string');
    const selectValues = variable.rules.find((rule) => rule.startsWith('in:'))?.split(',') || [];

    return (
        <div className={'billing-variable-card'}>
            <div className={'billing-variable-head'}>
                <p className={'billing-variable-title'}>
                    {!variable.isEditable && <span className={'billing-variable-badge'}>Read Only</span>}
                    {variable.name}
                </p>
            </div>
            <div className={'billing-variable-body'}>
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

                            onChange(
                                isStringSwitch ? (value === 'true' ? 'false' : 'true') : value === '1' ? '0' : '1'
                            );
                        }}
                    />
                ) : selectValues.length > 0 ? (
                    <Select
                        onChange={(event) => onChange(event.currentTarget.value)}
                        value={value || variable.defaultValue}
                        disabled={!variable.isEditable}
                        className={'billing-field'}
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
                        className={'billing-field'}
                    />
                )}

                <p className={'mt-2 text-xs leading-6 text-[color:var(--muted-foreground)]'}>{variable.description}</p>
            </div>
        </div>
    );
};

export default memo(BillingVariableBox, isEqual);
