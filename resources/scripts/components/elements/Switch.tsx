import React, { useMemo, useState } from 'react';
import tw from 'twin.macro';
import Label from '@/components/elements/Label';
import Select from '@/components/ui/select';

export interface SwitchProps {
    name: string;
    label?: string;
    description?: string;
    defaultChecked?: boolean;
    readOnly?: boolean;
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
    children?: React.ReactNode;
}

const Switch = ({ name, label, description, defaultChecked, readOnly, onChange, children }: SwitchProps) => {
    const [checked, setChecked] = useState(!!defaultChecked);
    const selectData = useMemo(
        () => [
            { id: `${name}-on`, label: 'On', value: 'on' },
            { id: `${name}-off`, label: 'Off', value: 'off' },
        ],
        [name]
    );

    const handleChange = (value: string) => {
        if (readOnly) return;

        const nextChecked = value === 'on';
        setChecked(nextChecked);
        onChange?.({
            currentTarget: { checked: nextChecked, name },
            target: { checked: nextChecked, name },
        } as React.ChangeEvent<HTMLInputElement>);
    };

    return (
        <div css={tw`flex items-center`}>
            <div css={tw`w-32 flex-none`}>
                {children || (
                    <>
                        <input type={'hidden'} name={name} value={checked ? '1' : '0'} disabled={readOnly} />
                        <Select
                            compact
                            disabled={readOnly}
                            value={checked ? 'on' : 'off'}
                            defaultValue={checked ? 'on' : 'off'}
                            title={label ? `Choose ${label}` : 'Choose Status'}
                            openDirection={'down'}
                            data={selectData}
                            onChange={handleChange}
                        />
                    </>
                )}
            </div>
            {(label || description) && (
                <div css={tw`ml-4 w-full`}>
                    {label && (
                        <Label css={[tw`cursor-pointer`, !!description && tw`mb-0`]}>
                            {label}
                        </Label>
                    )}
                    {description && <p css={tw`mt-2 text-sm text-[color:var(--text-subtle)]`}>{description}</p>}
                </div>
            )}
        </div>
    );
};

export default Switch;
