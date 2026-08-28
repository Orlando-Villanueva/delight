import Constants, { AppOwnership } from 'expo-constants';
import {
  GoogleOneTapSignIn,
  isCancelledResponse,
  isErrorWithCode,
  isNoSavedCredentialFoundResponse,
  isSuccessResponse,
  statusCodes,
} from 'react-native-nitro-google-signin';

import {
  beginGoogleSignIn,
  clearGoogleSignInSession,
  isGoogleSignInAvailable,
  NativeGoogleSignInError,
  resetGoogleSignInForTests,
  setGoogleSignInModuleForTests,
  setGoogleSignInPlatformForTests,
} from '@/auth/google-sign-in';

jest.mock('expo-constants', () => ({
  __esModule: true,
  AppOwnership: { Expo: 'expo' },
  default: { appOwnership: null },
}));
jest.mock('@/config/environment', () => ({
  environment: {
    apiUrl: 'https://delight-staging.laravel.cloud',
    appVariant: 'preview',
    googleWebClientId: 'web-client.apps.googleusercontent.com',
  },
}));
jest.mock('react-native-nitro-google-signin', () => ({
  GoogleOneTapSignIn: {
    configure: jest.fn(),
    checkPlayServices: jest.fn(),
    signIn: jest.fn(),
    createAccount: jest.fn(),
    presentExplicitSignIn: jest.fn(),
    signOut: jest.fn(),
  },
  isCancelledResponse: jest.fn((response) => response.type === 'cancelled'),
  isErrorWithCode: jest.fn((error) => typeof error === 'object' && error !== null && 'code' in error),
  isNoSavedCredentialFoundResponse: jest.fn(
    (response) => response.type === 'noSavedCredentialFound',
  ),
  isSuccessResponse: jest.fn((response) => response.type === 'success'),
  statusCodes: {
    DEVELOPER_ERROR: 'DEVELOPER_ERROR',
    PLAY_SERVICES_NOT_AVAILABLE: 'PLAY_SERVICES_NOT_AVAILABLE',
    SIGN_IN_CANCELLED: 'SIGN_IN_CANCELLED',
  },
}));

const mockedNative = jest.mocked(GoogleOneTapSignIn);
const mockedConstants = jest.mocked(Constants);

describe('native Google sign-in adapter', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    resetGoogleSignInForTests();
    setGoogleSignInModuleForTests(jest.requireMock('react-native-nitro-google-signin'));
    setGoogleSignInPlatformForTests('android');
    process.env.EXPO_OS = 'android';
    mockedConstants.appOwnership = null;
    mockedNative.checkPlayServices.mockResolvedValue();
    mockedNative.signOut.mockResolvedValue();
  });

  it('stays unavailable inside Expo Go', () => {
    mockedConstants.appOwnership = AppOwnership.Expo;

    expect(isGoogleSignInAvailable()).toBe(false);
  });

  it('configures once without scopes or offline access and returns only the ID token', async () => {
    mockedNative.signIn.mockResolvedValue({
      type: 'success',
      data: {
        idToken: 'google-id-token',
        scopes: [],
        serverAuthCode: null,
        user: {
          id: 'subject',
          email: 'reader@example.com',
          name: 'Reader',
          givenName: 'Delight',
          familyName: 'Reader',
          photo: null,
        },
      },
    });

    await expect(beginGoogleSignIn()).resolves.toEqual({
      status: 'success',
      idToken: 'google-id-token',
    });
    await beginGoogleSignIn();

    expect(mockedNative.configure).toHaveBeenCalledTimes(1);
    expect(mockedNative.configure).toHaveBeenCalledWith({
      webClientId: 'web-client.apps.googleusercontent.com',
      autoSelectOnSignIn: false,
    });
  });

  it('falls back through the interactive flows when no credential is saved', async () => {
    mockedNative.signIn.mockResolvedValue({ type: 'noSavedCredentialFound', data: null });
    mockedNative.createAccount.mockResolvedValue({ type: 'noSavedCredentialFound', data: null });
    mockedNative.presentExplicitSignIn.mockResolvedValue({ type: 'cancelled', data: null });

    await expect(beginGoogleSignIn()).resolves.toEqual({ status: 'cancelled' });
    expect(mockedNative.createAccount).toHaveBeenCalled();
    expect(mockedNative.presentExplicitSignIn).toHaveBeenCalled();
  });

  it('treats thrown cancellation as a neutral result', async () => {
    mockedNative.signIn.mockRejectedValue({
      code: statusCodes.SIGN_IN_CANCELLED,
      message: 'cancelled',
    });

    await expect(beginGoogleSignIn()).resolves.toEqual({ status: 'cancelled' });
  });

  it('maps unavailable Play Services to a recoverable error', async () => {
    mockedNative.checkPlayServices.mockRejectedValue({
      code: statusCodes.PLAY_SERVICES_NOT_AVAILABLE,
      message: 'missing',
    });

    await expect(beginGoogleSignIn()).rejects.toEqual(expect.objectContaining({
      kind: 'play-services',
    } satisfies Partial<NativeGoogleSignInError>));
  });

  it('clears the local Google session without revoking consent', async () => {
    await clearGoogleSignInSession();

    expect(mockedNative.signOut).toHaveBeenCalled();
    expect(isCancelledResponse).not.toHaveBeenCalled();
    expect(isErrorWithCode).not.toHaveBeenCalled();
    expect(isNoSavedCredentialFoundResponse).not.toHaveBeenCalled();
    expect(isSuccessResponse).not.toHaveBeenCalled();
  });
});
