import Constants, { AppOwnership } from 'expo-constants';
import { Platform } from 'react-native';

import { environment } from '@/config/environment';

type NitroGoogleSignIn = typeof import('react-native-nitro-google-signin');

export type GoogleSignInResult =
  | { status: 'success'; idToken: string }
  | { status: 'cancelled' };

export type NativeGoogleSignInErrorKind =
  | 'configuration'
  | 'play-services'
  | 'unexpected';

export class NativeGoogleSignInError extends Error {
  constructor(
    message: string,
    public readonly kind: NativeGoogleSignInErrorKind,
  ) {
    super(message);
    this.name = 'NativeGoogleSignInError';
  }
}

let nativeModulePromise: Promise<NitroGoogleSignIn> | null = null;
let configuredClientId: string | null = null;
let platformOverride: typeof Platform.OS | null = null;

function isExpoGo(): boolean {
  return Constants.appOwnership === AppOwnership.Expo;
}

export function isGoogleSignInAvailable(): boolean {
  return (platformOverride ?? Platform.OS) === 'android'
    && !isExpoGo()
    && typeof environment.googleWebClientId === 'string';
}

async function nativeModule(): Promise<NitroGoogleSignIn> {
  if (!nativeModulePromise) {
    nativeModulePromise = import('react-native-nitro-google-signin').catch((error: unknown) => {
      nativeModulePromise = null;
      throw error;
    });
  }

  return nativeModulePromise;
}

async function configuredModule(): Promise<NitroGoogleSignIn> {
  const clientId = environment.googleWebClientId;

  if (!isGoogleSignInAvailable() || !clientId) {
    throw new NativeGoogleSignInError(
      'Google sign-in is not configured for this build.',
      'configuration',
    );
  }

  try {
    const module = await nativeModule();

    if (configuredClientId !== clientId) {
      module.GoogleOneTapSignIn.configure({
        webClientId: clientId,
        autoSelectOnSignIn: false,
      });
      configuredClientId = clientId;
    }

    return module;
  } catch (error) {
    if (error instanceof NativeGoogleSignInError) {
      throw error;
    }

    throw new NativeGoogleSignInError(
      'Google sign-in is unavailable in this build.',
      'configuration',
    );
  }
}

function nativeError(
  error: unknown,
  module: NitroGoogleSignIn,
): NativeGoogleSignInError | null {
  if (!module.isErrorWithCode(error)) {
    return null;
  }

  if (error.code === module.statusCodes.PLAY_SERVICES_NOT_AVAILABLE) {
    return new NativeGoogleSignInError(
      'Google Play Services is unavailable or needs to be updated.',
      'play-services',
    );
  }

  if (error.code === module.statusCodes.DEVELOPER_ERROR) {
    return new NativeGoogleSignInError(
      'Google sign-in is not configured correctly for this build.',
      'configuration',
    );
  }

  return null;
}

export async function beginGoogleSignIn(): Promise<GoogleSignInResult> {
  const module = await configuredModule();

  try {
    await module.GoogleOneTapSignIn.checkPlayServices();

    let response = await module.GoogleOneTapSignIn.signIn();

    if (module.isNoSavedCredentialFoundResponse(response)) {
      response = await module.GoogleOneTapSignIn.createAccount();
    }

    if (module.isNoSavedCredentialFoundResponse(response)) {
      response = await module.GoogleOneTapSignIn.presentExplicitSignIn();
    }

    if (module.isCancelledResponse(response)) {
      return { status: 'cancelled' };
    }

    if (module.isSuccessResponse(response)) {
      return { status: 'success', idToken: response.data.idToken };
    }

    throw new NativeGoogleSignInError(
      'Google sign-in could not be completed. Try again.',
      'unexpected',
    );
  } catch (error) {
    if (error instanceof NativeGoogleSignInError) {
      throw error;
    }

    if (
      module.isErrorWithCode(error)
      && error.code === module.statusCodes.SIGN_IN_CANCELLED
    ) {
      return { status: 'cancelled' };
    }

    throw nativeError(error, module) ?? new NativeGoogleSignInError(
      'Google sign-in could not be completed. Try again.',
      'unexpected',
    );
  }
}

export async function clearGoogleSignInSession(): Promise<void> {
  if (!isGoogleSignInAvailable()) {
    return;
  }

  const module = await configuredModule();
  await module.GoogleOneTapSignIn.signOut();
}

export function resetGoogleSignInForTests(): void {
  nativeModulePromise = null;
  configuredClientId = null;
  platformOverride = null;
}

export function setGoogleSignInModuleForTests(module: NitroGoogleSignIn): void {
  nativeModulePromise = Promise.resolve(module);
}

export function setGoogleSignInPlatformForTests(platform: typeof Platform.OS): void {
  platformOverride = platform;
}
