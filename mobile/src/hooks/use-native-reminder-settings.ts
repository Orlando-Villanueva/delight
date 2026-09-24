import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { AccessibilityInfo, AppState, Linking, type AppStateStatus } from 'react-native';

import {
  fetchNativePushRegistration,
  fetchNativeReminderPreference,
  registerNativePush,
  unregisterNativePush,
  updateNativeReminderPreference,
} from '@/api/native-reminders';
import { ApiError } from '@/api/api-error';
import { useAuth, useAuthenticatedApi } from '@/auth/auth-context';
import {
  getNativeExpoPushToken,
  getNativeNotificationPermission,
  requestNativeNotificationPermission,
  type NativeNotificationPermission,
} from '@/notifications/native-reminders';

const preferenceQueryKey = ['native-reminder-preference'];
const registrationQueryKey = ['native-push-registration'];
const cleanupFailureMessage = 'Reminders are off, but the notification address could not be removed. Retry cleanup.';

class NativeReminderCleanupError extends Error {
  constructor(public readonly sourceError?: unknown) {
    super('Notification address cleanup failed.');
  }
}

function errorMessage(error: unknown): string {
  if (error instanceof NativeReminderCleanupError) {
    return cleanupFailureMessage;
  }

  if (error instanceof ApiError && error.kind === 'network') {
    return 'Delight could not connect. Check your connection and try again.';
  }

  if (error instanceof ApiError && error.kind === 'timeout') {
    return 'The notification request timed out. Check your connection and try again.';
  }

  if (error instanceof Error && error.message.includes('project is not configured')) {
    return 'This app build is not configured for native notifications yet.';
  }

  return 'Notification settings could not be updated. Try again.';
}

function permissionMessage(permission: NativeNotificationPermission | null): string | null {
  if (!permission || permission.granted) {
    return null;
  }

  return 'Reminders are enabled, but system notifications are blocked. Open system settings to restore delivery.';
}

export function useNativeReminderSettings() {
  const { status } = useAuth();
  const request = useAuthenticatedApi();
  const queryClient = useQueryClient();
  const [permission, setPermission] = useState<NativeNotificationPermission | null>(null);
  const [permissionLoaded, setPermissionLoaded] = useState(false);
  const [cleanupPending, setCleanupPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const preferenceQuery = useQuery({
    queryKey: preferenceQueryKey,
    queryFn: () => fetchNativeReminderPreference(request),
    enabled: status === 'authenticated',
    refetchOnMount: 'always',
  });
  const registrationQuery = useQuery({
    queryKey: registrationQueryKey,
    queryFn: () => fetchNativePushRegistration(request),
    enabled: status === 'authenticated',
    refetchOnMount: 'always',
  });
  const { refetch: refetchPreference } = preferenceQuery;
  const { refetch: refetchRegistration } = registrationQuery;

  const refreshPermission = useCallback(async (): Promise<NativeNotificationPermission | null> => {
    try {
      const nextPermission = await getNativeNotificationPermission();
      setPermission(nextPermission);

      return nextPermission;
    } catch (refreshError) {
      setError(errorMessage(refreshError));

      return null;
    } finally {
      setPermissionLoaded(true);
    }
  }, []);

  const refreshQueries = useCallback(async (): Promise<void> => {
    const [preferenceResult, registrationResult, nextPermission] = await Promise.all([
      refetchPreference(),
      refetchRegistration(),
      refreshPermission(),
    ]);

    if (preferenceResult.data && registrationResult.data) {
      const cleanupNeeded = !preferenceResult.data.enabled && registrationResult.data.registered;
      setCleanupPending(cleanupNeeded);

      if (cleanupNeeded) {
        setError(cleanupFailureMessage);
      }
    }

    if (
      preferenceResult.data?.enabled
      && nextPermission?.granted
      && !registrationResult.data?.registered
    ) {
      try {
        const expoPushToken = await getNativeExpoPushToken();
        await registerNativePush(request, expoPushToken);
        await refetchRegistration();
      } catch (registrationError) {
        setError(errorMessage(registrationError));
      }
    }
  }, [refetchPreference, refetchRegistration, refreshPermission, request]);

  useFocusEffect(useCallback(() => {
    void refreshQueries();
  }, [refreshQueries]));

  useEffect(() => {
    function refreshWhenForegrounded(nextAppState: AppStateStatus): void {
      if (nextAppState === 'active') {
        void refreshQueries();
      }
    }

    const subscription = AppState.addEventListener('change', refreshWhenForegrounded);

    return () => subscription.remove();
  }, [refreshQueries]);

  const toggleMutation = useMutation({
    mutationFn: async (enabled: boolean): Promise<void> => {
      if (!enabled) {
        await updateNativeReminderPreference(request, false);

        try {
          await unregisterNativePush(request);
        } catch {
          throw new NativeReminderCleanupError();
        }

        return;
      }

      const nextPermission = await requestNativeNotificationPermission();
      setPermission(nextPermission);

      if (!nextPermission.granted) {
        throw new Error('Notification permission was not granted.');
      }

      const expoPushToken = await getNativeExpoPushToken();

      try {
        await registerNativePush(request, expoPushToken);
        await updateNativeReminderPreference(request, true);
      } catch (enableError) {
        try {
          await unregisterNativePush(request);
        } catch (cleanupError) {
          throw new NativeReminderCleanupError(cleanupError);
        }

        throw enableError;
      }
    },
    onSuccess: async () => {
      setCleanupPending(false);
      setError(null);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: preferenceQueryKey }),
        queryClient.invalidateQueries({ queryKey: registrationQueryKey }),
      ]);
    },
    onError: async (mutationError) => {
      if (mutationError instanceof NativeReminderCleanupError) {
        setCleanupPending(true);
      }

      setError(
        mutationError instanceof Error && mutationError.message.includes('permission was not granted')
          ? 'Notifications were not enabled. Allow notifications in system settings and try again.'
          : errorMessage(mutationError),
      );
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: preferenceQueryKey }),
        queryClient.invalidateQueries({ queryKey: registrationQueryKey }),
      ]);
    },
  });

  const cleanupMutation = useMutation({
    mutationFn: () => unregisterNativePush(request),
    onSuccess: async () => {
      setCleanupPending(false);
      setError(null);
      await queryClient.invalidateQueries({ queryKey: registrationQueryKey });
    },
    onError: () => {
      setCleanupPending(true);
      setError(errorMessage(new NativeReminderCleanupError()));
    },
  });

  const isLoading = preferenceQuery.isLoading || registrationQuery.isLoading || !permissionLoaded;
  const isBusy = toggleMutation.isPending || cleanupMutation.isPending;
  const enabled = preferenceQuery.data?.enabled ?? false;
  const permissionWarning = enabled ? permissionMessage(permission) : null;
  const queryError = preferenceQuery.error ?? registrationQuery.error;

  const retryLoading = useCallback(() => {
    void refreshQueries();
  }, [refreshQueries]);

  const openNotificationSettings = useCallback(async (): Promise<void> => {
    try {
      await Linking.openSettings();
    } catch (settingsError) {
      const message = errorMessage(settingsError);
      setError(message);
      AccessibilityInfo.announceForAccessibility(message);
    }
  }, []);

  return {
    cleanupPending,
    enabled,
    error,
    hasQueryError: Boolean(queryError),
    isBusy,
    isLoading,
    permissionWarning,
    openNotificationSettings,
    retryCleanup: () => {
      setError(null);
      cleanupMutation.mutate();
    },
    retryLoading,
    toggle: (nextEnabled: boolean) => {
      setError(null);
      toggleMutation.mutate(nextEnabled);
    },
  };
}
