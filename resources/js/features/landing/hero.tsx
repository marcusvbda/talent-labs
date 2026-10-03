import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Pill } from '@/components/ui/pill';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { BetaCta } from './beta-cta';
import { anchorHash, scrollToHash } from './scroll-to-hash';
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

export function Hero({
    betaClosed,
    demo,
}: {
    betaClosed: boolean;
    demo?: ReactNode;
}) {
    const { t } = useT();

    return (
        <section
            aria-labelledby="hero-title"
            className={cn(
                'grid grid-cols-1 items-start gap-gap py-12 md:py-16',
                demo && 'xl:grid-cols-[1fr_1.5fr]',
            )}
        >
            <div className="flex flex-col items-start gap-6">
                <Reveal index={0}>
                    <Pill icon={Lock} tone="tile">
                        {t('landing.hero.badge')}
                    </Pill>
                </Reveal>
                <Reveal index={1}>
                    <h1
                        id="hero-title"
                        className="text-landing-hero-sm text-ink md:text-landing-hero"
                    >
                        {t('landing.hero.title.a')}{' '}
                        <em className="text-accent not-italic">
                            {t('landing.hero.title.em')}
                        </em>
                        {t('landing.hero.title.b')}
                    </h1>
                </Reveal>
                <Reveal index={2}>
                    <p className="max-w-2xl text-landing-lead text-muted">
                        {t('landing.hero.lead')}
                    </p>
                </Reveal>
                <Reveal index={3} className="flex flex-wrap gap-3">
                    <BetaCta betaClosed={betaClosed} size="lg" />
                    <div
                        onClick={(event) => {
                            const hash = anchorHash(event);

                            if (hash !== null && scrollToHash(hash)) {
                                event.preventDefault();
                            }
                        }}
                    >
                        <Button variant="secondary-tile" size="lg" href="#how">
                            {t('landing.hero.secondary')}
                        </Button>
                    </div>
                </Reveal>
            </div>
            {demo && <div className="min-w-0">{demo}</div>}
        </section>
    );
}
