import * as Application from 'expo-application';
import * as Linking from 'expo-linking';
import { act, fireEvent, render, screen } from '@testing-library/react-native';
import { AccessibilityInfo, Platform } from 'react-native';

import { apiRequest } from '@/api/client';
import { AndroidUpdateSettings } from '@/components/android-update-settings';
import { SettingsScreen } from '@/components/settings-screen';
import { environment } from '@/config/environment';
import { getAndroidInstallerSource } from '@/native/installer-source';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

jest.mock('@/api/client', () => ({ apiRequest: jest.fn() }));
jest.mock('@/native/installer-source', () => ({ getAndroidInstallerSource: jest.fn() }));
jest.mock('expo-application', () => ({ __esModule: true, nativeBuildVersion: '9' }));
jest.mock('expo-linking', () => ({ openURL: jest.fn() }));
jest.mock('@/components/native-reminder-settings', () => ({ NativeReminderSettings: () => null }));
jest.mock('@/config/web-environment', () => ({ getWebBaseUrl: () => 'https://mydelight.app' }));
jest.mock('@/theme/use-theme', () => ({ useTheme: jest.fn() }));
jest.mock('@/config/environment', () => ({
  environment: {
    apiUrl: 'https://mydelight.app',
    appVariant: 'production',
    androidUpdateCheckerEnabled: true,
  },
}));

const mockedRequest = jest.mocked(apiRequest);
const mockedOpenURL = jest.mocked(Linking.openURL);
const mockedInstallerSource = jest.mocked(getAndroidInstallerSource);
const release = {
  version: '0.2.0',
  version_code: 10,
  update_url: 'https://mydelight.app/android',
};

describe('Android update settings', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockedRequest.mockReset();
    mockedOpenURL.mockReset();
    Object.defineProperty(Platform, 'OS', { value: 'android', configurable: true });
    Object.defineProperty(Application, 'nativeBuildVersion', { value: '9', configurable: true });
    environment.androidUpdateCheckerEnabled = true;
    mockedInstallerSource.mockReturnValue('non-play');
    mockedRequest.mockResolvedValue({ data: release });
    mockedOpenURL.mockResolvedValue(true);
    jest.mocked(useTheme).mockReturnValue({ colors: themeTokens.light, mode: 'light' });
  });

  it('appears in Settings without checking automatically', async () => {
    await render(<SettingsScreen />);

    expect(screen.getByText('UPDATES')).toBeOnTheScreen();
    expect(screen.queryByText('App updates')).not.toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Check for updates' })).toBeEnabled();
    expect(screen.getByText('HELP & LEGAL')).toBeOnTheScreen();
    expect(mockedRequest).not.toHaveBeenCalled();
  });

  it('shows checking status and disables the action until the request completes', async () => {
    let resolveRequest!: (value: unknown) => void;
    mockedRequest.mockImplementation(() => new Promise((resolve) => { resolveRequest = resolve; }));
    await render(<AndroidUpdateSettings />);

    await fireEvent.press(screen.getByRole('button', { name: 'Check for updates' }));

    expect(screen.getByText('Checking for updates…')).toHaveProp('accessibilityLiveRegion', 'polite');
    expect(screen.getByRole('button', { name: 'Check for updates' })).toBeDisabled();
    expect(mockedRequest).toHaveBeenCalledTimes(1);

    await act(async () => { resolveRequest({ data: release }); });

    expect(await screen.findByText('Delight 0.2.0 is available.')).toBeOnTheScreen();
    expect(screen.getByRole('link', { name: 'View update' })).toBeEnabled();
  });

  it.each(['light', 'dark'] as const)('opens the official page for an update in %s mode', async (mode) => {
    jest.mocked(useTheme).mockReturnValue({ colors: themeTokens[mode], mode });
    await render(<AndroidUpdateSettings />);

    await fireEvent.press(screen.getByRole('button', { name: 'Check for updates' }));
    expect(await screen.findByText('Delight 0.2.0 is available.')).toHaveProp('accessibilityLiveRegion', 'polite');
    await fireEvent.press(screen.getByRole('link', { name: 'View update' }));

    expect(mockedOpenURL).toHaveBeenCalledWith(release.update_url);
  });

  it('shows an up-to-date result and permits another deliberate check', async () => {
    mockedRequest.mockResolvedValue({ data: { ...release, version_code: 9 } });
    await render(<AndroidUpdateSettings />);

    await fireEvent.press(screen.getByRole('button', { name: 'Check for updates' }));
    expect(await screen.findByText('Delight is up to date.')).toBeOnTheScreen();
    expect(screen.queryByRole('link', { name: 'View update' })).not.toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Check again' }));
    expect(mockedRequest).toHaveBeenCalledTimes(2);
  });

  it.each([
    ['offline', () => Promise.reject(new Error('Offline'))],
    ['invalid response', () => Promise.resolve({ data: { ...release, update_url: 'https://example.com' } })],
  ])('offers retry after %s instead of claiming the app is current', async (_name, failedRequest) => {
    mockedRequest.mockImplementationOnce(failedRequest);
    await render(<AndroidUpdateSettings />);

    await fireEvent.press(screen.getByRole('button', { name: 'Check for updates' }));
    expect(await screen.findByText('Unable to check for updates. Try again.')).toHaveProp(
      'accessibilityLiveRegion',
      'assertive',
    );
    expect(screen.queryByText('Delight is up to date.')).not.toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Try again' }));

    expect(await screen.findByText('Delight 0.2.0 is available.')).toBeOnTheScreen();
  });

  it('gives Play installs only a store-managed explanation and store link', async () => {
    mockedInstallerSource.mockReturnValue('play');
    await render(<AndroidUpdateSettings />);

    expect(screen.getByText('Updates are managed by Google Play.')).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Check for updates' })).not.toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('link', { name: 'Open Google Play' }));

    expect(mockedOpenURL).toHaveBeenCalledWith(
      'https://play.google.com/store/apps/details?id=com.orlandovillanueva.delight',
    );
    expect(mockedRequest).not.toHaveBeenCalled();
  });

  it.each(['installer', 'version'])('explains an unknown %s and offers the Android page', async (unknown) => {
    if (unknown === 'installer') {
      mockedInstallerSource.mockReturnValue('unknown');
    } else {
      Object.defineProperty(Application, 'nativeBuildVersion', { value: null, configurable: true });
    }

    await render(<AndroidUpdateSettings />);

    expect(screen.getByText(/Unable to determine/)).toBeOnTheScreen();
    expect(screen.queryByRole('button')).not.toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('link', { name: 'Visit Android page' }));

    expect(mockedOpenURL).toHaveBeenCalledWith('https://mydelight.app/android');
    expect(mockedRequest).not.toHaveBeenCalled();
  });

  it.each(['direct', 'play'])('reports link failure for %s installs and allows the same action again', async (source) => {
    mockedOpenURL.mockRejectedValueOnce(new Error('No browser available'));
    if (source === 'play') {
      mockedInstallerSource.mockReturnValue('play');
    }
    await render(<AndroidUpdateSettings />);

    if (source === 'direct') {
      await fireEvent.press(screen.getByRole('button', { name: 'Check for updates' }));
      await screen.findByText('Delight 0.2.0 is available.');
    }

    const label = source === 'play' ? 'Open Google Play' : 'View update';
    await fireEvent.press(screen.getByRole('link', { name: label }));
    const message = 'That resource could not be opened. Try again.';
    expect(await screen.findByText(message)).toBeOnTheScreen();
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith(message);

    await fireEvent.press(screen.getByRole('link', { name: label }));
    expect(mockedOpenURL).toHaveBeenCalledTimes(2);
    expect(screen.queryByText(message)).not.toBeOnTheScreen();
  });

  it.each(['ios', 'web', 'disabled'])('hides the section for %s', async (unsupported) => {
    if (unsupported === 'disabled') {
      environment.androidUpdateCheckerEnabled = false;
    } else {
      Object.defineProperty(Platform, 'OS', { value: unsupported, configurable: true });
    }
    await render(<AndroidUpdateSettings />);

    expect(screen.queryByText('UPDATES')).not.toBeOnTheScreen();
    expect(mockedRequest).not.toHaveBeenCalled();
  });
});
