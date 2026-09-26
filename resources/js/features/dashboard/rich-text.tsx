import { useT } from '@/i18n/i18n-provider';

const MARK = '\u0001';

/** Renders a translation with its `:params` emphasised (bold), in any word order. */
export function RichText({
    id,
    values,
}: {
    id: string;
    values: Record<string, string | number>;
}) {
    const { t } = useT();
    const tokens = Object.fromEntries(
        Object.keys(values).map((name) => [name, `${MARK}${name}${MARK}`]),
    );

    return (
        <>
            {t(id, tokens)
                .split(MARK)
                .map((part, index) =>
                    index % 2 === 1 ? <b key={index}>{values[part]}</b> : part,
                )}
        </>
    );
}
