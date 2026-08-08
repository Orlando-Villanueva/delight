import { render, screen } from '@testing-library/react-native';
import { Text } from 'react-native';

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
jest.mock('@/auth/auth-context', () => ({ useAuth: jest.fn() }));

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

  it('redirects unauthenticated protected routes to Login', async () => {
    status = 'unauthenticated';
    await render(<AuthGate><Text>Protected Home</Text></AuthGate>);
    expect(screen.getByText('Redirect:/(auth)/login')).toBeOnTheScreen();
    expect(screen.queryByText('Protected Home')).not.toBeOnTheScreen();
  });

  it('renders protected routes only for an authenticated session', async () => {
    status = 'authenticated';
    await render(<AuthGate><Text>Protected Home</Text></AuthGate>);
    expect(screen.getByText('Protected Home')).toBeOnTheScreen();
  });
});
