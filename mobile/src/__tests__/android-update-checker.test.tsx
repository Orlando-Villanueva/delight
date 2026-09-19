import * as SecureStore from 'expo-secure-store';
import * as Linking from 'expo-linking';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  act,
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react-native';
import { AppState } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { apiRequest } from '@/api/client';
import {
  androidUpdateCheckCooldownMs,
  androidUpdateDismissalWindowMs,
} from '@/api/android-update';
import { AndroidUpdateChecker } from '@/components/android-update-checker';
import { environment } from '@/config/environment';
import { getAndroidInstallerSource } from '@/native/installer-source';

jest.mock('@/api/client', () => ({ apiRequest: jest.fn() }));
jest.mock('@/native/installer-source', () => ({ getAndroidInstallerSource: jest.fn() }));
jest.mock('@/config/environment', () => ({
  environment: {
    apiUrl: 'https://mydelight.app',
    appVariant: 'production',
    androidUpdateCheckerEnabled: true,
  },
}));
jest.mock('expo-application', () => ({ nativeBuildVersion: '9' }));
jest.mock('expo-linking', () => ({ openURL: jest.fn() }));
jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn(),
}));

const mockedApiRequest = jest.mocked(apiRequest);
const mockedGetItem = jest.mocked(SecureStore.getItemAsync);
const mockedSetItem = jest.mocked(SecureStore.setItemAsync);
const mockedOpenURL = jest.mocked(Linking.openURL);
const mockedInstallerSource = jest.mocked(getAndroidInstallerSource);
const mockedEnvironment = environment;

let mockAppStateListener: ((state: 'active' | 'background') => void) | undefined;
const queryClients = new Set<QueryClient>();

jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, listener) => {
  mockAppStateListener = listener as (state: 'active' | 'background') => void;

  return { remove: jest.fn() };
});

function renderChecker() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } },
  });
  queryClients.add(queryClient);
  const rendered = render(
    <SafeAreaProvider
      initialMetrics={{
        frame: { x: 0, y: 0, width: 390, height: 844 },
        insets: { top: 47, left: 0, right: 0, bottom: 34 },
      }}
    >
      <QueryClientProvider client={queryClient}>
        <AndroidUpdateChecker />
      </QueryClientProvider>
    </SafeAreaProvider>,
  );

  return {
    queryClient,
    ...rendered,
  };
}

describe('Android update checker', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockAppStateListener = undefined;
    mockedGetItem.mockResolvedValue(null);
    mockedSetItem.mockResolvedValue(undefined);
    mockedOpenURL.mockResolvedValue(true);
    mockedInstallerSource.mockReturnValue('non-play');
    mockedEnvironment.androidUpdateCheckerEnabled = true;
  });

  afterEach(async () => {
    await act(async () => {
      for (const queryClient of queryClients) {
        await queryClient.cancelQueries();
        queryClient.clear();
      }
    });

    queryClients.clear();
    await cleanup();
  });

  it('prompts an older production build and opens the canonical page', async () => {
    mockedApiRequest.mockResolvedValue({
      data: {
        version: '0.2.0',
        version_code: 10,
        update_url: 'https://mydelight.app/android',
      },
    });

    renderChecker();

    await waitFor(() => expect(screen.getByText('A newer Delight version is ready')).toBeOnTheScreen());
    expect(screen.getByText('Delight 0.2.0 is ready.')).toBeOnTheScreen();

    await fireEvent.press(screen.getByLabelText('Download update'));

    expect(mockedOpenURL).toHaveBeenCalledWith('https://mydelight.app/android');
  });

  it('persists the release when the user chooses Later', async () => {
    mockedApiRequest.mockResolvedValue({
      data: {
        version: '0.2.0',
        version_code: 10,
        update_url: 'https://mydelight.app/android',
      },
    });

    renderChecker();

    await waitFor(() => expect(screen.getByText('A newer Delight version is ready')).toBeOnTheScreen());
    await fireEvent.press(screen.getByLabelText('Later'));

    expect(mockedSetItem).toHaveBeenCalledWith(
      'delight.android-update-dismissed',
      expect.stringContaining('"versionCode":10'),
    );
    expect(screen.queryByText('A newer Delight version is ready')).not.toBeOnTheScreen();
  });

  it('does not prompt when the installed build is current', async () => {
    mockedApiRequest.mockResolvedValue({
      data: {
        version: '0.1.0',
        version_code: 9,
        update_url: 'https://mydelight.app/android',
      },
    });

    renderChecker();

    await waitFor(() => expect(mockedApiRequest).toHaveBeenCalledWith('/api/v1/android/release'));
    expect(mockedOpenURL).not.toHaveBeenCalled();
    expect(mockedSetItem).not.toHaveBeenCalled();
  });

  it('does not request direct-download metadata for a Play-installed build', async () => {
    mockedInstallerSource.mockReturnValue('play');

    renderChecker();

    expect(mockedApiRequest).not.toHaveBeenCalled();
  });

  it('does not request metadata when the environment disables the checker', async () => {
    mockedEnvironment.androidUpdateCheckerEnabled = false;

    renderChecker();

    expect(mockedApiRequest).not.toHaveBeenCalled();
  });

  it('fails closed when Android cannot identify the installer', async () => {
    mockedInstallerSource.mockReturnValue('unknown');

    renderChecker();

    expect(mockedApiRequest).not.toHaveBeenCalled();
  });

  it('does not prompt again during the dismissal window but prompts for a newer release', async () => {
    mockedGetItem.mockResolvedValue(JSON.stringify({ versionCode: 10, dismissedAt: Date.now() }));
    mockedApiRequest.mockResolvedValue({
      data: {
        version: '0.2.0',
        version_code: 10,
        update_url: 'https://mydelight.app/android',
      },
    });

    const { queryClient } = renderChecker();
    await waitFor(() => expect(mockedApiRequest).toHaveBeenCalled());
    expect(mockedOpenURL).not.toHaveBeenCalled();
    expect(mockedSetItem).not.toHaveBeenCalled();

    mockedApiRequest.mockResolvedValue({
      data: {
        version: '0.3.0',
        version_code: 11,
        update_url: 'https://mydelight.app/android',
      },
    });
    await cleanup();
    await act(async () => {
      await queryClient.cancelQueries();
      queryClient.clear();
    });

    const newer = renderChecker();
    await waitFor(() => expect(screen.getByText('Delight 0.3.0 is ready.')).toBeOnTheScreen());
    await cleanup();
    await act(async () => {
      await newer.queryClient.cancelQueries();
      newer.queryClient.clear();
    });
  });

  it('prompts again after a dismissed release expires and the app foregrounds', async () => {
    const initialTime = 1_700_000_000_000;
    const dateNow = jest.spyOn(Date, 'now').mockReturnValue(initialTime);
    mockedGetItem.mockResolvedValue(JSON.stringify({ versionCode: 10, dismissedAt: initialTime }));
    mockedApiRequest.mockResolvedValue({
      data: {
        version: '0.2.0',
        version_code: 10,
        update_url: 'https://mydelight.app/android',
      },
    });

    renderChecker();
    await waitFor(() => expect(mockedApiRequest).toHaveBeenCalled());
    expect(mockedOpenURL).not.toHaveBeenCalled();
    expect(mockedSetItem).not.toHaveBeenCalled();

    dateNow.mockReturnValue(
      initialTime + androidUpdateDismissalWindowMs + androidUpdateCheckCooldownMs,
    );
    await act(async () => {
      mockAppStateListener?.('active');
    });

    await waitFor(() => expect(screen.getByText('Delight 0.2.0 is ready.')).toBeOnTheScreen());

    dateNow.mockRestore();
  });

  it('fails quietly for malformed metadata', async () => {
    mockedApiRequest.mockResolvedValue({
      data: {
        version: '0.2.0',
        version_code: 10,
        update_url: 'https://malicious.example/android',
      },
    });

    renderChecker();

    await waitFor(() => expect(mockedApiRequest).toHaveBeenCalled());
    expect(mockedOpenURL).not.toHaveBeenCalled();
    expect(mockedSetItem).not.toHaveBeenCalled();
  });
});
