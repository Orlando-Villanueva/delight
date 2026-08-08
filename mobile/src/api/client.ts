import { fetch } from 'expo/fetch';

import { ApiError, type ValidationErrors } from '@/api/api-error';
import { environment } from '@/config/environment';

const requestTimeoutMs = 15_000;

type LaravelErrorBody = {
  message?: string;
  errors?: ValidationErrors;
};

type ApiRequestOptions = {
  method?: 'GET' | 'POST' | 'DELETE';
  body?: unknown;
  token?: string | null;
  onUnauthorized?: () => void | Promise<void>;
};

function retryAfterSeconds(response: Response): number | undefined {
  const value = Number(response.headers.get('retry-after'));
  return Number.isFinite(value) && value > 0 ? value : undefined;
}

async function errorBody(response: Response): Promise<LaravelErrorBody> {
  try {
    return (await response.json()) as LaravelErrorBody;
  } catch {
    return {};
  }
}

export async function apiRequest<T>(
  path: string,
  { method = 'GET', body, token, onUnauthorized }: ApiRequestOptions = {},
): Promise<T> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), requestTimeoutMs);

  try {
    const response = await fetch(`${environment.apiUrl}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: controller.signal,
    });

    if (response.status === 401) {
      await onUnauthorized?.();
    }

    if (!response.ok) {
      const parsed = await errorBody(response);
      throw new ApiError(
        parsed.message ?? 'The request could not be completed.',
        'http',
        response.status,
        parsed.errors,
        response.status === 429 ? retryAfterSeconds(response) : undefined,
      );
    }

    if (response.status === 204) {
      return undefined as T;
    }

    return (await response.json()) as T;
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }

    if (error instanceof Error && error.name === 'AbortError') {
      throw new ApiError('The request timed out. Check your connection and try again.', 'timeout');
    }

    throw new ApiError('Delight could not connect. Check your connection and try again.', 'network');
  } finally {
    clearTimeout(timeout);
  }
}
