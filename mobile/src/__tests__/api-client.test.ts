import { fetch } from 'expo/fetch';

import { apiRequest } from '@/api/client';
import { ApiError } from '@/api/api-error';
import { shouldRetryQuery } from '@/api/retry-policy';

jest.mock('expo/fetch', () => ({ fetch: jest.fn() }));
jest.mock('@/config/environment', () => ({
  environment: { apiUrl: 'https://delight-staging.laravel.cloud', appVariant: 'development' },
}));

const mockedFetch = jest.mocked(fetch);

function response(status: number, body?: unknown, headers?: Record<string, string>) {
  return new Response(body === undefined ? undefined : JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json', ...headers },
  }) as never;
}

describe('typed API client', () => {
  beforeEach(() => jest.clearAllMocks());

  it('sends JSON and Bearer authentication and reads successful JSON', async () => {
    mockedFetch.mockResolvedValue(response(200, { data: { ok: true } }));

    await expect(apiRequest('/api/example', { method: 'POST', body: { value: 1 }, token: 'secret' })).resolves.toEqual({ data: { ok: true } });

    expect(mockedFetch).toHaveBeenCalledWith(
      'https://delight-staging.laravel.cloud/api/example',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ value: 1 }),
        headers: expect.objectContaining({ Authorization: 'Bearer secret', 'Content-Type': 'application/json' }),
      }),
    );
  });

  afterEach(() => jest.restoreAllMocks());

  it('reports the device timezone on authenticated requests', async () => {
    jest.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue({
      timeZone: 'Asia/Tokyo',
    } as Intl.ResolvedDateTimeFormatOptions);
    mockedFetch.mockResolvedValue(response(200, { data: {} }));

    await apiRequest('/api/v1/bootstrap', { token: 'secret' });

    expect(mockedFetch).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({
      headers: expect.objectContaining({ 'X-Reading-Timezone': 'Asia/Tokyo' }),
    }));
  });

  it('keeps authenticated requests available when timezone detection fails', async () => {
    jest.spyOn(Intl, 'DateTimeFormat').mockImplementation(() => {
      throw new Error('Timezone unavailable');
    });
    mockedFetch.mockResolvedValue(response(200, { data: {} }));

    await expect(apiRequest('/api/v1/bootstrap', { token: 'secret' })).resolves.toEqual({ data: {} });

    expect(mockedFetch.mock.calls[0][1]?.headers).not.toHaveProperty('X-Reading-Timezone');
  });

  it('omits empty timezone reports and does not report a timezone on unauthenticated requests', async () => {
    const resolvedOptions = jest.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue({
      timeZone: '',
    } as Intl.ResolvedDateTimeFormatOptions);
    mockedFetch.mockResolvedValue(response(200, { data: {} }));

    await apiRequest('/api/v1/bootstrap', { token: 'secret' });
    expect(mockedFetch.mock.calls[0][1]?.headers).not.toHaveProperty('X-Reading-Timezone');
    resolvedOptions.mockClear();
    mockedFetch.mockResolvedValueOnce(response(200, { data: {} }));
    await apiRequest('/api/v1/auth/token', { method: 'POST' });
    expect(resolvedOptions).not.toHaveBeenCalled();
  });

  it('returns no data for a successful empty response', async () => {
    mockedFetch.mockResolvedValue(response(204));

    await expect(apiRequest<void>('/api/v1/auth/token', { method: 'DELETE', token: 'secret' })).resolves.toBeUndefined();
  });

  it('maps validation errors and rate-limit cooldowns', async () => {
    mockedFetch
      .mockResolvedValueOnce(response(422, { message: 'Invalid credentials.', errors: { email: ['Invalid credentials.'] } }))
      .mockResolvedValueOnce(response(429, { message: 'Too many attempts.' }, { 'retry-after': '42' }));

    await expect(apiRequest('/login', { method: 'POST' })).rejects.toMatchObject({
      status: 422,
      validationErrors: { email: ['Invalid credentials.'] },
    });
    await expect(apiRequest('/login', { method: 'POST' })).rejects.toMatchObject({
      status: 429,
      retryAfterSeconds: 42,
    });
  });

  it('clears the session centrally on 401', async () => {
    const onUnauthorized = jest.fn();
    mockedFetch.mockResolvedValue(response(401, { message: 'Unauthenticated.' }));

    await expect(apiRequest('/protected', { token: 'revoked', onUnauthorized })).rejects.toMatchObject({ status: 401 });
    expect(onUnauthorized).toHaveBeenCalledTimes(1);
  });

  it('exposes server failures without retrying the mutation', async () => {
    mockedFetch.mockResolvedValue(response(503, { message: 'Service unavailable.' }));

    await expect(apiRequest('/login', { method: 'POST' })).rejects.toMatchObject({ status: 503, kind: 'http' });
    expect(mockedFetch).toHaveBeenCalledTimes(1);
  });

  it('classifies network failures and the 15-second timeout for manual retry', async () => {
    mockedFetch.mockRejectedValueOnce(new TypeError('Network request failed'));
    await expect(apiRequest('/login', { method: 'POST' })).rejects.toMatchObject({ kind: 'network' });

    jest.useFakeTimers();
    mockedFetch.mockImplementationOnce((_url, options) => new Promise((_resolve, reject) => {
      options?.signal?.addEventListener('abort', () => reject(Object.assign(new Error('Aborted'), { name: 'AbortError' })));
    }));
    const pending = apiRequest('/login', { method: 'POST' });
    const expectation = expect(pending).rejects.toMatchObject({ kind: 'timeout' });
    await jest.advanceTimersByTimeAsync(15_000);
    await expectation;
    jest.useRealTimers();
  });
});

describe('query retry policy', () => {
  it.each([
    [new ApiError('Offline.', 'network'), true],
    [new ApiError('Timed out.', 'timeout'), true],
    [new ApiError('Unavailable.', 'http', 503), true],
    [new ApiError('Unauthenticated.', 'http', 401), false],
    [new ApiError('Invalid.', 'http', 422), false],
    [new ApiError('Slow down.', 'http', 429), false],
  ])('retries only transient errors', (error, expected) => {
    expect(shouldRetryQuery(0, error)).toBe(expected);
  });

  it('stops after two retries', () => {
    const error = new ApiError('Unavailable.', 'http', 503);

    expect(shouldRetryQuery(1, error)).toBe(true);
    expect(shouldRetryQuery(2, error)).toBe(false);
  });
});
