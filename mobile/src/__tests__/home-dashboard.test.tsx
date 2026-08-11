import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { AppState } from 'react-native';

import HomeScreen from '@/app/(tabs)/home';
import { useAuthenticatedApi } from '@/auth/auth-context';

const mockRequest = jest.fn();
let mockAppStateListener: ((state: 'active' | 'background') => void) | undefined;
let mockQueryClient: QueryClient | undefined;

jest.mock('@/auth/auth-context', () => ({ useAuthenticatedApi: jest.fn() }));
jest.mock('react-native/Libraries/Components/RefreshControl/RefreshControl', () => {
  const { View } = jest.requireActual('react-native');

  return {
    __esModule: true,
    default: (props: object) => <View {...props} />,
  };
});
jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, listener) => {
  mockAppStateListener = listener as (state: 'active' | 'background') => void;

  return { remove: jest.fn() };
});

function bootstrap(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      user: { id: 1, name: 'Reader', email: 'reader@example.com' },
      today: '2026-08-10',
      yesterday: '2026-08-09',
      books: [],
      recent_book_ids: [],
      has_read_today: false,
      current_streak: 0,
      longest_streak: 0,
      this_week_days: 0,
      this_month_days: 0,
      activity: Array.from({ length: 14 }, (_, index) => ({ date: `2026-07-${String(index + 28).padStart(2, '0')}`, count: 0 })),
      ...overrides,
    },
  };
}

async function renderHome() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } },
  });
  mockQueryClient = queryClient;

  return render(
    <QueryClientProvider client={queryClient}>
      <HomeScreen />
    </QueryClientProvider>,
  );
}

describe('native Home dashboard', () => {
  beforeEach(() => {
    mockRequest.mockReset();
    mockAppStateListener = undefined;
    jest.mocked(useAuthenticatedApi).mockReturnValue(mockRequest);
  });

  afterEach(async () => {
    await mockQueryClient?.cancelQueries();
    mockQueryClient?.clear();
  });

  it('shows an accessible loading state before bootstrap data arrives', async () => {
    let resolveRequest: ((value: ReturnType<typeof bootstrap>) => void) | undefined;
    mockRequest.mockReturnValue(new Promise((resolve) => {
      resolveRequest = resolve;
    }));

    await renderHome();

    expect(screen.getByText('Loading your reading dashboard…')).toBeOnTheScreen();
    await act(async () => {
      resolveRequest?.(bootstrap());
    });
    await screen.findByText('No recent reading activity');
  });

  it('renders an encouraging empty dashboard from the bootstrap response', async () => {
    mockRequest.mockResolvedValue(bootstrap());

    await renderHome();

    expect(await screen.findByText('No recent reading activity')).toBeOnTheScreen();
    expect(screen.getAllByText('0 days')).toHaveLength(2);
    expect(mockRequest).toHaveBeenCalledWith('/api/v1/bootstrap');
  });

  it('renders all supplied dashboard values without recalculating them', async () => {
    mockRequest.mockResolvedValue(bootstrap({
      has_read_today: true,
      current_streak: 4,
      longest_streak: 11,
      this_week_days: 3,
      this_month_days: 8,
      activity: Array.from({ length: 14 }, (_, index) => ({ date: `2026-08-${String(index + 1).padStart(2, '0')}`, count: index === 13 ? 2 : 0 })),
    }));

    await renderHome();

    expect(await screen.findByText('Reading logged today')).toBeOnTheScreen();
    expect(screen.getByText('4 days')).toBeOnTheScreen();
    expect(screen.getByText('11 days')).toBeOnTheScreen();
    expect(screen.getByText('3')).toBeOnTheScreen();
    expect(screen.getByText('8')).toBeOnTheScreen();
    expect(screen.getByLabelText('2026-08-14: 2 readings')).toBeOnTheScreen();
  });

  it('offers a recoverable error state', async () => {
    mockRequest.mockRejectedValue(new Error('Network error'));

    await renderHome();

    expect(await screen.findByText('Your dashboard could not be loaded')).toBeOnTheScreen();
    mockRequest.mockResolvedValue(bootstrap());
    fireEvent.press(screen.getByRole('button', { name: 'Retry loading dashboard' }));

    await waitFor(() => expect(screen.getByText('No recent reading activity')).toBeOnTheScreen());
  });

  it('keeps the cached dashboard visible when a refresh fails', async () => {
    mockRequest
      .mockResolvedValueOnce(bootstrap({ current_streak: 4 }))
      .mockRejectedValueOnce(new Error('Network error'));

    await renderHome();

    expect(await screen.findByText('4 days')).toBeOnTheScreen();
    fireEvent(screen.getByTestId('home-refresh-control'), 'refresh');

    expect(await screen.findByText('We couldn’t refresh your dashboard')).toBeOnTheScreen();
    expect(screen.getByText('4 days')).toBeOnTheScreen();
    expect(screen.queryByText('Your dashboard could not be loaded')).not.toBeOnTheScreen();
    await waitFor(() => expect(screen.getByTestId('home-refresh-control')).toHaveProp('refreshing', false));
  });

  it('refreshes on pull-to-refresh and app foregrounding', async () => {
    let resolvePullToRefresh: ((value: ReturnType<typeof bootstrap>) => void) | undefined;
    let resolveForegroundRefresh: ((value: ReturnType<typeof bootstrap>) => void) | undefined;
    mockRequest
      .mockResolvedValueOnce(bootstrap())
      .mockImplementationOnce(
        () => new Promise((resolve) => {
          resolvePullToRefresh = resolve;
        }),
      )
      .mockImplementationOnce(
        () => new Promise((resolve) => {
          resolveForegroundRefresh = resolve;
        }),
      );
    await renderHome();

    await screen.findByText('No recent reading activity');
    fireEvent(screen.getByTestId('home-refresh-control'), 'refresh');
    await waitFor(() => {
      expect(mockRequest).toHaveBeenCalledTimes(2);
      expect(screen.getByTestId('home-refresh-control')).toHaveProp('refreshing', true);
    });
    await act(async () => {
      resolvePullToRefresh?.(bootstrap());
    });
    await waitFor(() => expect(screen.getByTestId('home-refresh-control')).toHaveProp('refreshing', false));

    await act(async () => {
      mockAppStateListener?.('active');
    });
    await waitFor(() => {
      expect(mockRequest).toHaveBeenCalledTimes(3);
      expect(screen.getByTestId('home-refresh-control')).toHaveProp('refreshing', true);
    });
    await act(async () => {
      resolveForegroundRefresh?.(bootstrap());
    });
    await waitFor(() => expect(screen.getByTestId('home-refresh-control')).toHaveProp('refreshing', false));
  });

});
