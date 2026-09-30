import { Gauge, Mail, SlidersHorizontal, UserRound } from 'lucide-react';
import { FeatureTile } from '@/components/patterns/feature-tile';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { useReveal } from './use-reveal';

const GUARANTEES = [
    { n: 1, icon: Mail },
    { n: 2, icon: UserRound },
    { n: 3, icon: Gauge },
    { n: 4, icon: SlidersHorizontal },
] as const;

const Tile = ({
    index,
    n,
    icon,
}: {
    index: number;
    n: (typeof GUARANTEES)[number]['n'];
    icon: (typeof GUARANTEES)[number]['icon'];
}) => {
    const { t } = useT();
    const reveal = useReveal<HTMLLIElement>(index);

    return (
        <li
            ref={reveal.ref}
            style={reveal.style}
            className={cn(
                reveal.className,
                'transition-transform motion-safe:hover:-translate-y-1',
            )}
        >
            <FeatureTile
                icon={icon}
                title={t(`landing.guarantee.${n}.title`)}
                description={t(`landing.guarantee.${n}.desc`)}
            />
        </li>
    );
};

export function GuaranteesStrip() {
    const { t } = useT();

    return (
        <section aria-labelledby="guarantees-title" className="py-8">
            <h2 id="guarantees-title" className="sr-only">
                {t('landing.guarantee.title')}
            </h2>
            <ul className="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-gap">
                {GUARANTEES.map(({ n, icon }, index) => (
                    <Tile key={n} index={index} n={n} icon={icon} />
                ))}
            </ul>
        </section>
    );
}
