const API_BASE = '/api';

let csrfInitialized = false;
let csrfPromise: Promise<void> | null = null;

export async function initializeCsrf(): Promise<void> {
    if (csrfInitialized) {
        return;
    }

    if (csrfPromise) {
        return csrfPromise;
    }

    csrfPromise = (async () => {
        const response = await fetch('/sanctum/csrf-cookie', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to initialize CSRF protection.');
        }

        csrfInitialized = true;
    })();

    try {
        await csrfPromise;
    } finally {
        csrfPromise = null;
    }
}

export function getCsrfToken(): string {
    const match = document.cookie.match(
        /(?:^|;\s*)XSRF-TOKEN=([^;]*)/
    );

    return match ? decodeURIComponent(match[1]) : '';
}

export async function apiFetch(
    path: string,
    options: RequestInit = {}
): Promise<Response> {
    const method = (options.method ?? 'GET').toUpperCase();

    const headers = new Headers(options.headers);

    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const isSafeMethod = ['GET', 'HEAD', 'OPTIONS'].includes(method);

    if (!isSafeMethod) {
        await initializeCsrf();

        const csrfToken = getCsrfToken();

        if (csrfToken) {
            headers.set('X-XSRF-TOKEN', csrfToken);
        }
    }

    return fetch(`${API_BASE}${path}`, {
        ...options,
        method,
        headers,
        credentials: 'same-origin',
    });
}

export async function logoutUser(): Promise<void> {
    const response = await apiFetch('/logout', {
        method: 'POST',
    });

    if (!response.ok) {
        const result = await response.json().catch(() => ({}));

        throw new Error(
            result.message || 'Unable to log out. Please try again.',
        );
    }
}

export function resetCsrfForTests(): void {
    csrfInitialized = false;
    csrfPromise = null;
}