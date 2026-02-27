import React, { useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, MotionConfig, motion } from 'framer-motion';
import { ChevronDownIcon, X } from 'lucide-react';

export type TSelectData = {
    id: string;
    label: string;
    value: string;
    description?: string;
    icon?: React.ReactNode;
    disabled?: boolean;
};

type SelectProps = {
    data?: TSelectData[];
    onChange?: (value: string) => void;
    defaultValue?: string;
    title?: string;
};

const Select = ({ data = [], defaultValue, onChange, title = 'Choose Mode' }: SelectProps) => {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<TSelectData | undefined>(undefined);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (defaultValue) {
            const item = data.find((i) => i.value === defaultValue);
            if (item) {
                setSelected(item);
                return;
            }
        }

        setSelected(data[0]);
    }, [defaultValue, data]);

    useEffect(() => {
        if (!open) return;

        const handleOutside = (e: MouseEvent) => {
            if (!ref.current) return;
            if (e.target instanceof Node && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handleOutside);
        return () => document.removeEventListener('mousedown', handleOutside);
    }, [open]);

    const onSelect = (value: string) => {
        const item = data.find((i) => i.value === value);
        if (!item || item.disabled) return;

        setSelected(item);
        onChange?.(value);
        setOpen(false);
    };

    const selectedItem = useMemo(() => selected || data[0], [selected, data]);

    return (
        <MotionConfig
            transition={{
                type: 'spring',
                stiffness: 320,
                damping: 26,
                ease: 'easeOut',
            }}
        >
            <div className='w-full' ref={ref}>
                <AnimatePresence mode='popLayout'>
                    {!open ? (
                        <motion.button
                            type='button'
                            whileTap={{ scale: 0.98 }}
                            animate={{ borderRadius: 14 }}
                            layout
                            layoutId='file-editor-dropdown'
                            onClick={() => setOpen(true)}
                            className='w-full overflow-hidden border border-[#1f2a14] bg-[#000000] text-left shadow-sm'
                        >
                            <SelectItem item={selectedItem} noDescription order={selectedItem?.value} />
                        </motion.button>
                    ) : (
                        <motion.div
                            layout
                            animate={{ borderRadius: 14 }}
                            layoutId='file-editor-dropdown'
                            className='w-full overflow-hidden border border-[#1f2a14] bg-[#000000] py-2 shadow-[0_16px_28px_rgba(0,0,0,0.55)]'
                        >
                            <Head setOpen={setOpen} title={title} />
                            <div className='max-h-72 w-full overflow-y-auto'>
                                {data.map((item, index) => (
                                    <SelectItem
                                        order={item.value}
                                        noDescription={false}
                                        key={item.id}
                                        item={item}
                                        onChange={onSelect}
                                        animationOrder={index}
                                    />
                                ))}
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </MotionConfig>
    );
};

const Head = ({ setOpen, title }: { setOpen: (open: boolean) => void; title: string }) => {
    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ delay: 0.08 }}
            layout
            className='flex items-center justify-between px-4 py-3'
        >
            <strong className='text-xs font-semibold uppercase tracking-wide text-[#f8f6ef]'>{title}</strong>
            <button
                type='button'
                onClick={() => setOpen(false)}
                className='flex h-6 w-6 items-center justify-center rounded-full bg-[#0c0c0c] text-[#c9d1d9] hover:bg-[#131313]'
            >
                <X size={12} />
            </button>
        </motion.div>
    );
};

type SelectItemProps = {
    item?: TSelectData;
    noDescription?: boolean;
    order?: string;
    onChange?: (value: string) => void;
    animationOrder?: number;
};

const SelectItem = ({ item, noDescription = true, order, onChange, animationOrder = 0 }: SelectItemProps) => {
    return (
        <motion.div
            className={[
                'group flex cursor-pointer items-center justify-between gap-2 px-3 py-2 transition-colors',
                'hover:bg-[rgba(163,255,18,0.12)]',
                noDescription ? 'min-h-[2.4rem]' : 'min-h-[3rem]',
                item?.disabled ? 'cursor-not-allowed opacity-50' : '',
            ].join(' ')}
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 8 }}
            transition={{ duration: 0.2, delay: animationOrder * 0.03 }}
            onClick={() => (!item?.disabled ? onChange?.(order || '') : null)}
        >
            <div className='flex min-w-0 items-center gap-3'>
                <div className='flex h-8 w-8 items-center justify-center rounded-md border border-[#1f2a14] bg-[#050505] text-[#d9ff93]'>
                    {item?.icon}
                </div>
                <div className='flex min-w-0 flex-col'>
                    <strong className='truncate text-xs font-semibold uppercase tracking-wide text-[#f8f6ef]'>
                        {item?.label}
                    </strong>
                    {!noDescription && item?.description ? (
                        <span className='truncate text-[11px] text-[#9ca3af]'>{item.description}</span>
                    ) : null}
                </div>
            </div>
            {noDescription ? <ChevronDownIcon className='text-[#d9ff93]' size={16} /> : null}
        </motion.div>
    );
};

export default Select;
