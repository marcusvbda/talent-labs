import { forwardRef } from 'react';
import type { TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';
import { CONTROL_BASE } from './input';

export const Textarea = forwardRef<
    HTMLTextAreaElement,
    TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, rows = 4, ...props }, ref) => (
    <textarea
        ref={ref}
        rows={rows}
        className={cn(
            CONTROL_BASE,
            'min-h-control-lg resize-y px-6 py-4',
            className,
        )}
        {...props}
    />
));

Textarea.displayName = 'Textarea';
