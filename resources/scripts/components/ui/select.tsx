import React, { useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
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
    disabled?: boolean;
    compact?: boolean;
};

const Select = ({
    data = [],
    defaultValue,
    onChange,
    title = 'Choose Mode',
    disabled = false,
    compact = false,
}: SelectProps) => {
    const [open, setOpen] = useState(false);
    const [openUpward, setOpenUpward] = useState(false);
    const [selected, setSelected] = useState<TSelectData | undefined>(undefined);
    const ref = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLDivElement>(null);

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

        const updatePlacement = () => {
            if (!triggerRef.current) return;

            const rect = triggerRef.current.getBoundingClientRect();
            const approxHeight = 340;
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;

            setOpenUpward(spaceBelow < approxHeight && spaceAbove > spaceBelow);
        };

        const handleOutside = (e: MouseEvent) => {
            if (!ref.current) return;
            if (e.target instanceof Node && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        };

        updatePlacement();
        window.addEventListener('resize', updatePlacement);
        window.addEventListener('scroll', updatePlacement, true);
        document.addEventListener('mousedown', handleOutside);
        return () => {
            window.removeEventListener('resize', updatePlacement);
            window.removeEventListener('scroll', updatePlacement, true);
            document.removeEventListener('mousedown', handleOutside);
        };
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
        <div className={['relative w-full', compact ? 'min-h-[40px]' : 'min-h-[48px]'].join(' ')} ref={ref}>
            <div
                ref={triggerRef}
                onClick={() => !disabled && setOpen((v) => !v)}
                className={[
                    'w-full overflow-hidden border border-[color:var(--border)] bg-[color:var(--card)] shadow-sm',
                    compact ? 'rounded-xl' : 'rounded-[30px]',
                    disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                ].join(' ')}
            >
                <SelectItem item={selectedItem} noDescription order={selectedItem?.value} compact={compact} />
            </div>

            <AnimatePresence>
                {open && (
                    <motion.div
                        initial={{ opacity: 0, y: openUpward ? 6 : -6 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: openUpward ? 6 : -6 }}
                        transition={{ duration: 0.18, ease: 'easeOut' }}
                        className={[
                            'absolute left-0 z-[80] w-full overflow-hidden border border-[color:var(--border)] bg-[color:var(--card)] py-2 shadow-[0_16px_28px_rgba(0,0,0,0.45)]',
                            compact ? 'min-w-[180px] rounded-xl' : 'min-w-[220px] rounded-[20px]',
                            openUpward ? 'bottom-[calc(100%+0.5rem)]' : 'top-[calc(100%+0.5rem)]',
                        ].join(' ')}
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
                                    compact={compact}
                                />
                            ))}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
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
            <strong className='text-xs font-semibold uppercase tracking-wide text-[color:var(--foreground)]'>{title}</strong>
            <button
                type='button'
                onClick={() => setOpen(false)}
                className='flex h-6 w-6 items-center justify-center rounded-full bg-[color:var(--background)] text-[color:var(--foreground)] hover:bg-[color:var(--accent)]'
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
    compact?: boolean;
};

const SelectItem = ({
    item,
    noDescription = true,
    order,
    onChange,
    animationOrder = 0,
    compact = false,
}: SelectItemProps) => {
    return (
        <motion.div
            className={[
                'group flex cursor-pointer items-center justify-between gap-2 transition-colors hover:bg-[color:var(--accent)]',
                compact ? 'px-2.5 py-1.5' : 'p-4 py-2',
                noDescription ? '!p-2' : '',
                item?.disabled ? 'cursor-not-allowed opacity-50' : '',
            ].join(' ')}
            initial='hidden'
            animate='visible'
            exit='exit'
            variants={animation}
            custom={animationOrder}
            onClick={() => (!item?.disabled ? onChange?.(order || '') : null)}
        >
            <div className='flex min-w-0 items-center gap-3'>
                <motion.div
                    layout
                    layoutId={`icon-${item?.id}`}
                    className={[
                        'flex shrink-0 items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--background)] text-[color:var(--primary)] aspect-square',
                        compact ? 'h-8 w-8 min-h-8 min-w-8 text-[11px]' : 'h-10 w-10 min-h-10 min-w-10',
                    ].join(' ')}
                >
                    {item?.icon}
                </motion.div>
                <motion.div layout className={['flex min-w-0 flex-col', compact ? 'w-40' : 'w-56'].join(' ')}>
                    <motion.strong
                        layoutId={`label-${item?.id}`}
                        className={[
                            'truncate font-semibold uppercase tracking-wide text-[color:var(--foreground)]',
                            compact ? 'text-[11px]' : 'text-xs',
                        ].join(' ')}
                    >
                        {item?.label}
                    </motion.strong>
                    {!noDescription && item?.description ? (
                        <span className='truncate text-[11px] text-[color:var(--muted-foreground)]'>{item.description}</span>
                    ) : null}
                </motion.div>
            </div>
            {noDescription ? (
                <motion.div layout className='flex items-center justify-center gap-2 pr-3'>
                    <ChevronDownIcon className='text-[color:var(--primary)]' size={compact ? 16 : 20} />
                </motion.div>
            ) : null}
        </motion.div>
    );
};

const animation = {
    hidden: {
        opacity: 0,
        y: 10,
    },
    visible: {
        opacity: 1,
        y: 0,
        transition: (custom: number) => ({
            delay: custom * 0.06,
            duration: 0.24,
        }),
    },
    exit: {
        opacity: 0,
        y: 10,
        transition: (custom: number) => ({
            delay: custom * 0.02,
        }),
    },
};

export default Select;
