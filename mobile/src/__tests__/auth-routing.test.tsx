import { render, screen } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';
import { Text } from 'react-native';

import RootLayout from '@/app/_layout';
import { AuthGate } from '@/auth/auth-gate';
import { useAuth } from '@/auth/auth-context';

let mockSegments: string[] = ['(tabs)', 'home'];
let status: 'loading' | 'authenticated' | 'unauthenticated' = 'loading';
const MockText = Text;

jest.mock('expo-router', () => ({
  Redirect: ({ href }: { href: string }) => {
    return <MockText>Redirect:{href}</MockText>;
  },
  useSegments: () => mockSegments,
}));
jest.mock('expo-router/stack', () => {
  const React = jest.requireActual<typeof import('react')>('react');
  const { Text: NativeText } = jest.requireActual<typeof import('react-native')>('react-native');
  const Stack = ({ children }: PropsWithChildren) => (
    <>
      <NativeText>Root navigator</NativeText>
      {children}
    </>
  );
  Stack.Screen = function MockStackScreen() {
    return null;
  };

  return { Stack };
});
jest.mock('expo-status-bar', () => ({ StatusBar: () => null }));
jest.mock('@/auth/auth-context', () => ({
  AuthProvider: function MockAuthProvider({ children }: PropsWithChildren) {
    return children;
  },
  useAuth: jest.fn(),
}));

describe('authenticated route protection', () => {
  beforeEach(() => {
    mockSegments = ['(tabs)', 'home'];
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
