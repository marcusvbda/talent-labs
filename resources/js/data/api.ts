export class ApiError extends Error {
    status: number;
    errors?: Record<string, string[]>;

    constructor(
        status: number,
        message: string,
        errors?: Record<string, string[]>,
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

type ApiInit = {
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    body?: unknown;
    signal?: AbortSignal;
};

const xsrfToken = (): string | null => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
};

export async function apiFetch<T>(url: string, init: ApiInit = {}): Promise<T> {
    const hasBody = init.body !== undefined;
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (hasBody) {
        headers['Content-Type'] = 'application/json';
    }

    const token = xsrfToken();

    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    const response = await fetch(url, {
        method: (init.method ?? 'get').toUpperCase(),
        credentials: 'same-origin',
        headers,
        body: hasBody ? JSON.stringify(init.body) : undefined,
        signal: init.signal,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload: unknown = await response.json().catch(() => undefined);

    if (!response.ok) {
        const data = (payload ?? {}) as {
            message?: unknown;
            errors?: Record<string, string[]>;
        };
        const message =
            typeof data.message === 'string' && data.message !== ''
                ? data.message
                : response.statusText ||
                  `Request failed with status ${response.status}`;

        throw new ApiError(response.status, message, data.errors);
    }

    return payload as T;
}
