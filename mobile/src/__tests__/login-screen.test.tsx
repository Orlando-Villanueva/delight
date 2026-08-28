import * as Linking from 'expo-linking';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { AccessibilityInfo } from 'react-native';

import { ApiError } from '@/api/api-error';
import LoginScreen from '@/app/(auth)/login';
import { useAuth } from '@/auth/auth-context';
import { NativeGoogleSignInError } from '@/auth/google-sign-in';

jest.mock('expo-linking', () => ({ openURL: jest.fn() }));
jest.mock('@/auth/auth-context', () => ({ useAuth: jest.fn() }));
jest.mock('@/config/environment', () => ({
  environment: { apiUrl: 'https://delight-staging.laravel.cloud', appVariant: 'development' },
}));

const login = jest.fn();
const loginWithGoogle = jest.fn();
const confirmGoogleLink = jest.fn();
const cancelGoogleLink = jest.fn();

function mockAuth(overrides: Partial<ReturnType<typeof useAuth>> = {}) {
  jest.mocked(useAuth).mockReturnValue({
    login,
    loginWithGoogle,
    confirmGoogleLink,
    cancelGoogleLink,
    isGoogleSignInAvailable: false,
    isGooglePasswordRequired: false,
    isLoggingIn: false,
    isLoggingInWithGoogle: false,
    ...overrides,
  } as unknown as ReturnType<typeof useAuth>);
}

describe('native Login screen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    login.mockReset();
    loginWithGoogle.mockReset();
    confirmGoogleLink.mockReset();
    cancelGoogleLink.mockReset();
    jest.mocked(Linking.openURL).mockResolvedValue(true);
    mockAuth();
  });

  async function fillAndSubmit() {
    await fireEvent.changeText(screen.getByLabelText('Email'), 'reader@example.com');
    await fireEvent.changeText(screen.getByLabelText('Password'), 'password');
    await fireEvent.press(screen.getByLabelText('Sign in'));
  }

  it('submits credentials and preserves the native form on success', async () => {
    login.mockResolvedValue(undefined);
    await render(<LoginScreen />);

    expect(screen.getByLabelText('Delight')).toBeOnTheScreen();
    expect(screen.getByText('Welcome back')).toBeOnTheScreen();
    expect(screen.getByText('Continue your reading rhythm.')).toBeOnTheScreen();
    expect(screen.queryByText('Return to Delight')).not.toBeOnTheScreen();

    await fillAndSubmit();
    await waitFor(() => expect(login).toHaveBeenCalledWith({ email: 'reader@example.com', password: 'password' }));
  });

  it('shows generic invalid credentials and server field validation', async () => {
    login.mockRejectedValue(new ApiError('These credentials do not match our records.', 'http', 422, { email: ['These credentials do not match our records.'] }));
    await render(<LoginScreen />);
    await fillAndSubmit();
    await waitFor(() => expect(screen.getByText('These credentials do not match our records.')).toBeOnTheScreen());
    expect(screen.getByDisplayValue('reader@example.com')).toBeOnTheScreen();
  });

  it('preserves input and allows manual retry after a network failure', async () => {
    login.mockRejectedValueOnce(new ApiError('Delight could not connect. Check your connection and try again.', 'network')).mockResolvedValueOnce(undefined);
    await render(<LoginScreen />);
    await fillAndSubmit();
    await screen.findByText('Delight could not connect. Check your connection and try again.');
    expect(screen.getByDisplayValue('reader@example.com')).toBeOnTheScreen();
    await fireEvent.press(screen.getByLabelText('Sign in'));
    await waitFor(() => expect(login).toHaveBeenCalledTimes(2));
  });

  it('starts a retry cooldown after a rate-limit response', async () => {
    jest.useFakeTimers();
    login.mockRejectedValue(new ApiError('Too many attempts.', 'http', 429, {}, 30));
    await render(<LoginScreen />);

    await fillAndSubmit();

    await waitFor(() => expect(screen.getByText('Try again in 30s')).toBeOnTheScreen());
    expect(screen.getByLabelText('Sign in')).toBeDisabled();

    await act(async () => {
      jest.advanceTimersByTime(1_000);
    });

    expect(screen.getByText('Try again in 29s')).toBeOnTheScreen();
  });

  it('opens staging registration and recovery routes and explains Google accounts', async () => {
    await render(<LoginScreen />);
    await fireEvent.press(screen.getByLabelText('Create an account on the web'));
    await fireEvent.press(screen.getByLabelText('Reset your password on the web'));
    expect(Linking.openURL).toHaveBeenNthCalledWith(1, 'https://delight-staging.laravel.cloud/register');
    expect(Linking.openURL).toHaveBeenNthCalledWith(2, 'https://delight-staging.laravel.cloud/forgot-password');
    expect(screen.getByText('Created your account with Google? Set a password on the web.')).toBeOnTheScreen();
  });

  it('shows and announces a recoverable error when a web route cannot open', async () => {
    jest.mocked(Linking.openURL).mockRejectedValueOnce(new Error('No browser available'));
    await render(<LoginScreen />);

    await fireEvent.press(screen.getByLabelText('Create an account on the web'));

    const message = 'The web page could not be opened. Try again.';
    expect(await screen.findByText(message)).toBeOnTheScreen();
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith(message);
  });

  it('offers accessible Google sign-in without removing email and password', async () => {
    mockAuth({ isGoogleSignInAvailable: true });
    loginWithGoogle.mockResolvedValue('authenticated');
    await render(<LoginScreen />);

    expect(screen.getByLabelText('Continue with Google')).toBeOnTheScreen();
    expect(screen.getByLabelText('Email')).toBeOnTheScreen();
    expect(screen.getByLabelText('Password')).toBeOnTheScreen();
    expect(screen.queryByText('Created your account with Google? Set a password on the web.'))
      .not.toBeOnTheScreen();

    await fireEvent.press(screen.getByLabelText('Continue with Google'));
    await waitFor(() => expect(loginWithGoogle).toHaveBeenCalled());
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith('Signed in.');
  });

  it('treats Google cancellation neutrally', async () => {
    mockAuth({ isGoogleSignInAvailable: true });
    loginWithGoogle.mockResolvedValue('cancelled');
    await render(<LoginScreen />);

    await fireEvent.press(screen.getByLabelText('Continue with Google'));

    await waitFor(() => expect(loginWithGoogle).toHaveBeenCalled());
    expect(screen.queryByText('Google sign-in could not be completed. Try again.'))
      .not.toBeOnTheScreen();
  });

  it('collects Delight password proof and keeps incorrect proof recoverable', async () => {
    mockAuth({
      isGoogleSignInAvailable: true,
      isGooglePasswordRequired: true,
    });
    confirmGoogleLink.mockRejectedValue(new ApiError(
      'The password is incorrect.',
      'http',
      422,
      { password: ['The password is incorrect.'] },
    ));
    await render(<LoginScreen />);

    await fireEvent.changeText(
      screen.getByLabelText('Delight password for Google account'),
      'wrong-password',
    );
    await fireEvent.press(screen.getByLabelText('Confirm Google account linking'));

    expect(await screen.findByText('The password is incorrect.')).toBeOnTheScreen();
    expect(confirmGoogleLink).toHaveBeenCalledWith('wrong-password');

    await fireEvent.press(screen.getByLabelText('Cancel Google account linking'));
    expect(cancelGoogleLink).toHaveBeenCalled();
  });

  it('disables password proof retry for the server cooldown after a 429 response', async () => {
    mockAuth({
      isGoogleSignInAvailable: true,
      isGooglePasswordRequired: true,
    });
    confirmGoogleLink.mockRejectedValue(
      new ApiError('Too many attempts.', 'http', 429, {}, 30),
    );
    await render(<LoginScreen />);

    await fireEvent.changeText(
      screen.getByLabelText('Delight password for Google account'),
      'ValidPass123!',
    );
    await fireEvent.press(screen.getByLabelText('Confirm Google account linking'));

    expect(await screen.findByText('Try again in 30s')).toBeOnTheScreen();
    expect(screen.getByLabelText('Confirm Google account linking')).toBeDisabled();
    expect(screen.getByLabelText('Cancel Google account linking')).toBeEnabled();

    await fireEvent.press(screen.getByLabelText('Confirm Google account linking'));
    expect(confirmGoogleLink).toHaveBeenCalledTimes(1);
  });

  it('prevents Google password confirmation while email login is pending', async () => {
    mockAuth({
      isGoogleSignInAvailable: true,
      isGooglePasswordRequired: true,
      isLoggingIn: true,
    });
    await render(<LoginScreen />);

    const confirmButton = screen.getByLabelText('Confirm Google account linking');
    expect(confirmButton).toBeDisabled();
    expect(screen.getByLabelText('Cancel Google account linking')).toBeEnabled();

    await fireEvent.press(confirmButton);
    expect(confirmGoogleLink).not.toHaveBeenCalled();
  });

  it('shows a recoverable Play Services error while leaving email login available', async () => {
    mockAuth({ isGoogleSignInAvailable: true });
    loginWithGoogle.mockRejectedValue(new NativeGoogleSignInError(
      'Google Play Services is unavailable or needs to be updated.',
      'play-services',
    ));
    await render(<LoginScreen />);

    await fireEvent.press(screen.getByLabelText('Continue with Google'));

    expect(await screen.findByText('Google Play Services is unavailable or needs to be updated.'))
      .toBeOnTheScreen();
    expect(screen.getByLabelText('Sign in')).toBeEnabled();
  });
});
