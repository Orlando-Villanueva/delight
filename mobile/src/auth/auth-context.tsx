import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  createContext,
  type PropsWithChildren,
  use,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';

import { apiRequest } from '@/api/client';
import { ApiError } from '@/api/api-error';
import {
  beginGoogleSignIn,
  clearGoogleSignInSession,
  isGoogleSignInAvailable,
} from '@/auth/google-sign-in';
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

export type GoogleLoginResult = 'authenticated' | 'cancelled' | 'password-required';

type AuthContextValue = {
  status: 'loading' | 'authenticated' | 'unauthenticated';
  token: string | null;
  user: AuthUser | null;
  login: (input: LoginInput) => Promise<void>;
  loginWithGoogle: () => Promise<GoogleLoginResult>;
  confirmGoogleLink: (password: string) => Promise<void>;
  cancelGoogleLink: () => void;
  logout: () => Promise<void>;
  clearSession: () => Promise<void>;
  isGoogleSignInAvailable: boolean;
  isGooglePasswordRequired: boolean;
  isLoggingIn: boolean;
  isLoggingInWithGoogle: boolean;
  isLoggingOut: boolean;
};

export type AuthenticatedRequestOptions = {
  method?: 'GET' | 'POST' | 'DELETE';
  body?: unknown;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const queryClient = useQueryClient();
  const pendingGoogleIdToken = useRef<string | null>(null);
  const [status, setStatus] = useState<AuthContextValue['status']>('loading');
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [isGooglePasswordRequired, setIsGooglePasswordRequired] = useState(false);
  const [isLoggingInWithGoogle, setIsLoggingInWithGoogle] = useState(false);

  const clearPendingGoogleLogin = useCallback(() => {
    pendingGoogleIdToken.current = null;
    setIsGooglePasswordRequired(false);
  }, []);

  const clearSession = useCallback(async () => {
    clearPendingGoogleLogin();
    try {
      await tokenStorage.clear();
    } catch {
      // The server session is already invalid or revoked. Local UI state must still be cleared.
    }
    queryClient.clear();
    setToken(null);
    setUser(null);
    setStatus('unauthenticated');
  }, [clearPendingGoogleLogin, queryClient]);

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
      clearPendingGoogleLogin();
      setToken(data.token);
      setUser(data.user);
      setStatus('authenticated');
    },
  });

  const exchangeGoogleToken = useCallback(
    async (idToken: string, password?: string) => {
      setIsLoggingInWithGoogle(true);

      try {
        const response = await apiRequest<TokenResponse>('/api/v1/auth/google-token', {
          method: 'POST',
          body: {
            id_token: idToken,
            device_name: 'Delight Android',
            ...(password ? { password } : {}),
          },
        });
        await tokenStorage.set(response.data.token);
        clearPendingGoogleLogin();
        setToken(response.data.token);
        setUser(response.data.user);
        setStatus('authenticated');
      } finally {
        setIsLoggingInWithGoogle(false);
      }
    },
    [clearPendingGoogleLogin],
  );

  const logoutMutation = useMutation({
    mutationFn: async () => {
      if (token) {
        try {
          await apiRequest<void>('/api/v1/auth/token', {
            method: 'DELETE',
            token,
          });
        } catch (error) {
          if (!(error instanceof ApiError) || error.status !== 401) {
            throw error;
          }
        }
      }

      try {
        await clearGoogleSignInSession();
      } catch {
        // A revoked Delight token must still be cleared when local Google cleanup fails.
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
    loginWithGoogle: async () => {
      clearPendingGoogleLogin();
      const result = await beginGoogleSignIn();

      if (result.status === 'cancelled') {
        return 'cancelled';
      }

      pendingGoogleIdToken.current = result.idToken;

      try {
        await exchangeGoogleToken(result.idToken);
        return 'authenticated';
      } catch (error) {
        if (
          error instanceof ApiError
          && error.status === 422
          && error.validationErrors.password?.[0]
        ) {
          setIsGooglePasswordRequired(true);
          return 'password-required';
        }

        clearPendingGoogleLogin();
        throw error;
      }
    },
    confirmGoogleLink: async (password) => {
      const idToken = pendingGoogleIdToken.current;

      if (!idToken) {
        throw new ApiError('Start Google sign-in again.', 'http', 422);
      }

      try {
        await exchangeGoogleToken(idToken, password);
      } catch (error) {
        const isRecoverableApiFailure = error instanceof ApiError && (
          error.kind === 'network'
          || error.kind === 'timeout'
          || error.status === 429
          || (typeof error.status === 'number' && error.status >= 500)
          || (error.status === 422 && Boolean(error.validationErrors.password?.[0]))
        );

        if (!isRecoverableApiFailure) {
          clearPendingGoogleLogin();
        }

        throw error;
      }
    },
    cancelGoogleLink: clearPendingGoogleLogin,
    logout: async () => {
      await logoutMutation.mutateAsync();
    },
    clearSession,
    isGoogleSignInAvailable: isGoogleSignInAvailable(),
    isGooglePasswordRequired,
    isLoggingIn: loginMutation.isPending,
    isLoggingInWithGoogle,
    isLoggingOut: logoutMutation.isPending,
  }), [
    clearPendingGoogleLogin,
    clearSession,
    exchangeGoogleToken,
    isGooglePasswordRequired,
    isLoggingInWithGoogle,
    loginMutation,
    logoutMutation,
    status,
    token,
    user,
  ]);

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
