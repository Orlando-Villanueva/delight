import * as Application from 'expo-application';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react-native';
import { type ReactNode } from 'react';
import { Platform } from 'react-native';

import { apiRequest } from '@/api/client';
import { environment } from '@/config/environment';
import { useManualAndroidUpdateCheck } from '@/hooks/use-manual-android-update-check';
import { getAndroidInstallerSource } from '@/native/installer-source';

jest.mock('@/api/client', () => ({ apiRequest: jest.fn() }));
jest.mock('@/native/installer-source', () => ({ getAndroidInstallerSource: jest.fn() }));
jest.mock('expo-application', () => ({ nativeBuildVersion: '9' }));
jest.mock('@/config/environment', () => ({
  environment: {
    apiUrl: 'https://mydelight.app',
    appVariant: 'production',
    androidUpdateCheckerEnabled: true,
  },
}));

const mockedRequest = jest.mocked(apiRequest);
const mockedInstallerSource = jest.mocked(getAndroidInstallerSource);
const release = {
  version: '0.2.0',
  version_code: 10,
  update_url: 'https://mydelight.app/android',
};

describe('manual Android update check', () => {
  beforeEach(() => {
    jest.resetAllMocks();
    Object.defineProperty(Platform, 'OS', { value: 'android', configurable: true });
    Object.defineProperty(Application, 'nativeBuildVersion', { value: '9', configurable: true });
    environment.androidUpdateCheckerEnabled = true;
    mockedInstallerSource.mockReturnValue('non-play');
    mockedRequest.mockResolvedValue({ data: release });
  });

  it('waits for a deliberate check and prevents overlapping requests', async () => {
    let resolveRequest!: (value: unknown) => void;
    mockedRequest.mockImplementation(() => new Promise((resolve) => { resolveRequest = resolve; }));
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    expect(result.current.state.status).toBe('idle');
    expect(mockedRequest).not.toHaveBeenCalled();

    let pendingCheck!: Promise<void>;
    await act(async () => {
      pendingCheck = result.current.checkForUpdate();
      await result.current.checkForUpdate();
    });

    expect(result.current.state.status).toBe('checking');
    expect(mockedRequest).toHaveBeenCalledTimes(1);

    await act(async () => {
      resolveRequest({ data: release });
      await pendingCheck;
    });

    expect(result.current.state).toEqual({ status: 'update-available', release });
  });

  it.each(['9', '10', '11'])('compares the installed native version code %s', async (versionCode) => {
    Object.defineProperty(Application, 'nativeBuildVersion', { value: versionCode, configurable: true });
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    await act(async () => { await result.current.checkForUpdate(); });

    expect(result.current.state.status).toBe(versionCode === '9' ? 'update-available' : 'up-to-date');
  });

  it.each([
    ['network failure', () => Promise.reject(new Error('Offline'))],
    ['invalid metadata', () => Promise.resolve({ data: { ...release, version_code: '10' } })],
    ['unsafe destination', () => Promise.resolve({ data: { ...release, update_url: 'https://example.com' } })],
  ])('reports %s and allows a fresh retry', async (_name, failedRequest) => {
    mockedRequest.mockImplementationOnce(failedRequest);
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    await act(async () => { await result.current.checkForUpdate(); });
    expect(result.current.state).toEqual({ status: 'unable-to-check', reason: 'request-failed' });

    await act(async () => { await result.current.checkForUpdate(); });
    expect(result.current.state.status).toBe('update-available');
    expect(mockedRequest).toHaveBeenCalledTimes(2);
  });

  it('replaces a previous success with an error when a new check fails', async () => {
    const { result } = await renderHook(useManualAndroidUpdateCheck);
    await act(async () => { await result.current.checkForUpdate(); });
    expect(result.current.state.status).toBe('update-available');

    mockedRequest.mockRejectedValueOnce(new Error('Offline'));
    await act(async () => { await result.current.checkForUpdate(); });
    expect(result.current.state).toEqual({ status: 'unable-to-check', reason: 'request-failed' });
  });

  it('keeps Play installs store-managed without fetching direct-download metadata', async () => {
    mockedInstallerSource.mockReturnValue('play');
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    await act(async () => { await result.current.checkForUpdate(); });

    expect(result.current.state.status).toBe('play-managed');
    expect(mockedRequest).not.toHaveBeenCalled();
  });

  it.each([null, '', 'invalid', '0', '-1', '1.5'])('cannot claim status with native version %s', async (version) => {
    Object.defineProperty(Application, 'nativeBuildVersion', { value: version, configurable: true });
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    await act(async () => { await result.current.checkForUpdate(); });

    expect(result.current.state).toEqual({ status: 'unable-to-check', reason: 'unknown-version' });
    expect(mockedRequest).not.toHaveBeenCalled();
  });

  it('cannot claim status for an unknown installer', async () => {
    mockedInstallerSource.mockReturnValue('unknown');
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    await act(async () => { await result.current.checkForUpdate(); });

    expect(result.current.state).toEqual({ status: 'unable-to-check', reason: 'unknown-installer' });
    expect(mockedRequest).not.toHaveBeenCalled();
  });

  it.each(['ios', 'web'])('does not check on %s', async (platform) => {
    Object.defineProperty(Platform, 'OS', { value: platform, configurable: true });
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    await act(async () => { await result.current.checkForUpdate(); });

    expect(result.current.state.status).toBe('unsupported');
    expect(mockedRequest).not.toHaveBeenCalled();
    expect(mockedInstallerSource).not.toHaveBeenCalled();
  });

  it('respects build-channel gating', async () => {
    environment.androidUpdateCheckerEnabled = false;
    const { result } = await renderHook(useManualAndroidUpdateCheck);

    await act(async () => { await result.current.checkForUpdate(); });

    expect(result.current.state.status).toBe('unsupported');
    expect(mockedRequest).not.toHaveBeenCalled();
  });

  it('always fetches fresh metadata without changing the automatic query cache', async () => {
    const queryClient = new QueryClient();
    const cachedRelease = { ...release, version_code: 9 };
    queryClient.setQueryData(['android-release'], cachedRelease);
    const { result, unmount } = await renderHook(useManualAndroidUpdateCheck, {
      wrapper: ({ children }: { children: ReactNode }) => (
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      ),
    });

    await act(async () => { await result.current.checkForUpdate(); });
    await act(async () => { await result.current.checkForUpdate(); });

    expect(mockedRequest).toHaveBeenCalledTimes(2);
    expect(mockedRequest).toHaveBeenCalledWith('/api/v1/android/release');
    expect(result.current.state.status).toBe('update-available');
    expect(queryClient.getQueryData(['android-release'])).toEqual(cachedRelease);
    await unmount();
    queryClient.clear();
  });
});
