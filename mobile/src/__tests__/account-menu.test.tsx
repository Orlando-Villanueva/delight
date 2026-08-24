import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';
import { AccessibilityInfo } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { ApiError } from '@/api/api-error';
import { AccountMenu, accountInitials } from '@/components/account-menu';
import { useAuth, useAuthenticatedApi, type AuthUser } from '@/auth/auth-context';

jest.mock('@/auth/auth-context', () => ({
  useAuth: jest.fn(),
  useAuthenticatedApi: jest.fn(),
}));

const request = jest.fn();
const logout = jest.fn();
let queryClient: QueryClient;

const sessionUser: AuthUser = { id: 1, name: 'Reader', email: 'reader@example.com' };

function bootstrap(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      user: { id: 2, name: 'Restored Reader', email: 'restored@example.com' },
      today: '2026-08-10',
      yesterday: '2026-08-09',
      books: [],
      recent_book_ids: [],
      has_read_today: false,
      current_streak: 0,
      longest_streak: 0,
      this_week_days: 0,
      this_month_days: 0,
      activity: [],
      ...overrides,
    },
  };
}

function mockAuth(user: typeof sessionUser | null = sessionUser) {
  jest.mocked(useAuth).mockReturnValue({
    user,
    logout,
    isLoggingOut: false,
  } as unknown as ReturnType<typeof useAuth>);
}

async function renderMenu() {
  queryClient = new QueryClient({
    defaultOptions: {
      queries: { gcTime: 0, retry: false },
      mutations: { gcTime: 0, retry: false },
    },
  });

  function Wrapper({ children }: PropsWithChildren) {
    return (
      <SafeAreaProvider
        initialMetrics={{
          frame: { x: 0, y: 0, width: 390, height: 844 },
          insets: { top: 47, left: 0, right: 0, bottom: 34 },
        }}
      >
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      </SafeAreaProvider>
    );
  }

  return render(<AccountMenu />, { wrapper: Wrapper });
}

describe('account initials', () => {
  it('uses the first and last name letters', () => {
    expect(accountInitials('Orlando Villanueva')).toBe('OV');
    expect(accountInitials('Reader')).toBe('R');
    expect(accountInitials('  Jean Luc Picard  ')).toBe('JP');
    expect(accountInitials('   ')).toBeNull();
    expect(accountInitials(undefined)).toBeNull();
  });
});

describe('account menu', () => {
  beforeEach(() => {
    request.mockReset();
    logout.mockReset();
    logout.mockResolvedValue(undefined);
    request.mockResolvedValue(bootstrap());
    jest.mocked(useAuthenticatedApi).mockReturnValue(request);
    mockAuth();
  });

  afterEach(async () => {
    await queryClient?.cancelQueries();
    queryClient?.clear();
    cleanup();
  });

  it('keeps sign out hidden until the account control is opened', async () => {
    await renderMenu();

    expect(screen.getByLabelText('Account for Reader')).toBeOnTheScreen();
    expect(screen.getByLabelText('Account for Reader')).toHaveProp(
      'accessibilityHint',
      'Shows the signed-in account and sign out',
    );
    expect(screen.getByText('R')).toBeOnTheScreen();
    expect(screen.queryByLabelText('Sign out')).not.toBeOnTheScreen();
    expect(screen.queryByText('Log out')).not.toBeOnTheScreen();
    expect(request).not.toHaveBeenCalled();
  });

  it('shows the signed-in name and email from the auth session', async () => {
    await renderMenu();

    await fireEvent.press(screen.getByLabelText('Account for Reader'));

    expect(screen.getByText('Reader')).toBeOnTheScreen();
    expect(screen.getByText('reader@example.com')).toBeOnTheScreen();
    expect(screen.getByLabelText('Sign out')).toBeOnTheScreen();
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith(
      'Signed in as Reader, reader@example.com.',
    );
  });

  it('shows the stored profile photo in the account control and sheet', async () => {
    mockAuth({ ...sessionUser, avatar_url: 'https://example.com/reader.jpg' });
    await renderMenu();

    const headerImage = screen.getByTestId('account-avatar-image');
    expect(headerImage).toHaveProp('source', { uri: 'https://example.com/reader.jpg' });
    expect(headerImage).toHaveStyle({ opacity: 0 });

    await fireEvent(headerImage, 'load');
    expect(headerImage).toHaveStyle({ opacity: 1 });

    await fireEvent.press(screen.getByLabelText('Account for Reader'));
    expect(screen.getByTestId('account-avatar-image')).toHaveStyle({ width: 56, height: 56 });
  });

  it('keeps initials visible while the photo loads and after it fails', async () => {
    mockAuth({ ...sessionUser, avatar_url: 'not-a-valid-image-url' });
    await renderMenu();

    expect(screen.getByText('R')).toBeOnTheScreen();
    await fireEvent(screen.getByTestId('account-avatar-image'), 'error');

    expect(screen.getByText('R')).toBeOnTheScreen();
    expect(screen.queryByTestId('account-avatar-image')).not.toBeOnTheScreen();
  });

  it('reads restored identity from bootstrap when the session has no user', async () => {
    mockAuth(null);
    let resolveBootstrap: (value: ReturnType<typeof bootstrap>) => void = () => undefined;
    request.mockReturnValue(new Promise((resolve) => {
      resolveBootstrap = resolve;
    }));
    await renderMenu();

    expect(screen.getByLabelText('Account')).toBeOnTheScreen();
    await fireEvent.press(screen.getByLabelText('Account'));
    expect(screen.getByText('Loading account…')).toBeOnTheScreen();
    expect(screen.getByLabelText('Sign out')).toBeOnTheScreen();

    await act(async () => {
      resolveBootstrap(bootstrap());
    });

    await waitFor(() => expect(screen.getByText('Restored Reader')).toBeOnTheScreen());
    expect(screen.getByText('restored@example.com')).toBeOnTheScreen();
    expect(request).toHaveBeenCalledWith('/api/v1/bootstrap');
  });

  it('dismisses the account surface from the overlay and close control', async () => {
    await renderMenu();

    await fireEvent.press(screen.getByLabelText('Account for Reader'));
    expect(screen.getByText('reader@example.com')).toBeOnTheScreen();

    await fireEvent.press(screen.getByLabelText('Dismiss account'));
    expect(screen.queryByText('reader@example.com')).not.toBeOnTheScreen();
    expect(screen.queryByLabelText('Sign out')).not.toBeOnTheScreen();

    await fireEvent.press(screen.getByLabelText('Account for Reader'));
    await fireEvent.press(screen.getByLabelText('Close account'));
    expect(screen.queryByText('reader@example.com')).not.toBeOnTheScreen();
  });

  it('signs out from the opened account surface', async () => {
    await renderMenu();

    await fireEvent.press(screen.getByLabelText('Account for Reader'));
    await fireEvent.press(screen.getByLabelText('Sign out'));

    expect(logout).toHaveBeenCalled();
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith('Signing out.');
  });

  it('keeps sign out available when restored identity cannot load', async () => {
    mockAuth(null);
    request.mockRejectedValue(new ApiError('Delight could not connect.', 'network'));
    await renderMenu();

    await fireEvent.press(screen.getByLabelText('Account'));
    expect(await screen.findByText('Account details are unavailable.')).toBeOnTheScreen();
    expect(screen.getByLabelText('Sign out')).toBeOnTheScreen();

    await fireEvent.press(screen.getByLabelText('Sign out'));
    expect(logout).toHaveBeenCalled();
  });
});
