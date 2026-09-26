import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { IconButton } from '@/components/ui/icon-button';
import { Pill } from '@/components/ui/pill';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

type HeroStat = { label: string; value: string };

const CornerArrow = ({ href, label }: { href?: string; label: string }) =>
    href ? (
        <Link
            href={href}
            aria-label={label}
            className="inline-grid size-control-sm place-items-center rounded-full bg-white text-ink transition-colors hover:bg-tile focus-visible:focus-ring"
        >
            <ArrowUpRight size={20} strokeWidth={1.8} aria-hidden="true" />
        </Link>
    ) : (
        <IconButton icon={ArrowUpRight} label={label} bg="white" />
    );

export function HeroCard({
    label,
    icon,
    value,
    suffix,
    bars,
    barLabels,
    caption,
    stats,
    href,
    loading = false,
}: {
    label: string;
    icon: LucideIcon;
    value: string;
    suffix?: string;
    bars: number[];
    barLabels?: string[];
    caption: string;
    stats: HeroStat[];
    href?: string;
    loading?: boolean;
}) {
    const { t } = useT();
    const max = Math.max(...bars, 1);

    return (
        <Card tone="hero-accent" as="article">
            <div className="flex items-center justify-between gap-4">
                <Pill icon={icon} tone="white-on-accent">
                    {label}
                </Pill>
                <CornerArrow href={href} label={t('patterns.hero.open')} />
            </div>
            {loading ? (
                <div
                    className="mt-8 flex flex-col gap-4"
                    role="status"
                    aria-busy="true"
                    aria-label={t('common.loading')}
                >
                    <Skeleton shape="block" className="h-28 bg-white/30" />
                    <Skeleton shape="line" className="bg-white/30" />
                    <Skeleton shape="block" className="h-16 bg-white/30" />
                </div>
            ) : (
                <>
                    <div className="mt-6 flex flex-wrap items-end justify-between gap-4 md:mt-8">
                        <div className="flex items-baseline gap-2 md:gap-3">
                            <span className="text-hero-numeral-sm md:text-hero-numeral">
                                {value}
                            </span>
                            {suffix && (
                                <span className="text-hero-suffix-sm text-ink/80 md:text-hero-suffix">
                                    {suffix}
                                </span>
                            )}
                        </div>
                        <div aria-hidden="true" className="flex flex-col gap-2">
                            <div className="flex h-28 items-end gap-1.5 md:h-36 md:gap-2.5">
                                {bars.map((bar, index) => (
                                    <i
                                        key={index}
                                        style={{
                                            height: `${(bar / max) * 100}%`,
                                        }}
                                        className={cn(
                                            'w-4 rounded-t-bar-top rounded-b-md md:w-6',
                                            index === bars.length - 1
                                                ? 'bg-accent-soft'
                                                : 'bg-hatched',
                                        )}
                                    />
                                ))}
                            </div>
                            {barLabels && (
                                <div className="flex gap-1.5 text-chip text-ink/80 md:gap-2.5">
                                    {barLabels.map((barLabel, index) => (
                                        <span
                                            key={index}
                                            className="flex w-4 justify-center md:w-6"
                                        >
                                            <span className="shrink-0 whitespace-nowrap">
                                                {barLabel}
                                            </span>
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                    <p className="mt-5 text-label text-ink/90">{caption}</p>
                    <dl className="mt-5 grid grid-cols-3 rounded-tile border border-white/30 bg-ink/10">
                        {stats.map((stat, index) => (
                            <div
                                key={stat.label}
                                className={
                                    index > 0
                                        ? 'border-l border-white/30 px-3 py-3 md:px-5 md:py-4'
                                        : 'px-3 py-3 md:px-5 md:py-4'
                                }
                            >
                                <dt className="text-chip text-ink">
                                    {stat.label}
                                </dt>
                                <dd className="text-label font-medium md:text-card-title-sm">
                                    {stat.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </>
            )}
        </Card>
    );
}
