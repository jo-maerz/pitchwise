/** Talks to the plain-PHP API (api/public/index.php) with the player's Sanctum token. */
export class ApiError extends Error {
    constructor(status, code, message, details) {
        super(message);
        this.status = status;
        this.code = code;
        this.details = details;
    }
}

export class PracticeApi {
    constructor(baseUrl, token) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = token;
    }

    async request(method, path, body) {
        let response;
        try {
            response = await fetch(this.baseUrl + path, {
                method,
                headers: {
                    Authorization: `Bearer ${this.token}`,
                    Accept: 'application/json',
                    ...(body ? { 'Content-Type': 'application/json' } : {}),
                },
                body: body ? JSON.stringify(body) : undefined,
            });
        } catch {
            throw new ApiError(0, 'network', 'The practice API could not be reached.');
        }
        const data = await response.json().catch(() => null);
        if (!response.ok) {
            const err = data?.error ?? {};
            throw new ApiError(response.status, err.code ?? 'http_error', err.message ?? `Request failed (${response.status}).`, err.details);
        }
        return data;
    }

    getPiece(id) {
        return this.request('GET', `/pieces/${id}`);
    }

    startSession(payload) {
        return this.request('POST', '/sessions', payload);
    }

    sendResults(sessionId, results, finished = false) {
        return this.request('POST', `/sessions/${sessionId}/results`, { results, finished });
    }
}
