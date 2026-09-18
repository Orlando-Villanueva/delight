import Constants from 'expo-constants';
import { z } from 'zod';

import { apiRequest } from '@/api/client';

export const androidReleaseResponseSchema = z.object({
  data: z.object({
    version: z.string().min(1),
    version_code: z.number().int().positive(),
    update_url: z.string().url().refine(isCanonicalAndroidUpdateUrl),
  }),
});

export type AndroidReleaseMetadata = z.infer<typeof androidReleaseResponseSchema>['data'];

export const androidUpdateCheckCooldownMs = 6 * 60 * 60 * 1_000;
export const androidUpdateDismissalWindowMs = 7 * 24 * 60 * 60 * 1_000;

export function installedAndroidVersionCode(): number | null {
  const versionCode = Number(Constants.nativeBuildVersion);

  return Number.isInteger(versionCode) && versionCode > 0 ? versionCode : null;
}

export function isCanonicalAndroidUpdateUrl(value: string): boolean {
  try {
    const url = new URL(value);

    return url.protocol === 'https:'
      && url.hostname === 'mydelight.app'
      && url.pathname === '/android'
      && url.search === ''
      && url.hash === '';
  } catch {
    return false;
  }
}

export function isAndroidUpdateAvailable(
  installedVersionCode: number | null,
  release: AndroidReleaseMetadata,
): boolean {
  return installedVersionCode !== null && installedVersionCode < release.version_code;
}

export async function fetchAndroidRelease(): Promise<AndroidReleaseMetadata> {
  const response = await apiRequest<unknown>('/api/v1/android/release');

  return androidReleaseResponseSchema.parse(response).data;
}
