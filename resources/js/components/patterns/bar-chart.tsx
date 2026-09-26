import { BarChart3 } from 'lucide-react';
import { useId, useState } from 'react';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/i18n/i18n-provider';
import { useFormat } from '@/lib/format';

export type BarChartDatum = {
    label: string;
    value: number;
    isToday?: boolean;
};

const WIDTH = 530;
const HEIGHT = 410;
const LEFT = 34;
const BASE = 360;
const TOP = 22;
const MAX_BAR_WIDTH = 22;
const BAR_SLOT_RATIO = 0.62;
const RADIUS = 5;
const CAP = 3;
const STUB = 3;

const safeValue = (value: number) =>
    Number.isFinite(value) && value > 0 ? value : 0;

const niceMax = (max: number) => {
    if (max <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(max));
    const step = [1, 2, 2.5, 5, 10].find((s) => s * magnitude >= max) ?? 10;

    return step * magnitude;
};

const roundedTop = (x: number, y: number, w: number, h: number, r: number) => {
    const radius = Math.min(r, h, w / 2);

    return `M${x} ${y + h}V${y + radius}Q${x} ${y} ${x + radius} ${y}H${x + w - radius}Q${x + w} ${y} ${x + w} ${y + radius}V${y + h}Z`;
};

export function BarChart({
    data,
    title,
    valueFormatter,
    loading = false,
    empty = false,
}: {
    data: BarChartDatum[];
    title: string;
    valueFormatter?: (value: number) => string;
    loading?: boolean;
    empty?: boolean;
}) {
    const { t } = useT();
    const format = useFormat();
    const uid = useId().replace(/:/g, '');
    const [active, setActive] = useState<number | null>(null);

    if (loading) {
        return (
            <div
                role="status"
                aria-label={t('common.loading')}
                style={{ aspectRatio: `${WIDTH} / ${HEIGHT}` }}
            >
                <Skeleton shape="block" className="h-full rounded-card-sm" />
            </div>
        );
    }

    if (empty || data.length === 0) {
        return (
            <EmptyState
                icon={BarChart3}
                title={t('chart.empty.title')}
                description={t('chart.empty.description')}
            />
        );
    }

    const fmt = valueFormatter ?? ((value: number) => format.number(value));
    const values = data.map((d) => safeValue(d.value));
    const max = niceMax(Math.max(...values));
    const slot = (WIDTH - LEFT) / data.length;
    const barWidth = Math.min(MAX_BAR_WIDTH, slot * BAR_SLOT_RATIO);
    const y = (v: number) => BASE - (v / max) * (BASE - TOP);
    const grid = [0, max / 2, max];
    const neutralId = `${uid}-neutral`;
    const todayId = `${uid}-today`;
    const titleId = `${uid}-title`;
    const activeDatum = active === null ? null : data[active];
    const activeCx = active === null ? 0 : LEFT + slot * active + slot / 2;
    const activeY = active === null ? 0 : y(values[active]);

    return (
        <div className="relative">
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                role="group"
                aria-labelledby={titleId}
                className="block h-auto w-full overflow-visible"
            >
                <title id={titleId}>{title}</title>
                <defs>
                    <linearGradient id={neutralId} x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0"
                            style={{ stopColor: 'var(--color-bar-top)' }}
                        />
                        <stop
                            offset="1"
                            style={{ stopColor: 'var(--color-bar-bottom)' }}
                        />
                    </linearGradient>
                    <linearGradient id={todayId} x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0"
                            style={{
                                stopColor: 'var(--color-accent)',
                                stopOpacity: 0.55,
                            }}
                        />
                        <stop
                            offset="1"
                            style={{
                                stopColor: 'var(--color-accent)',
                                stopOpacity: 0.06,
                            }}
                        />
                    </linearGradient>
                </defs>
                {grid.map((g, index) => (
                    <g key={index}>
                        <line
                            x1={LEFT}
                            x2={WIDTH}
                            y1={y(g)}
                            y2={y(g)}
                            strokeWidth={1}
                            strokeDasharray={g === 0 ? undefined : '4 5'}
                            className="stroke-hairline"
                        />
                        <text
                            x={0}
                            y={y(g) + 4}
                            fontSize={12}
                            className="fill-muted"
                        >
                            {format.number(g)}
                        </text>
                    </g>
                ))}
                {data.map((d, i) => {
                    const v = values[i];
                    const cx = LEFT + slot * i + slot / 2;
                    const x = cx - barWidth / 2;
                    const top = y(v);
                    const today = Boolean(d.isToday);

                    return (
                        <g
                            key={i}
                            tabIndex={0}
                            role="img"
                            aria-label={`${d.label}: ${fmt(v)}`}
                            className="outline-none"
                            onMouseEnter={() => setActive(i)}
                            onMouseLeave={() => setActive(null)}
                            onFocus={() => setActive(i)}
                            onBlur={() => setActive(null)}
                        >
                            <rect
                                x={LEFT + slot * i}
                                y={0}
                                width={slot}
                                height={HEIGHT}
                                rx={RADIUS}
                                className={
                                    active === i
                                        ? 'fill-tile'
                                        : 'fill-transparent'
                                }
                            />
                            {v === 0 ? (
                                <rect
                                    x={x}
                                    y={BASE - STUB}
                                    width={barWidth}
                                    height={STUB}
                                    rx={CAP / 2}
                                    className="fill-bar-stub"
                                />
                            ) : (
                                <>
                                    <path
                                        d={roundedTop(
                                            x,
                                            top,
                                            barWidth,
                                            BASE - top,
                                            RADIUS,
                                        )}
                                        fill={`url(#${today ? todayId : neutralId})`}
                                    />
                                    <path
                                        d={roundedTop(
                                            x,
                                            top,
                                            barWidth,
                                            CAP,
                                            CAP / 2,
                                        )}
                                        className={
                                            today
                                                ? 'fill-accent'
                                                : 'fill-bar-cap'
                                        }
                                    />
                                </>
                            )}
                            {today && v > 0 && (
                                <text
                                    x={cx}
                                    y={top - 14}
                                    textAnchor="middle"
                                    fontSize={14}
                                    fontWeight={600}
                                    className="fill-accent-deep"
                                >
                                    {fmt(v)}
                                </text>
                            )}
                            <text
                                x={cx}
                                y={BASE + 26}
                                textAnchor="middle"
                                fontSize={12.5}
                                fontWeight={today ? 650 : undefined}
                                className={today ? 'fill-ink' : 'fill-muted'}
                            >
                                {d.label}
                            </text>
                        </g>
                    );
                })}
            </svg>
            {activeDatum && (
                <span
                    role="status"
                    style={{
                        left: `${(activeCx / WIDTH) * 100}%`,
                        top: `${(activeY / HEIGHT) * 100}%`,
                    }}
                    className="pointer-events-none absolute z-40 -mt-2 w-max -translate-x-1/2 -translate-y-full rounded-checkbox bg-ink px-3 py-2 text-chip text-white"
                >
                    {activeDatum.label} · {fmt(values[active ?? 0])}
                </span>
            )}
            <table className="sr-only">
                <caption>{title}</caption>
                <thead>
                    <tr>
                        <th scope="col">{t('chart.table.day')}</th>
                        <th scope="col">{t('chart.table.value')}</th>
                    </tr>
                </thead>
                <tbody>
                    {data.map((d, i) => (
                        <tr key={i}>
                            <th scope="row">{d.label}</th>
                            <td>{fmt(values[i])}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
