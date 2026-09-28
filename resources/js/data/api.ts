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

    const isForm = init.body instanceof FormData;

    // A FormData body is sent as-is: the browser sets the multipart boundary.
    if (hasBody && !isForm) {
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
        body: !hasBody
            ? undefined
            : isForm
              ? (init.body as FormData)
              : JSON.stringify(init.body),
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

export function apiUpload<T>(
    url: string,
    form: FormData,
    onProgress: (percent: number) => void,
    method: 'post' | 'put' | 'patch' = 'post',
): Promise<T> {
    return new Promise<T>((resolve, reject) => {
        const request = new XMLHttpRequest();

        request.open(method.toUpperCase(), url, true);
        request.withCredentials = true;
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        const token = xsrfToken();

        if (token) {
            request.setRequestHeader('X-XSRF-TOKEN', token);
        }

        request.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };

        request.onerror = () => {
            reject(new ApiError(0, 'Network error'));
        };

        request.onload = () => {
            if (request.status === 204) {
                resolve(undefined as T);

                return;
            }

            let payload: unknown;

            try {
                payload = request.responseText
                    ? JSON.parse(request.responseText)
                    : undefined;
            } catch {
                payload = undefined;
            }

            if (request.status < 200 || request.status >= 300) {
                const data = (payload ?? {}) as {
                    message?: unknown;
                    errors?: Record<string, string[]>;
                };
                const message =
                    typeof data.message === 'string' && data.message !== ''
                        ? data.message
                        : request.statusText ||
                          `Request failed with status ${request.status}`;

                reject(new ApiError(request.status, message, data.errors));

                return;
            }

            resolve(payload as T);
        };

        request.send(form);
    });
}
