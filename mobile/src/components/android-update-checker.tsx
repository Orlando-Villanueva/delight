import * as Linking from 'expo-linking';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { Alert, AppState, type AppStateStatus } from 'react-native';

import {
  androidUpdateCheckCooldownMs,
  androidUpdateDismissalWindowMs,
  fetchAndroidRelease,
  installedAndroidVersionCode,
  isAndroidUpdateAvailable,
} from '@/api/android-update';
import {
  getAndroidUpdateDismissal,
  saveAndroidUpdateDismissal,
} from '@/api/android-update-storage';
import { shouldRetryQuery } from '@/api/retry-policy';
import { environment } from '@/config/environment';
import { getAndroidInstallerSource } from '@/native/installer-source';

export function AndroidUpdateChecker(): null {
  useAndroidUpdateChecker();

  return null;
}

export function useAndroidUpdateChecker(): void {
  const installedVersionCode = installedAndroidVersionCode();
  const installerSource = getAndroidInstallerSource();
  const isEnabled = environment.androidUpdateCheckerEnabled
    && installerSource === 'non-play'
    && installedVersionCode !== null;
  const lastCheckAtRef = useRef<number | null>(null);
  const promptedVersionCodeRef = useRef<number | null>(null);
  const { data, dataUpdatedAt, refetch } = useQuery({
    queryKey: ['android-release'],
    queryFn: fetchAndroidRelease,
    enabled: isEnabled,
    retry: shouldRetryQuery,
    staleTime: androidUpdateCheckCooldownMs,
  });

  useEffect(() => {
    if (isEnabled) {
      lastCheckAtRef.current = Date.now();
    }
  }, [isEnabled]);

  useEffect(() => {
    if (!isEnabled) {
      return;
    }

    function checkWhenForegrounded(nextAppState: AppStateStatus): void {
      if (nextAppState !== 'active') {
        return;
      }

      const now = Date.now();
      const lastCheckAt = lastCheckAtRef.current;

      if (lastCheckAt !== null && now - lastCheckAt < androidUpdateCheckCooldownMs) {
        return;
      }

      lastCheckAtRef.current = now;
      void refetch();
    }

    return AppState.addEventListener('change', checkWhenForegrounded).remove;
  }, [isEnabled, refetch]);

  useEffect(() => {
    if (
      !isEnabled
      || installedVersionCode === null
      || !data
      || !isAndroidUpdateAvailable(installedVersionCode, data)
      || promptedVersionCodeRef.current === data.version_code
    ) {
      return;
    }

    let isActive = true;

    void getAndroidUpdateDismissal().then((dismissal) => {
      if (
        !isActive
        || isDismissedForCurrentRelease(dismissal, data.version_code)
        || promptedVersionCodeRef.current === data.version_code
      ) {
        return;
      }

      promptedVersionCodeRef.current = data.version_code;

      Alert.alert(
        'A new Delight update is available',
        `Version ${data.version} (${data.version_code}) is ready. Update from the official Delight download page.`,
        [
          {
            text: 'Later',
            style: 'cancel',
            onPress: () => {
              void saveAndroidUpdateDismissal({
                versionCode: data.version_code,
                dismissedAt: Date.now(),
              });
            },
          },
          {
            text: 'Open download page',
            onPress: () => {
              void Linking.openURL(data.update_url).catch(() => {
                // A browser failure must not block authentication or reading flows.
              });
            },
          },
        ],
      );
    }).catch(() => {
      // Release checks are best effort and must fail quietly.
    });

    return () => {
      isActive = false;
    };
  }, [data, dataUpdatedAt, installedVersionCode, isEnabled]);
}

function isDismissedForCurrentRelease(
  dismissal: Awaited<ReturnType<typeof getAndroidUpdateDismissal>>,
  versionCode: number,
): boolean {
  return dismissal !== null
    && dismissal.versionCode === versionCode
    && Date.now() - dismissal.dismissedAt < androidUpdateDismissalWindowMs;
}
