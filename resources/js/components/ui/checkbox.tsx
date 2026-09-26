import { Check, Minus } from 'lucide-react';
import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export const Checkbox = forwardRef<
    HTMLInputElement,
    Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'size'> & {
        indeterminate?: boolean;
    }
>(({ indeterminate = false, className, ...props }, ref) => {
    const inner = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => inner.current as HTMLInputElement);

    useEffect(() => {
        if (inner.current) {
            inner.current.indeterminate = indeterminate;
        }
    }, [indeterminate]);

    return (
        <span
            className={cn('relative inline-flex size-6.5 shrink-0', className)}
        >
            <input
                ref={inner}
                type="checkbox"
                aria-checked={indeterminate ? 'mixed' : undefined}
                className="peer size-full cursor-pointer appearance-none rounded-checkbox bg-tile outline-2 outline-transparent transition-colors checked:bg-accent indeterminate:bg-accent focus-visible:focus-ring disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:bg-danger-bg aria-invalid:outline-danger"
                {...props}
            />
            <Check
                aria-hidden="true"
                strokeWidth={1.8}
                className="pointer-events-none absolute inset-1 hidden size-4.5 text-white peer-checked:block peer-indeterminate:hidden"
            />
            <Minus
                aria-hidden="true"
                strokeWidth={1.8}
                className="pointer-events-none absolute inset-1 hidden size-4.5 text-white peer-indeterminate:block"
            />
        </span>
    );
});

Checkbox.displayName = 'Checkbox';
