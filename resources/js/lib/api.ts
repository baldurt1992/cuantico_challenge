export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

export async function requestJson<T>(
    url: string,
    options: RequestInit = {},
): Promise<T> {
    const token = document.cookie
        .split("; ")
        .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
        ?.slice("XSRF-TOKEN=".length);
    const response = await fetch(url, {
        ...options,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            ...(token ? { "X-XSRF-TOKEN": decodeURIComponent(token) } : {}),
            ...options.headers,
        },
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = body.errors as Record<string, string[]> | undefined;
        const message = errors ? Object.values(errors).flat()[0] : body.message;
        throw new ApiError(
            response.status === 401 || response.status === 419
                ? "La sesión expiró. Vuelve a iniciar sesión."
                : (message ??
                      "No pudimos completar la solicitud. Intenta nuevamente."),
            response.status,
        );
    }
    return body as T;
}
