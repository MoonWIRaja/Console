import React, { useRef, useState } from 'react';
import { Dialog as HDialog } from '@headlessui/react';
import { Button } from '@/components/elements/button/index';
import { XIcon } from '@heroicons/react/solid';
import { AnimatePresence, motion } from 'framer-motion';
import { DialogContext, IconPosition, RenderDialogProps, styles } from './';

const variants = {
    open: {
        scale: 1,
        opacity: 1,
        transition: {
            type: 'spring',
            damping: 15,
            stiffness: 300,
            duration: 0.15,
        },
    },
    closed: {
        scale: 0.75,
        opacity: 0,
        transition: {
            type: 'easeIn',
            duration: 0.15,
        },
    },
    bounce: {
        scale: 0.95,
        opacity: 1,
        transition: { type: 'linear', duration: 0.075 },
    },
};

export default ({
    open,
    title,
    description,
    onClose,
    hideCloseIcon,
    preventExternalClose,
    panelClassName,
    contentClassName,
    scrollWrapperClassName,
    children,
}: RenderDialogProps) => {
    const container = useRef<HTMLDivElement>(null);
    const [icon, setIcon] = useState<React.ReactNode>();
    const [footer, setFooter] = useState<React.ReactNode>();
    const [iconPosition, setIconPosition] = useState<IconPosition>('title');
    const [down, setDown] = useState(false);
    const hasBody = React.Children.count(children) > 0 || (iconPosition === 'container' && !!icon);

    const onContainerClick = (down: boolean, e: React.MouseEvent<HTMLDivElement>): void => {
        if (e.target instanceof HTMLElement && container.current?.isSameNode(e.target)) {
            setDown(down);
        }
    };

    const onDialogClose = (): void => {
        if (!preventExternalClose) {
            return onClose();
        }
    };

    return (
        <AnimatePresence>
            {open && (
                <DialogContext.Provider value={{ setIcon, setFooter, setIconPosition }}>
                    <HDialog
                        static
                        as={motion.div}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.15 }}
                        open={open}
                        onClose={onDialogClose}
                    >
                        <div className={'fixed inset-0 z-[90] bg-[rgba(84,63,38,0.34)] backdrop-blur-md'} />
                        <div
                            className={['fixed inset-0 z-[100] overflow-y-auto', scrollWrapperClassName || '']
                                .join(' ')
                                .trim()}
                        >
                            <div
                                ref={container}
                                className={styles.container}
                                onMouseDown={onContainerClick.bind(this, true)}
                                onMouseUp={onContainerClick.bind(this, false)}
                            >
                                <HDialog.Panel
                                    as={motion.div}
                                    initial={'closed'}
                                    animate={down ? 'bounce' : 'open'}
                                    exit={'closed'}
                                    variants={variants}
                                    className={[styles.panel, panelClassName || ''].join(' ').trim()}
                                >
                                    <div
                                        className={[
                                            'bg-transparent px-5 pt-5 md:px-6 md:pt-6',
                                            hasBody ? 'pb-3' : 'pb-5',
                                        ]
                                            .join(' ')
                                            .trim()}
                                    >
                                        <div className={'flex items-start justify-between gap-4'}>
                                            <div className={'min-w-0 flex-1'}>
                                                <div className={'flex items-center'}>
                                                    {iconPosition !== 'container' && icon}
                                                    <div className={'min-w-0'}>
                                                        {title && (
                                                            <HDialog.Title className={styles.title}>
                                                                {title}
                                                            </HDialog.Title>
                                                        )}
                                                        {description && (
                                                            <HDialog.Description
                                                                className={'text-sm text-[color:var(--text-subtle)]'}
                                                            >
                                                                {description}
                                                            </HDialog.Description>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            {!hideCloseIcon && (
                                                <Button.Text
                                                    size={Button.Sizes.Small}
                                                    shape={Button.Shapes.IconSquare}
                                                    type={'button'}
                                                    onClick={onClose}
                                                    className={
                                                        'group !rounded-[10px] !border !border-[#2D4A3E] !bg-[#F5EFD5] !text-[#742220] hover:!border-[#2D4A3E] hover:!bg-[rgba(45,74,62,0.12)] hover:!text-[#2D4A3E] focus:!ring-[#2D4A3E] focus:!ring-offset-[#FEF9E1]'
                                                    }
                                                >
                                                    <XIcon className={styles.close_icon} />
                                                </Button.Text>
                                            )}
                                        </div>
                                    </div>
                                    {hasBody && (
                                        <div className={'flex overflow-y-auto px-5 pb-4 pt-1 md:px-6'}>
                                            {iconPosition === 'container' && icon}
                                            <div
                                                className={[
                                                    'flex-1 min-w-0',
                                                    contentClassName || 'max-h-[calc(100vh-14rem)] overflow-y-auto',
                                                ]
                                                    .join(' ')
                                                    .trim()}
                                            >
                                                {children}
                                            </div>
                                        </div>
                                    )}
                                    {footer}
                                </HDialog.Panel>
                            </div>
                        </div>
                    </HDialog>
                </DialogContext.Provider>
            )}
        </AnimatePresence>
    );
};
