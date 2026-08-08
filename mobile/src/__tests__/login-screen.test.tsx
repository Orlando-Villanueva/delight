import * as Linking from 'expo-linking';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

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

  it('opens staging registration and recovery routes and explains Google accounts', async () => {
    await render(<LoginScreen />);
    await fireEvent.press(screen.getByLabelText('Create an account on the web'));
    await fireEvent.press(screen.getByLabelText('Reset your password on the web'));
    expect(Linking.openURL).toHaveBeenNthCalledWith(1, 'https://delight-staging.laravel.cloud/register');
    expect(Linking.openURL).toHaveBeenNthCalledWith(2, 'https://delight-staging.laravel.cloud/forgot-password');
    expect(screen.getByText(/Used Google to create your account/)).toBeOnTheScreen();
  });
});
