import * as React from 'react';
import { ArrowDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAutoScroll } from '@/components/hooks/use-auto-scroll';
import { cn } from '@/lib/utils';

interface ChatMessageListProps extends React.HTMLAttributes<HTMLDivElement> {
    smooth?: boolean;
}

const ChatMessageList = React.forwardRef<HTMLDivElement, ChatMessageListProps>(
    ({ className, children, smooth = false, ...props }, _ref) => {
        const { scrollRef, isAtBottom, scrollToBottom, disableAutoScroll } = useAutoScroll({
            smooth,
            content: children,
        });

        return (
            <div className={'relative h-full w-full'}>
                <div
                    ref={scrollRef}
                    className={cn('flex h-full w-full flex-col overflow-y-auto px-4 py-5 sm:px-6', className)}
                    onTouchMove={disableAutoScroll}
                    onWheel={disableAutoScroll}
                    {...props}
                >
                    <div className={'flex flex-col gap-4'}>{children}</div>
                </div>

                {!isAtBottom ? (
                    <Button
                        type={'button'}
                        variant={'ghost'}
                        size={'icon'}
                        onClick={() => scrollToBottom()}
                        aria-label={'Scroll to latest message'}
                        className={
                            'absolute bottom-3 left-1/2 h-10 w-10 -translate-x-1/2 rounded-full border border-[rgba(var(--primary-rgb),0.24)] bg-[rgba(4,8,14,0.92)] text-[#f8f6ef] shadow-[0_18px_30px_rgba(0,0,0,0.35)] hover:bg-[rgba(8,14,22,0.98)]'
                        }
                    >
                        <ArrowDown className={'h-4 w-4'} />
                    </Button>
                ) : null}
            </div>
        );
    }
);

ChatMessageList.displayName = 'ChatMessageList';

export { ChatMessageList };
