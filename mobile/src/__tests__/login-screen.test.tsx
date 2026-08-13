import * as Linking from 'expo-linking';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { AccessibilityInfo } from 'react-native';

import { ApiError } from '@/api/api-error';
import LoginScreen from '@/app/(auth)/login';
import { useAuth } from '@/auth/auth-context';

jest.mock('expo-linking', () => ({ openURL: jest.fn() }));
jest.mock('@/auth/auth-context', () => ({ useAuth: jest.fn() }));
jest.mock('@/config/environment', () => ({
  environment: { apiUrl: 'https://delight-staging.laravel.cloud', appVariant: 'development' },
}));

const login = jest.fn();

describe('native Login screen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    login.mockReset();
    jest.mocked(Linking.openURL).mockResolvedValue(true);
    jest.mocked(useAuth).mockReturnValue({ login, isLoggingIn: false } as unknown as ReturnType<typeof useAuth>);
  });

  async function fillAndSubmit() {
    await fireEvent.changeText(screen.getByLabelText('Email'), 'reader@example.com');
    await fireEvent.changeText(screen.getByLabelText('Password'), 'password');
    await fireEvent.press(screen.getByLabelText('Sign in'));
  }

  it('submits credentials and preserves the native form on success', async () => {
    login.mockResolvedValue(undefined);
    await render(<LoginScreen />);

    expect(screen.getByTestId('login-email-input')).toBeOnTheScreen();
    expect(screen.getByTestId('login-password-input')).toBeOnTheScreen();
    expect(screen.getByTestId('login-submit-button')).toBeOnTheScreen();

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
    expect(screen.getByText(/Used Google to create your account/)).toBeOnTheScreen();
  });

  it('shows and announces a recoverable error when a web route cannot open', async () => {
    jest.mocked(Linking.openURL).mockRejectedValueOnce(new Error('No browser available'));
    await render(<LoginScreen />);

    await fireEvent.press(screen.getByLabelText('Create an account on the web'));

    const message = 'The web page could not be opened. Try again.';
    expect(await screen.findByText(message)).toBeOnTheScreen();
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith(message);
  });
});
