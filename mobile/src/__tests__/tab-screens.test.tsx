import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import LogScreen from '@/app/(tabs)/log';
import { useAuthenticatedApi } from '@/auth/auth-context';

jest.mock('@/auth/auth-context', () => ({ useAuthenticatedApi: jest.fn() }));

const request = jest.fn();
let queryClient: QueryClient;

function bootstrap() {
  return {
    data: {
      user: { id: 1, name: 'Reader', email: 'reader@example.com' },
      today: '2026-08-10',
      yesterday: '2026-08-09',
      books: [{ id: 43, name: 'John', chapters: 21, testament: 'new' }],
      recent_book_ids: [43],
      has_read_today: false,
      current_streak: 0,
      longest_streak: 0,
      this_week_days: 0,
      this_month_days: 0,
      activity: [],
    },
  };
}

describe('mobile tab screens', () => {
  beforeEach(() => {
    request.mockReset();
    request.mockResolvedValue(bootstrap());
    jest.mocked(useAuthenticatedApi).mockReturnValue(request);
  });

  afterEach(async () => {
    await queryClient?.cancelQueries();
    queryClient?.clear();
    cleanup();
  });

  it('renders the native reading-log form on the Log tab', async () => {
    queryClient = new QueryClient({
      defaultOptions: {
        queries: { gcTime: 0, retry: false },
        mutations: { gcTime: 0, retry: false },
      },
    });

    function Wrapper({ children }: PropsWithChildren) {
      return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
    }

    await render(<LogScreen />, { wrapper: Wrapper });

    expect(await screen.findByText('When did you read?')).toBeOnTheScreen();
    expect(screen.getByLabelText('Log reading')).toBeOnTheScreen();
  });
});
