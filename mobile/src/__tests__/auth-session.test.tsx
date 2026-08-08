import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { Text } from 'react-native';

import { ApiError } from '@/api/api-error';
import { apiRequest } from '@/api/client';
import { AuthProvider, useAuth, useAuthenticatedApi } from '@/auth/auth-context';
import { tokenStorage } from '@/auth/token-storage';

jest.mock('@/api/client', () => ({ apiRequest: jest.fn() }));
jest.mock('@/auth/token-storage', () => ({
  tokenStorage: { get: jest.fn(), set: jest.fn(), clear: jest.fn() },
}));

const mockedRequest = jest.mocked(apiRequest);
const mockedStorage = jest.mocked(tokenStorage);

function SessionHarness() {
  const auth = useAuth();
  const request = useAuthenticatedApi();
  return (
    <>
      <Text>{auth.status}</Text>
      <Text>{auth.user?.email ?? 'no user'}</Text>
      <Text onPress={() => void auth.login({ email: 'reader@example.com', password: 'password' }).catch(() => undefined)}>Login</Text>
      <Text onPress={() => void auth.logout().catch(() => undefined)}>Logout</Text>
      <Text onPress={() => void request('/protected').catch(() => undefined)}>Protected request</Text>
    </>
  );
}

async function renderSession() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { gcTime: Infinity }, mutations: { retry: false, gcTime: Infinity } } });
  return render(<QueryClientProvider client={queryClient}><AuthProvider><SessionHarness /></AuthProvider></QueryClientProvider>);
}

describe('authentication session', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockedStorage.clear.mockResolvedValue();
    mockedStorage.set.mockResolvedValue();
  });

  it('restores a stored token on cold startup', async () => {
    let resolveToken: (token: string | null) => void = () => undefined;
    mockedStorage.get.mockReturnValue(new Promise((resolve) => { resolveToken = resolve; }));
    await renderSession();

    expect(screen.getByText('loading')).toBeOnTheScreen();
    await act(async () => resolveToken('stored-token'));
    expect(screen.getByText('authenticated')).toBeOnTheScreen();
  });

  it('stores a successful login and exposes the authenticated user', async () => {
    mockedStorage.get.mockResolvedValue(null);
    mockedRequest.mockResolvedValue({ data: { token: 'new-token', token_type: 'Bearer', user: { id: 1, name: 'Reader', email: 'reader@example.com' } } });
    await renderSession();
    await waitFor(() => expect(screen.getByText('unauthenticated')).toBeOnTheScreen());

    await fireEvent.press(screen.getByText('Login'));
    await waitFor(() => expect(screen.getByText('reader@example.com')).toBeOnTheScreen());
    expect(mockedStorage.set).toHaveBeenCalledWith('new-token');
    expect(mockedRequest).toHaveBeenCalledWith('/api/v1/auth/token', expect.objectContaining({ method: 'POST' }));
  });

  it('revokes the current token then clears local storage on logout', async () => {
    mockedStorage.get.mockResolvedValue('stored-token');
    mockedRequest.mockResolvedValue(undefined);
    await renderSession();
    await waitFor(() => expect(screen.getByText('authenticated')).toBeOnTheScreen());

    await fireEvent.press(screen.getByText('Logout'));
    await waitFor(() => expect(screen.getByText('unauthenticated')).toBeOnTheScreen());
    expect(mockedRequest).toHaveBeenCalledWith('/api/v1/auth/token', expect.objectContaining({ method: 'DELETE', token: 'stored-token' }));
    expect(mockedStorage.clear).toHaveBeenCalled();
  });

  it('safely clears an already-invalid logout session', async () => {
    mockedStorage.get.mockResolvedValue('revoked-token');
    mockedRequest.mockImplementation(async (_path, options) => {
      await options?.onUnauthorized?.();
      throw new ApiError('Unauthenticated.', 'http', 401);
    });
    await renderSession();
    await waitFor(() => expect(screen.getByText('authenticated')).toBeOnTheScreen());

    await fireEvent.press(screen.getByText('Logout'));
    await waitFor(() => expect(screen.getByText('unauthenticated')).toBeOnTheScreen());
    expect(mockedStorage.clear).toHaveBeenCalled();
  });

  it('keeps the session when logout cannot reach the server', async () => {
    mockedStorage.get.mockResolvedValue('stored-token');
    mockedRequest.mockRejectedValue(new ApiError('Delight could not connect.', 'network'));
    await renderSession();
    await waitFor(() => expect(screen.getByText('authenticated')).toBeOnTheScreen());

    await fireEvent.press(screen.getByText('Logout'));
    await waitFor(() => expect(mockedRequest).toHaveBeenCalled());
    expect(screen.getByText('authenticated')).toBeOnTheScreen();
    expect(mockedStorage.clear).not.toHaveBeenCalled();
  });

  it('returns to Login after 401 even when SecureStore deletion fails', async () => {
    mockedStorage.get.mockResolvedValue('revoked-token');
    mockedStorage.clear.mockRejectedValue(new Error('SecureStore unavailable'));
    mockedRequest.mockImplementation(async (_path, options) => {
      await options?.onUnauthorized?.();
      throw new ApiError('Unauthenticated.', 'http', 401);
    });
    await renderSession();
    await waitFor(() => expect(screen.getByText('authenticated')).toBeOnTheScreen());

    await fireEvent.press(screen.getByText('Protected request'));
    await waitFor(() => expect(screen.getByText('unauthenticated')).toBeOnTheScreen());
    expect(mockedStorage.clear).toHaveBeenCalled();
  });
});
