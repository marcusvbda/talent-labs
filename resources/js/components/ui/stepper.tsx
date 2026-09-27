import { ProgressBar } from '@/components/ui/progress-bar';
import { StatusDisc } from '@/components/ui/status-disc';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

export type StepperStep = { key: string; label: string; done: boolean };

const DISC = 'size-8 text-label-sm';

/**
 * Horizontal step indicator. `onStepChange` receives the step INDEX and is
 * only wired to steps that are already done (they can be revisited).
 * Below `sm` it collapses to "Step N of M · label" plus the progress bar.
 */
export function Stepper({
    steps,
    currentIndex,
    onStepChange,
    ariaLabel,
}: {
    steps: StepperStep[];
    currentIndex: number;
    onStepChange?: (index: number) => void;
    ariaLabel: string;
}) {
    const { t } = useT();
    const current = steps[currentIndex];

    return (
        <nav aria-label={ariaLabel} className="flex min-w-0 flex-col gap-3">
            <p className="text-label font-medium sm:hidden">
                {t('patterns.stepper.progress', {
                    current: currentIndex + 1,
                    total: steps.length,
                })}
                {current && ` · ${current.label}`}
            </p>
            <ol className="hidden items-center gap-3 sm:flex">
                {steps.map((step, index) => {
                    const isCurrent = index === currentIndex;
                    const disc = step.done ? (
                        <StatusDisc
                            status="done"
                            tint="dark-done"
                            size="sm"
                            label={t('patterns.stepper.step', {
                                number: index + 1,
                            })}
                        />
                    ) : (
                        <span
                            aria-hidden="true"
                            className={cn(
                                DISC,
                                'inline-grid shrink-0 place-items-center rounded-full',
                                isCurrent
                                    ? 'bg-ink text-white'
                                    : 'bg-disc-neutral text-muted',
                            )}
                        >
                            {index + 1}
                        </span>
                    );
                    const content = (
                        <>
                            {disc}
                            <span
                                className={cn(
                                    'text-label-sm whitespace-nowrap',
                                    isCurrent
                                        ? 'font-medium text-ink'
                                        : 'text-muted',
                                )}
                            >
                                {step.label}
                            </span>
                        </>
                    );

                    return (
                        <li
                            key={step.key}
                            aria-current={isCurrent ? 'step' : undefined}
                            className={cn(
                                'flex items-center gap-3',
                                index < steps.length - 1 && 'min-w-0 flex-1',
                            )}
                        >
                            {step.done && onStepChange ? (
                                <button
                                    type="button"
                                    onClick={() => onStepChange(index)}
                                    className="flex cursor-pointer items-center gap-2 rounded-full focus-visible:focus-ring"
                                >
                                    {content}
                                </button>
                            ) : (
                                <span className="flex items-center gap-2">
                                    {content}
                                </span>
                            )}
                            {index < steps.length - 1 && (
                                <span
                                    aria-hidden="true"
                                    className="h-px min-w-4 flex-1 bg-hairline"
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
            <ProgressBar
                value={currentIndex}
                max={steps.length}
                label={t('patterns.stepper.progress', {
                    current: currentIndex + 1,
                    total: steps.length,
                })}
            />
        </nav>
    );
}
