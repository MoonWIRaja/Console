'use client';

import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import { cn } from '@/lib/utils';

type FloatingActionMenuProps = {
    options: {
        label: string;
        onClick: () => void;
        Icon?: React.ReactNode;
    }[];
    className?: string;
    openOnHover?: boolean;
    onPrimaryClick?: () => void;
};

const FloatingActionMenu = ({
    options,
    className,
    openOnHover = false,
    onPrimaryClick,
}: FloatingActionMenuProps) => {
    const [isOpen, setIsOpen] = useState(false);

    const toggleMenu = () => {
        setIsOpen((current) => !current);
    };

    return (
        <div
            className={cn('fixed bottom-8 right-8 z-[1200]', className)}
            onMouseEnter={openOnHover ? () => setIsOpen(true) : undefined}
            onMouseLeave={openOnHover ? () => setIsOpen(false) : undefined}
        >
            <Button
                onClick={onPrimaryClick || toggleMenu}
                size={'icon'}
                className={
                    '!h-14 !w-14 !rounded-full p-0 border border-[color:var(--border,#1f2a14)] bg-[color:var(--card,#0C0C0C)] ' +
                    'text-[color:var(--foreground,#f8f6ef)] shadow-[0_10px_30px_rgba(0,0,0,0.35)] ' +
                    'hover:scale-105 hover:bg-[color:var(--accent,#1f2a14)]'
                }
            >
                <motion.div
                    animate={{ rotate: isOpen ? 45 : 0 }}
                    transition={{
                        duration: 0.3,
                        type: 'spring',
                        stiffness: 300,
                        damping: 20,
                    }}
                >
                    <Plus className='h-6 w-6' />
                </motion.div>
            </Button>

            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, x: 10, y: 10, filter: 'blur(10px)' }}
                        animate={{ opacity: 1, x: 0, y: 0, filter: 'blur(0px)' }}
                        exit={{ opacity: 0, x: 10, y: 10, filter: 'blur(10px)' }}
                        transition={{
                            duration: 0.6,
                            type: 'spring',
                            stiffness: 300,
                            damping: 20,
                            delay: 0.1,
                        }}
                        className='absolute bottom-14 right-0 mb-2'
                    >
                        <div className='flex max-h-[65vh] min-w-[220px] flex-col items-stretch gap-2 overflow-y-auto rounded-2xl border border-[color:var(--border,#1f2a14)] bg-[color:var(--card,#0C0C0C)] p-2 pr-1 shadow-[0_14px_40px_rgba(0,0,0,0.45)] backdrop-blur-md'>
                            {options.map((option, index) => (
                                <motion.div
                                    key={`${option.label}-${index}`}
                                    initial={{ opacity: 0, x: 20 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    exit={{ opacity: 0, x: 20 }}
                                    transition={{
                                        duration: 0.3,
                                        delay: index * 0.05,
                                    }}
                                >
                                    <Button
                                        onClick={() => {
                                            option.onClick();
                                            setIsOpen(false);
                                        }}
                                        size='sm'
                                        className={
                                            'flex w-full items-center justify-start gap-2 rounded-xl border border-transparent ' +
                                            'bg-transparent px-3 py-2 text-[color:var(--foreground,#f8f6ef)] transition-all ' +
                                            'hover:border-[color:var(--border,#1f2a14)] hover:bg-[color:var(--accent,#1f2a14)]'
                                        }
                                    >
                                        {option.Icon}
                                        <span>{option.label}</span>
                                    </Button>
                                </motion.div>
                            ))}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
};

export default FloatingActionMenu;
