import { StatusDisc } from '@/components/ui/status-disc';
import { cn } from '@/lib/utils';

export type LiveStep = { key: string; label: string };

export function LiveStepper({
    steps,
    activeIndex,
    failedIndex,
    subStep,
    tone = 'dark',
}: {
    steps: LiveStep[];
    activeIndex: number;
    failedIndex?: number;
    subStep?: string;
    tone?: 'dark';
}) {
    const reached = failedIndex ?? activeIndex;

    return (
        <ol data-tone={tone} className="flex w-full items-start">
            {steps.map((step, index) => {
                const failed = index === failedIndex;
                const active = !failed && index === activeIndex;
                const done = !failed && index < activeIndex;

                return (
                    <li
                        key={step.key}
                        aria-current={active ? 'step' : undefined}
                        className="relative flex min-w-0 flex-1 flex-col items-center gap-2 text-center"
                    >
                        {index < steps.length - 1 && (
                            <span
                                aria-hidden="true"
                                className="absolute top-4 left-1/2 h-0.5 w-full -translate-y-1/2 overflow-hidden bg-dark-line"
                            >
                                <span
                                    className={cn(
                                        'block size-full origin-left bg-accent transition-transform duration-300 ease-out',
                                        index < reached
                                            ? 'scale-x-100'
                                            : 'scale-x-0',
                                    )}
                                />
                            </span>
                        )}
                        <span className="relative rounded-full bg-dark">
                            <StatusDisc
                                size="sm"
                                status={
                                    failed
                                        ? 'failed'
                                        : done
                                          ? 'done'
                                          : active
                                            ? 'sending'
                                            : 'waiting'
                                }
                                tint={
                                    failed
                                        ? 'red'
                                        : done
                                          ? 'dark-done'
                                          : active
                                            ? 'dark-active'
                                            : 'dark-idle'
                                }
                            />
                        </span>
                        <span
                            className={cn(
                                'text-label-sm',
                                active || failed
                                    ? 'text-white'
                                    : 'text-dark-muted',
                            )}
                        >
                            {step.label}
                        </span>
                        {active && subStep && (
                            <span className="text-body whitespace-nowrap text-accent-line">
                                {subStep}
                            </span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
