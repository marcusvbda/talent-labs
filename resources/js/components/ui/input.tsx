import { forwardRef } from 'react';
import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type InputSize = 'lg' | 'md' | 'sm';

const SIZES: Record<InputSize, string> = {
    lg: 'h-control-lg px-7',
    md: 'h-control-md px-6',
    sm: 'h-control-sm px-5',
};

export const CONTROL_BASE =
    'w-full rounded-tile bg-tile text-body text-ink placeholder:text-faint outline-2 outline-transparent transition-colors focus-visible:focus-ring disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:outline-danger aria-invalid:bg-danger-bg';

export const Input = forwardRef<
    HTMLInputElement,
    Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> & { size?: InputSize }
>(({ size = 'md', className, type = 'text', ...props }, ref) => (
    <input
        ref={ref}
        type={type}
        className={cn(CONTROL_BASE, SIZES[size], className)}
        {...props}
    />
));

Input.displayName = 'Input';
