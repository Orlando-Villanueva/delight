import { render, screen } from '@testing-library/react-native';
import * as SystemUI from 'expo-system-ui';
import type { PropsWithChildren } from 'react';
import * as ReactNative from 'react-native';
import { Text } from 'react-native';

import RootLayout from '@/app/_layout';
import { AuthGate } from '@/auth/auth-gate';
import { useAuth } from '@/auth/auth-context';

let mockSegments: string[] = ['(tabs)', 'home'];
let status: 'loading' | 'authenticated' | 'unauthenticated' = 'loading';
const MockText = Text;
let mockRootScreenOptions: {
  contentStyle?: { backgroundColor?: string };
  headerShown?: boolean;
} | undefined;
let mockSettingsScreenOptions: { animation?: string } | undefined;
let mockNavigationTheme: {
  dark?: boolean;
  colors?: Record<string, string>;
} | undefined;

jest.mock('expo-router', () => ({
  DarkTheme: {
    dark: true,
    colors: { notification: 'dark-notification' },
  },
  DefaultTheme: {
    dark: false,
    colors: { notification: 'light-notification' },
  },
  Redirect: ({ href }: { href: string }) => {
    return <MockText>Redirect:{href}</MockText>;
  },
  ThemeProvider: ({
    children,
    value,
  }: PropsWithChildren<{ value: typeof mockNavigationTheme }>) => {
    mockNavigationTheme = value;

    return children;
  },
  useSegments: () => mockSegments,
}));
jest.mock('expo-router/stack', () => {
  const React = jest.requireActual<typeof import('react')>('react');
  const { Text: NativeText } = jest.requireActual<typeof import('react-native')>('react-native');
  const Stack = ({ children, screenOptions }: PropsWithChildren<{
    screenOptions?: typeof mockRootScreenOptions;
  }>) => {
    mockRootScreenOptions = screenOptions;

    return (
      <>
        <NativeText>Root navigator</NativeText>
        {children}
      </>
    );
  };
  Stack.Screen = function MockStackScreen({
    name,
    options,
  }: {
    name: string;
    options?: typeof mockSettingsScreenOptions;
  }) {
    if (name === 'settings') {
      mockSettingsScreenOptions = options;
    }

    return null;
  };

  return { Stack };
});
jest.mock('expo-status-bar', () => ({ StatusBar: () => null }));
jest.mock('expo-system-ui', () => ({
  setBackgroundColorAsync: jest.fn().mockResolvedValue(undefined),
}));
jest.mock('@/auth/auth-context', () => ({
  AuthProvider: function MockAuthProvider({ children }: PropsWithChildren) {
    return children;
  },
  useAuth: jest.fn(),
}));

describe('authenticated route protection', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.spyOn(ReactNative, 'useColorScheme').mockReturnValue('light');
    mockSegments = ['(tabs)', 'home'];
    mockRootScreenOptions = undefined;
    mockSettingsScreenOptions = undefined;
    mockNavigationTheme = undefined;
    status = 'loading';
    jest.mocked(useAuth).mockImplementation(() => ({ status }) as ReturnType<typeof useAuth>);
  });

  it('shows only session restoration state while loading', async () => {
    await render(<AuthGate><Text>Protected Home</Text></AuthGate>);
    expect(screen.getByText('Opening Delight…')).toBeOnTheScreen();
    expect(screen.queryByText('Protected Home')).not.toBeOnTheScreen();
  });

  it('keeps the root navigator mounted while the session restores', async () => {
    await render(<RootLayout />);

    expect(screen.getByText('Root navigator')).toBeOnTheScreen();
  });

  it('uses a themed background and fade transition for Settings navigation', async () => {
    await render(<RootLayout />);

    expect(mockRootScreenOptions).toEqual({
      contentStyle: { backgroundColor: '#f8fafc' },
      headerShown: false,
    });
    expect(mockSettingsScreenOptions).toEqual({ animation: 'fade' });
    expect(mockNavigationTheme).toEqual({
      dark: false,
      colors: {
        background: '#f8fafc',
        border: '#cbd5e1',
        card: '#ffffff',
        notification: 'light-notification',
        primary: '#3366cc',
        text: '#0f172a',
      },
    });
  });

  it('keeps the navigator surfaces dark during dark-mode transitions', async () => {
    jest.spyOn(ReactNative, 'useColorScheme').mockReturnValue('dark');

    await render(<RootLayout />);

    expect(screen.getByTestId('app-background')).toHaveStyle({ flex: 1, backgroundColor: '#0f172a' });
    expect(SystemUI.setBackgroundColorAsync).toHaveBeenLastCalledWith('#0f172a');
    expect(mockRootScreenOptions?.contentStyle).toEqual({ backgroundColor: '#0f172a' });
    expect(mockNavigationTheme).toMatchObject({
      dark: true,
      colors: {
        background: '#0f172a',
        border: '#475569',
        card: '#1e293b',
        primary: '#7aa2f7',
        text: '#f8fafc',
      },
    });
  });

  it('updates the persistent and native backgrounds when the system theme changes', async () => {
    const { rerender } = await render(<RootLayout />);

    expect(screen.getByTestId('app-background')).toHaveStyle({ backgroundColor: '#f8fafc' });
    expect(SystemUI.setBackgroundColorAsync).toHaveBeenLastCalledWith('#f8fafc');

    jest.spyOn(ReactNative, 'useColorScheme').mockReturnValue('dark');
    await rerender(<RootLayout />);

    expect(screen.getByTestId('app-background')).toHaveStyle({ backgroundColor: '#0f172a' });
    expect(SystemUI.setBackgroundColorAsync).toHaveBeenLastCalledWith('#0f172a');

    jest.spyOn(ReactNative, 'useColorScheme').mockReturnValue('light');
    await rerender(<RootLayout />);

    expect(screen.getByTestId('app-background')).toHaveStyle({ backgroundColor: '#f8fafc' });
    expect(SystemUI.setBackgroundColorAsync).toHaveBeenLastCalledWith('#f8fafc');
  });

  it('redirects unauthenticated protected routes to Login', async () => {
    status = 'unauthenticated';
    await render(<AuthGate><Text>Protected Home</Text></AuthGate>);
    expect(screen.getByText('Redirect:/(auth)/login')).toBeOnTheScreen();
    expect(screen.queryByText('Protected Home')).not.toBeOnTheScreen();
  });

  it('redirects authenticated users away from Login', async () => {
    mockSegments = ['(auth)', 'login'];
    status = 'authenticated';

    await render(<AuthGate><Text>Login</Text></AuthGate>);

    expect(screen.getByText('Redirect:/(tabs)/home')).toBeOnTheScreen();
    expect(screen.queryByText('Login')).not.toBeOnTheScreen();
  });

  it('renders protected routes only for an authenticated session', async () => {
    status = 'authenticated';
    await render(<AuthGate><Text>Protected Home</Text></AuthGate>);
    expect(screen.getByText('Protected Home')).toBeOnTheScreen();
  });
});
