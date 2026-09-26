// Spec B.7 rule: a page hook takes the Inertia prop of the same shape and
// passes it as `initialData` when defined, so the first render needs no fetch
// and later refreshes go through the query.
export function initialDataFrom<T>(
    prop: T | null | undefined,
): { initialData: T } | Record<string, never> {
    return prop === undefined || prop === null ? {} : { initialData: prop };
}
