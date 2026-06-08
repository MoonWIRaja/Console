import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { motion } from 'framer-motion';
import { Check, ChevronDownIcon, X } from 'lucide-react';
import { readThemeVariables, serverThemeStyle } from '@/components/server/serverTheme';

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
    value?: string;
    title?: string;
    disabled?: boolean;
    compact?: boolean;
    openDirection?: 'up' | 'down';
};

const calcPosition = (el: HTMLElement, openDirection: 'up' | 'down' = 'up') => {
    const rect = el.getBoundingClientRect();
    return {
        top: rect.top,
        bottom: rect.bottom,
        left: rect.left,
        width: rect.width,
        openUpward: openDirection === 'up',
    };
};

const Select = ({
    data = [],
    defaultValue,
    value,
    onChange,
    title = 'Choose Mode',
    disabled = false,
    compact = false,
    openDirection = 'up',
}: SelectProps) => {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<TSelectData | undefined>(undefined);
    const ref = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const currentValue = value ?? defaultValue;

        if (currentValue) {
            const item = data.find((i) => i.value === currentValue);
            if (item) {
                setSelected(item);
                return;
            }
        }

        setSelected(data[0]);
    }, [value, defaultValue, data]);

    useEffect(() => {
        if (!open) return;

        const handleOutside = (e: MouseEvent) => {
            if (!(e.target instanceof Node)) return;
            if (ref.current?.contains(e.target) || menuRef.current?.contains(e.target)) return;
            setOpen(false);
        };

        document.addEventListener('mousedown', handleOutside);
        return () => {
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

    const [portalPosition, setPortalPosition] = useState<{
        top: number;
        bottom: number;
        left: number;
        width: number;
        openUpward: boolean;
    } | null>(null);
    const [portalThemeVars, setPortalThemeVars] = useState<React.CSSProperties>(serverThemeStyle);

    const handleToggle = () => {
        if (disabled || data.length === 0) return;
        if (!open && triggerRef.current) {
            setPortalPosition(calcPosition(triggerRef.current, openDirection));
            setPortalThemeVars(readThemeVariables(triggerRef.current) as React.CSSProperties);
        }
        setOpen((v) => !v);
    };

    useEffect(() => {
        if (!open || !triggerRef.current) return;

        const updatePosition = () => {
            if (triggerRef.current) {
                setPortalPosition(calcPosition(triggerRef.current, openDirection));
            }
        };

        window.addEventListener('scroll', updatePosition, true);
        window.addEventListener('resize', updatePosition);
        return () => {
            window.removeEventListener('scroll', updatePosition, true);
            window.removeEventListener('resize', updatePosition);
        };
    }, [open]);

    return (
        <div className={['relative w-full', compact ? 'min-h-[40px]' : 'min-h-[48px]'].join(' ')} ref={ref}>
            <div
                ref={triggerRef}
                onClick={handleToggle}
                className={[
                    'w-full overflow-hidden transition-all duration-150',
                    compact ? 'rounded-xl' : 'rounded-[30px]',
                    disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                ].join(' ')}
                style={{
                    border: '2px solid #2D4A3E',
                    backgroundColor: '#F5EFD5',
                    boxShadow: open ? '2px 2px 0px 0px #2D4A3E' : '4px 4px 0px 0px #2D4A3E',
                }}
            >
                <SelectItem item={selectedItem} noDescription order={selectedItem?.value} compact={compact} />
            </div>

            {open &&
                createPortal(
                    <div
                        ref={menuRef}
                        style={{
                            ...portalThemeVars,
                            position: 'fixed',
                            zIndex: 10001,
                            ...(portalPosition?.openUpward
                                ? { bottom: portalPosition ? `${window.innerHeight - portalPosition.top + 6}px` : '9999px' }
                                : { top: portalPosition ? `${portalPosition.bottom + 6}px` : '9999px' }),
                            left: portalPosition ? `${portalPosition.left}px` : '0',
                            width: portalPosition ? `${portalPosition.width}px` : '100%',
                        }}
                    >
                        <motion.div
                            initial={{ opacity: 0, scale: 0.97 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: 0.97 }}
                            transition={{ duration: 0.15, ease: 'easeOut' }}
                            className={compact ? 'min-w-[180px] rounded-xl' : 'min-w-[220px] rounded-[20px]'}
                            style={{
                                overflow: 'hidden',
                                paddingTop: '8px',
                                paddingBottom: '8px',
                                border: '2px solid #2D4A3E',
                                backgroundColor: '#FEF9E1',
                                backgroundImage: [
                                    'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                    'repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                    'repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                ].join(', '),
                                boxShadow: '4px 4px 0px 0px #2D4A3E',
                                transformOrigin: portalPosition?.openUpward ? 'bottom center' : 'top center',
                            }}
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
                                        isSelected={selectedItem?.id === item.id}
                                    />
                                ))}
                            </div>
                        </motion.div>
                    </div>,
                    document.body
                )}
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
            className='flex items-center justify-between px-4 pb-2 pt-1'
            style={{ borderBottom: '2px solid #2D4A3E' }}
        >
            <strong style={{ color: '#2D4A3E', fontSize: '10px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.1em' }}>
                {title}
            </strong>
            <button
                type='button'
                onClick={() => setOpen(false)}
                className='flex h-5 w-5 items-center justify-center rounded-full transition-colors'
                style={{ background: 'rgba(45,74,62,0.1)', color: '#2D4A3E' }}
            >
                <X size={10} />
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
    isSelected?: boolean;
};

const SelectItem = ({
    item,
    noDescription = true,
    order,
    onChange,
    animationOrder = 0,
    compact = false,
    isSelected = false,
}: SelectItemProps) => {
    const isBooleanOn = item?.value === 'on';
    const isBooleanOff = item?.value === 'off';
    const booleanIndicatorStyle = isBooleanOn
        ? { borderColor: '#166534', backgroundColor: '#22c55e', color: '#052e16' }
        : isBooleanOff
            ? { borderColor: '#7f1d1d', backgroundColor: '#ef4444', color: '#450a0a' }
            : { border: '1px solid #2D4A3E', backgroundColor: '#EDE6D0', color: '#2D4A3E' };

    return (
        <motion.div
            className={[
                'group flex cursor-pointer items-center justify-between gap-2 transition-all duration-150',
                compact ? 'px-2.5 py-1.5' : 'p-4 py-2',
                noDescription ? '!p-2' : '',
                item?.disabled ? 'cursor-not-allowed opacity-50' : '',
            ].join(' ')}
            style={{
                backgroundColor: !noDescription && isSelected ? '#EDE6D0' : undefined,
            }}
            onMouseEnter={(e) => { if (!item?.disabled) (e.currentTarget as HTMLDivElement).style.backgroundColor = '#EDE6D0'; }}
            onMouseLeave={(e) => { (e.currentTarget as HTMLDivElement).style.backgroundColor = !noDescription && isSelected ? '#EDE6D0' : ''; }}
            initial='hidden'
            animate='visible'
            exit='exit'
            variants={animation}
            custom={animationOrder}
            onClick={() => (!item?.disabled ? onChange?.(order || '') : null)}
        >
            <div className='flex min-w-0 items-center gap-3'>
                <div
                    className={[
                        'flex shrink-0 items-center justify-center rounded-full aspect-square',
                        compact ? 'h-8 w-8 min-h-8 min-w-8 text-[11px]' : 'h-10 w-10 min-h-10 min-w-10',
                    ].join(' ')}
                    style={booleanIndicatorStyle}
                >
                    {item?.icon}
                </div>
                <div className={['flex min-w-0 flex-col', compact ? 'w-40' : 'w-56'].join(' ')}>
                    <strong
                        className={[
                            'truncate font-semibold uppercase tracking-wide text-[color:var(--foreground)]',
                            compact ? 'text-[11px]' : 'text-xs',
                        ].join(' ')}
                    >
                        {item?.label}
                    </strong>
                    {!noDescription && item?.description ? (
                        <span className='truncate text-[11px] text-[color:var(--muted-foreground)]'>
                            {item.description}
                        </span>
                    ) : null}
                </div>
            </div>
            {noDescription ? (
                <div className='flex items-center justify-center gap-2 pr-3'>
                    <ChevronDownIcon className='text-[color:var(--primary)]' size={compact ? 16 : 20} />
                </div>
            ) : isSelected ? (
                <div className='flex items-center justify-center pr-1'>
                    <Check className='text-[color:var(--primary)]' size={compact ? 13 : 15} />
                </div>
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
