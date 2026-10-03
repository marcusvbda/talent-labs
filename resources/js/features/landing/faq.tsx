import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import type { LandingLegal } from './types';
import { useReveal } from './use-reveal';

const Reveal = ({
    index,
    className,
    children,
}: {
    index: number;
    className?: string;
    children: ReactNode;
}) => {
    const reveal = useReveal<HTMLDivElement>(index);

    return (
        <div
            ref={reveal.ref}
            style={reveal.style}
            className={cn(reveal.className, className)}
        >
            {children}
        </div>
    );
};

const FaqItem = ({
    id,
    question,
    answer,
    open,
    onToggle,
}: {
    id: string;
    question: string;
    answer: string;
    open: boolean;
    onToggle: () => void;
}) => (
    <div className="rounded-card-sm bg-card px-6 transition-transform motion-safe:hover:-translate-y-1">
        <h3>
            <button
                type="button"
                id={`${id}-button`}
                aria-expanded={open}
                aria-controls={`${id}-panel`}
                onClick={onToggle}
                className="flex w-full items-center justify-between gap-4 rounded-row py-4 text-left text-row-title text-ink focus-visible:focus-ring"
            >
                {question}
                <ChevronDown
                    aria-hidden="true"
                    className={cn(
                        'size-5 shrink-0 text-muted transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>
        </h3>
        <div
            id={`${id}-panel`}
            role="region"
            aria-labelledby={`${id}-button`}
            data-open={open}
            inert={!open}
            className="accordion-panel"
        >
            <div className="overflow-hidden">
                <p className="pb-4 text-landing-body text-muted">{answer}</p>
            </div>
        </div>
    </div>
);

export function Faq({ legal }: { legal: LandingLegal }) {
    const { t } = useT();
    const items = legal.optOut ? [1, 2, 3, 4, 5] : [1, 2, 3, 4];
    const [openItems, setOpenItems] = useState<number[]>([1]);

    const toggle = (item: number) =>
        setOpenItems((current) =>
            current.includes(item)
                ? current.filter((value) => value !== item)
                : [...current, item],
        );

    return (
        <section aria-labelledby="faq-title" className="py-12 md:py-16">
            <div className="flex flex-col items-start gap-5">
                <Reveal index={0}>
                    <p className="text-label text-accent">
                        {t('landing.faq.eyebrow')}
                    </p>
                </Reveal>
                <Reveal index={1}>
                    <h2
                        id="faq-title"
                        className="text-landing-section-sm text-ink md:text-landing-section"
                    >
                        {t('landing.faq.title')}
                    </h2>
                </Reveal>
            </div>
            <div className="mt-8 flex flex-col gap-3">
                {items.map((item) => (
                    <FaqItem
                        key={item}
                        id={`faq-${item}`}
                        question={t(`landing.faq.${item}.q`)}
                        answer={t(`landing.faq.${item}.a`)}
                        open={openItems.includes(item)}
                        onToggle={() => toggle(item)}
                    />
                ))}
            </div>
        </section>
    );
}
