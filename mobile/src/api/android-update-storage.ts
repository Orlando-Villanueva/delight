import * as SecureStore from 'expo-secure-store';

const dismissalKey = 'delight.android-update-dismissed';

export type AndroidUpdateDismissal = {
  versionCode: number;
  dismissedAt: number;
};

export async function getAndroidUpdateDismissal(): Promise<AndroidUpdateDismissal | null> {
  try {
    const value = await SecureStore.getItemAsync(dismissalKey);

    if (!value) {
      return null;
    }

    const parsed: unknown = JSON.parse(value);

    if (!isAndroidUpdateDismissal(parsed)) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

export async function saveAndroidUpdateDismissal(dismissal: AndroidUpdateDismissal): Promise<void> {
  try {
    await SecureStore.setItemAsync(dismissalKey, JSON.stringify(dismissal));
  } catch {
    // A storage failure must not interfere with the rest of the app.
  }
}

function isAndroidUpdateDismissal(value: unknown): value is AndroidUpdateDismissal {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const dismissal = value as Record<string, unknown>;

  return typeof dismissal.versionCode === 'number'
    && Number.isInteger(dismissal.versionCode)
    && dismissal.versionCode > 0
    && typeof dismissal.dismissedAt === 'number'
    && Number.isFinite(dismissal.dismissedAt)
    && dismissal.dismissedAt > 0;
}
