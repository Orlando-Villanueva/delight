import { useMutation, useQueryClient } from '@tanstack/react-query';
import { createContext, type PropsWithChildren, use, useCallback, useEffect, useMemo, useState } from 'react';

import { apiRequest } from '@/api/client';
import { ApiError } from '@/api/api-error';
import { tokenStorage } from '@/auth/token-storage';

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  avatar_url?: string | null;
};

type TokenResponse = {
  data: {
    token: string;
    token_type: 'Bearer';
    user: AuthUser;
  };
};

type LoginInput = {
  email: string;
  password: string;
};

type AuthContextValue = {
  status: 'loading' | 'authenticated' | 'unauthenticated';
  token: string | null;
  user: AuthUser | null;
  login: (input: LoginInput) => Promise<void>;
  logout: () => Promise<void>;
  clearSession: () => Promise<void>;
  isLoggingIn: boolean;
  isLoggingOut: boolean;
};

export type AuthenticatedRequestOptions = {
  method?: 'GET' | 'POST' | 'DELETE';
  body?: unknown;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<AuthContextValue['status']>('loading');
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<AuthUser | null>(null);

  const clearSession = useCallback(async () => {
    try {
      await tokenStorage.clear();
    } catch {
      // The server session is already invalid or revoked. Local UI state must still be cleared.
    }
    queryClient.clear();
    setToken(null);
    setUser(null);
    setStatus('unauthenticated');
  }, [queryClient]);

  useEffect(() => {
    let isMounted = true;

    tokenStorage.get().then((storedToken) => {
      if (!isMounted) {
        return;
      }

      setToken(storedToken);
      setStatus(storedToken ? 'authenticated' : 'unauthenticated');
    }).catch(() => {
      if (isMounted) {
        setStatus('unauthenticated');
      }
    });

    return () => {
      isMounted = false;
    };
  }, []);

  const loginMutation = useMutation({
    mutationFn: async (input: LoginInput) => {
      const response = await apiRequest<TokenResponse>('/api/v1/auth/token', {
        method: 'POST',
        body: { ...input, device_name: 'Delight Android' },
      });
      await tokenStorage.set(response.data.token);
      return response.data;
    },
    retry: false,
    onSuccess: (data) => {
      setToken(data.token);
      setUser(data.user);
      setStatus('authenticated');
    },
  });

  const logoutMutation = useMutation({
    mutationFn: async () => {
      if (token) {
        try {
          await apiRequest<void>('/api/v1/auth/token', {
            method: 'DELETE',
            token,
          });
        } catch (error) {
          if (error instanceof ApiError && error.status === 401) {
            return;
          }
          throw error;
        }
      }
    },
    retry: false,
    onSuccess: clearSession,
  });

  const value = useMemo<AuthContextValue>(() => ({
    status,
    token,
    user,
    login: async (input) => {
      await loginMutation.mutateAsync(input);
    },
    logout: async () => {
      await logoutMutation.mutateAsync();
    },
    clearSession,
    isLoggingIn: loginMutation.isPending,
    isLoggingOut: logoutMutation.isPending,
  }), [clearSession, loginMutation, logoutMutation, status, token, user]);

  return <AuthContext value={value}>{children}</AuthContext>;
}

export function useAuth(): AuthContextValue {
  const context = use(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider.');
  }

  return context;
}

export function useAuthenticatedApi() {
  const { token, clearSession } = useAuth();

  return useCallback(<T,>(path: string, options: AuthenticatedRequestOptions = {}) => {
    return apiRequest<T>(path, { ...options, token, onUnauthorized: clearSession });
  }, [clearSession, token]);
}
