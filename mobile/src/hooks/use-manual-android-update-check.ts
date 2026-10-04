import { useCallback, useEffect, useRef, useState } from 'react';
import { Platform } from 'react-native';

import {
  fetchAndroidRelease,
  installedAndroidVersionCode,
  isAndroidUpdateAvailable,
  type AndroidReleaseMetadata,
} from '@/api/android-update';
import { environment } from '@/config/environment';
import { getAndroidInstallerSource } from '@/native/installer-source';

export type ManualAndroidUpdateState =
  | { status: 'unsupported' }
  | { status: 'play-managed' }
  | { status: 'idle' }
  | { status: 'checking' }
  | { status: 'up-to-date'; release: AndroidReleaseMetadata }
  | { status: 'update-available'; release: AndroidReleaseMetadata }
  | { status: 'unable-to-check'; reason: 'unknown-installer' | 'unknown-version' | 'request-failed' };

function initialState(): ManualAndroidUpdateState {
  if (Platform.OS !== 'android' || !environment.androidUpdateCheckerEnabled) {
    return { status: 'unsupported' };
  }

  const installerSource = getAndroidInstallerSource();

  if (installerSource === 'play') {
    return { status: 'play-managed' };
  }

  if (installerSource === 'unknown') {
    return { status: 'unable-to-check', reason: 'unknown-installer' };
  }

  if (installedAndroidVersionCode() === null) {
    return { status: 'unable-to-check', reason: 'unknown-version' };
  }

  return { status: 'idle' };
}

export function useManualAndroidUpdateCheck() {
  const [state, setState] = useState<ManualAndroidUpdateState>(initialState);
  const requestPending = useRef(false);
  const mounted = useRef(false);

  useEffect(() => {
    mounted.current = true;

    return () => {
      mounted.current = false;
    };
  }, []);

  const checkForUpdate = useCallback(async (): Promise<void> => {
    if (requestPending.current || !mounted.current) {
      return;
    }

    const prerequisite = initialState();
    const installedVersionCode = installedAndroidVersionCode();

    if (prerequisite.status !== 'idle' || installedVersionCode === null) {
      setState(prerequisite);
      return;
    }

    requestPending.current = true;
    setState({ status: 'checking' });

    try {
      const release = await fetchAndroidRelease();

      if (mounted.current) {
        setState({
          status: isAndroidUpdateAvailable(installedVersionCode, release) ? 'update-available' : 'up-to-date',
          release,
        });
      }
    } catch {
      if (mounted.current) {
        setState({ status: 'unable-to-check', reason: 'request-failed' });
      }
    } finally {
      requestPending.current = false;
    }
  }, []);

  return { state, checkForUpdate };
}
