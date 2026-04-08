import * as React from 'react';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type ChatInputProps = React.TextareaHTMLAttributes<HTMLTextAreaElement>;

const ChatInput = React.forwardRef<HTMLTextAreaElement, ChatInputProps>(({ className, ...props }, ref) => (
    <Textarea
        ref={ref}
        autoComplete={'off'}
        name={'message'}
        className={cn(
            'min-h-[7.5rem] resize-none border-0 bg-transparent px-0 py-0 text-sm text-[#f8f6ef]',
            'focus:bg-transparent focus:ring-0',
            className
        )}
        {...props}
    />
));

ChatInput.displayName = 'ChatInput';

export { ChatInput };
