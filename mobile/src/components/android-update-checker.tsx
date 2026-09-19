import * as Linking from 'expo-linking';
import { useQuery } from '@tanstack/react-query';
import {
  type ReactElement,
  useCallback,
  useEffect,
  useRef,
  useState,
} from 'react';
import {
  AppState,
  Image,
  Pressable,
  Text,
  type AppStateStatus,
  View,
} from 'react-native';

import {
  androidUpdateCheckCooldownMs,
  androidUpdateDismissalWindowMs,
  fetchAndroidRelease,
  installedAndroidVersionCode,
  isAndroidUpdateAvailable,
  type AndroidReleaseMetadata,
} from '@/api/android-update';
import {
  getAndroidUpdateDismissal,
  saveAndroidUpdateDismissal,
} from '@/api/android-update-storage';
import { shouldRetryQuery } from '@/api/retry-policy';
import { environment } from '@/config/environment';
import { BottomSheet } from '@/components/bottom-sheet';
import { getAndroidInstallerSource } from '@/native/installer-source';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

type AndroidUpdatePrompt = {
  release: AndroidReleaseMetadata;
  onLater: () => void;
  onDownload: () => void;
};

export function AndroidUpdateChecker(): ReactElement | null {
  const prompt = useAndroidUpdateChecker();

  if (!prompt) {
    return null;
  }

  return <AndroidUpdatePromptView {...prompt} />;
}

export function useAndroidUpdateChecker(): AndroidUpdatePrompt | null {
  const installedVersionCode = installedAndroidVersionCode();
  const installerSource = getAndroidInstallerSource();
  const isEnabled = environment.androidUpdateCheckerEnabled
    && installerSource === 'non-play'
    && installedVersionCode !== null;
  const lastCheckAtRef = useRef<number | null>(null);
  const promptedVersionCodeRef = useRef<number | null>(null);
  const [releaseToPrompt, setReleaseToPrompt] = useState<AndroidReleaseMetadata | null>(null);
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
      setReleaseToPrompt(data);
    }).catch(() => {
      // Release checks are best effort and must fail quietly.
    });

    return () => {
      isActive = false;
    };
  }, [data, dataUpdatedAt, installedVersionCode, isEnabled]);

  const onLater = useCallback(() => {
    if (!releaseToPrompt) {
      return;
    }

    setReleaseToPrompt(null);
    void saveAndroidUpdateDismissal({
      versionCode: releaseToPrompt.version_code,
      dismissedAt: Date.now(),
    });
  }, [releaseToPrompt]);

  const onDownload = useCallback(() => {
    if (!releaseToPrompt) {
      return;
    }

    setReleaseToPrompt(null);
    void Linking.openURL(releaseToPrompt.update_url).catch(() => {
      // A browser failure must not block authentication or reading flows.
    });
  }, [releaseToPrompt]);

  if (!releaseToPrompt) {
    return null;
  }

  return { release: releaseToPrompt, onLater, onDownload };
}

function AndroidUpdatePromptView({
  release,
  onLater,
  onDownload,
}: Readonly<AndroidUpdatePrompt>): ReactElement {
  const { colors } = useTheme();

  return (
    <BottomSheet
      visible
      title="A newer Delight version is ready"
      closeLabel="Later"
      onClose={onLater}
      padBottomSafeArea
      dismissAccessibilityLabel="Dismiss Delight update"
      dismissAccessibilityHint="Keeps using the current Delight version"
      closeAccessibilityLabel="Later"
      closeAccessibilityHint="Keeps using the current Delight version"
    >
      <View style={{ gap: themeTokens.spacing.section }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <Image
            accessible={false}
            source={require('../../assets/images/delight-logo.png')}
            style={{ width: 56, height: 56, borderRadius: themeTokens.radius.card }}
          />
          <View style={{ flex: 1, gap: 4 }}>
            <Text selectable style={{ color: colors.text, fontSize: 17, fontWeight: '700' }}>
              Delight {release.version} is ready.
            </Text>
            <Text selectable style={{ color: colors.mutedText, fontSize: 15, lineHeight: 21 }}>
              Download the latest version from the official Delight page.
            </Text>
          </View>
        </View>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Download update"
          accessibilityHint="Opens the official Delight download page"
          onPress={onDownload}
          style={{
            minHeight: themeTokens.minimumTouchTarget,
            alignItems: 'center',
            justifyContent: 'center',
            marginBottom: themeTokens.spacing.section,
            paddingHorizontal: 16,
            borderRadius: themeTokens.radius.control,
            backgroundColor: colors.accentAction,
          }}
        >
          <Text style={{ color: colors.accentActionContrast, fontSize: 16, fontWeight: '700' }}>
            Download update
          </Text>
        </Pressable>
      </View>
    </BottomSheet>
  );
}

function isDismissedForCurrentRelease(
  dismissal: Awaited<ReturnType<typeof getAndroidUpdateDismissal>>,
  versionCode: number,
): boolean {
  return dismissal !== null
    && dismissal.versionCode === versionCode
    && Date.now() - dismissal.dismissedAt < androidUpdateDismissalWindowMs;
}
