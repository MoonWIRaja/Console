import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import styles from './style.module.css';
import useFitText from 'use-fit-text';
import CopyOnClick from '@/components/elements/CopyOnClick';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    color?: string | undefined;
    icon: IconDefinition;
    children: React.ReactNode;
    className?: string;
}

export default ({ title, copyOnClick, icon, color, className, children }: StatBlockProps) => {
    const { fontSize, ref } = useFitText({ minFontSize: 8, maxFontSize: 500 });

    return (
        <CopyOnClick text={copyOnClick}>
            <div className={classNames(styles.stat_block, className)}>
                <div className={classNames(styles.status_bar, color || 'bg-gray-700')} />
                <div className={classNames(styles.icon, color || 'bg-gray-700')}>
                    <Icon icon={icon} className={'text-white'} />
                </div>
                <div className={'flex flex-col justify-center overflow-hidden w-full'}>
                    <p
                        className={
                            'font-header font-medium leading-tight text-xs md:text-sm text-gray-600 dark:text-gray-300'
                        }
                    >
                        {title}
                    </p>
                    <div
                        ref={ref}
                        className={'h-[1.75rem] w-full truncate font-semibold text-gray-900 dark:text-gray-100'}
                        style={{ fontSize }}
                    >
                        {children}
                    </div>
                </div>
            </div>
        </CopyOnClick>
    );
};
