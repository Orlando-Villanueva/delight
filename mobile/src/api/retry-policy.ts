import { ApiError } from '@/api/api-error';

const maximumQueryRetries = 2;

export function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  if (failureCount >= maximumQueryRetries || ! (error instanceof ApiError)) {
    return false;
  }

  return error.kind === 'network'
    || error.kind === 'timeout'
    || (error.kind === 'http' && error.status !== undefined && error.status >= 500);
}
