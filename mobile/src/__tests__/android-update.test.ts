import * as Application from 'expo-application';

import {
  androidReleaseResponseSchema,
  installedAndroidVersionCode,
  isAndroidUpdateAvailable,
  isCanonicalAndroidUpdateUrl,
} from '@/api/android-update';

jest.mock('expo-application', () => ({ nativeBuildVersion: '9' }));
jest.mock('@/api/client', () => ({ apiRequest: jest.fn() }));
jest.mock('@/config/environment', () => ({
  environment: {
    apiUrl: 'https://mydelight.app',
    appVariant: 'production',
    androidUpdateCheckerEnabled: true,
  },
}));

describe('Android update metadata', () => {
  it('reads a positive native build version code', () => {
    expect(installedAndroidVersionCode()).toBe(9);
  });

  it('only accepts the canonical HTTPS Android page', () => {
    expect(isCanonicalAndroidUpdateUrl('https://mydelight.app/android')).toBe(true);
    expect(isCanonicalAndroidUpdateUrl('https://mydelight.app/android?source=app')).toBe(false);
    expect(isCanonicalAndroidUpdateUrl('https://example.com/android')).toBe(false);
  });

  it('detects only releases newer than the installed build', () => {
    const release = androidReleaseResponseSchema.parse({
      data: {
        version: '0.2.0',
        version_code: 10,
        update_url: 'https://mydelight.app/android',
      },
    }).data;

    expect(isAndroidUpdateAvailable(9, release)).toBe(true);
    expect(isAndroidUpdateAvailable(10, release)).toBe(false);
    expect(isAndroidUpdateAvailable(11, release)).toBe(false);
  });

  it('treats an unavailable native build version as unknown', () => {
    Object.defineProperty(Application, 'nativeBuildVersion', { value: null, configurable: true });

    expect(installedAndroidVersionCode()).toBeNull();
  });
});
