import { Gauge, Mail, SlidersHorizontal, UserRound } from 'lucide-react';
import { FeatureTile } from '@/components/patterns/feature-tile';
import { useT } from '@/i18n/i18n-provider';
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
        <li ref={reveal.ref} style={reveal.style} className={reveal.className}>
            <FeatureTile
                icon={icon}
                title={t(`landing.guarantee.${n}.title`)}
                description={t(`landing.guarantee.${n}.desc`)}
            />
        </li>
    );
};

export function GuaranteesStrip() {
    return (
        <section className="py-8">
            <ul className="grid grid-cols-2 gap-4 md:gap-gap lg:grid-cols-4">
                {GUARANTEES.map(({ n, icon }, index) => (
                    <Tile key={n} index={index} n={n} icon={icon} />
                ))}
            </ul>
        </section>
    );
}
