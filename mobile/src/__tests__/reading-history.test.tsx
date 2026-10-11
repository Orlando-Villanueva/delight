import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, cleanup, fireEvent, render, userEvent, waitFor, within } from '@testing-library/react-native';
import { router } from 'expo-router';
import type { PropsWithChildren } from 'react';
import { Alert, AppState } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import HistoryScreen from '@/app/(tabs)/history';
import { ApiError } from '@/api/api-error';
import { mergeReadingHistoryPages, type ReadingHistoryPage } from '@/api/reading-history';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { canFitChapterCount } from '@/components/reading-history';
import { themeTokens } from '@/theme/tokens';

jest.mock('@/auth/auth-context', () => ({ useAuthenticatedApi: jest.fn() }));

jest.mock('expo-router', () => ({ router: { navigate: jest.fn(), push: jest.fn() } }));

jest.mock('react-native/Libraries/Components/RefreshControl/RefreshControl', () => {
  const { View } = jest.requireActual('react-native');

  return {
    __esModule: true,
    default: (props: object) => <View {...props} />,
  };
});

const request = jest.fn();
let mockAppStateListener: ((state: 'active' | 'background') => void) | undefined;

jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, listener) => {
  mockAppStateListener = listener as (state: 'active' | 'background') => void;

  return { remove: jest.fn() };
});

async function renderHistory(readToday: boolean | 'unavailable' = true) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false, staleTime: Infinity }, mutations: { retry: false, gcTime: 0 } },
  });

  if (readToday !== 'unavailable') {
    queryClient.setQueryData(['bootstrap'], bootstrapResponse(readToday).data);
  }

  function Wrapper({ children }: PropsWithChildren) {
    return (
      <SafeAreaProvider
        initialMetrics={{
          frame: { x: 0, y: 0, width: 390, height: 844 },
          insets: { top: 0, left: 0, right: 0, bottom: 24 },
        }}
      >
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      </SafeAreaProvider>
    );
  }

  return Object.assign(await render(<HistoryScreen />, { wrapper: Wrapper }), { queryClient });
}

function bootstrapResponse(hasReadToday: boolean) {
  return {
    data: {
      user: { id: 1, name: 'Reader', email: 'reader@example.com' },
      today: '2026-08-10',
      yesterday: '2026-08-09',
      books: [],
      recent_book_ids: [],
      has_read_today: hasReadToday,
      current_streak: 0,
      longest_streak: 0,
      this_week_days: 0,
      this_month_days: 0,
      activity: [],
    },
  };
}

function historyResponse(page: number, lastPage: number, date: string, groups = [readingGroup()]): unknown {
  return {
    data: [{ date_read: date, groups }],
    meta: { current_page: page, last_page: lastPage },
  };
}

function readingGroup(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    log_ids: [101, 102],
    book: { id: 43, name: 'John' },
    start_chapter: 1,
    end_chapter: 2,
    passage: 'John 1-2',
    notes_text: 'A hopeful beginning.',
    date_read: '2026-08-10',
    logged_at: '2026-08-10T14:30:00.000Z',
    ...overrides,
  };
}

function detailedGroup(overrides: Partial<Record<string, unknown>> = {}) {
  return readingGroup({
    records: [
      {
        id: 101,
        chapter: 1,
        passage: 'John 1',
        notes_text: 'A hopeful beginning.',
        date_read: '2026-08-10',
        logged_at: '2026-08-10T14:30:00.000Z',
      },
      {
        id: 102,
        chapter: 2,
        passage: 'John 2',
        notes_text: 'A hopeful beginning.',
        date_read: '2026-08-10',
        logged_at: '2026-08-10T14:30:00.000Z',
      },
    ],
    ...overrides,
  });
}

beforeEach(() => {
  request.mockReset();
  request.mockImplementation((path: string) => {
    if (path === '/api/v1/bootstrap') {
      return Promise.resolve(bootstrapResponse(true));
    }

    return Promise.reject(new Error(`Unexpected request: ${path}`));
  });
  mockAppStateListener = undefined;
  jest.mocked(router.navigate).mockClear();
  jest.mocked(router.push).mockClear();
  jest.mocked(useAuthenticatedApi).mockReturnValue(request);
});

afterEach(cleanup);

describe('reading history', () => {
  it('opens the intended groups details by tapping its note and shows a shared note once', async () => {
    request.mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [
      detailedGroup(),
      detailedGroup({
        log_ids: [104],
        passage: 'John 4',
        start_chapter: 4,
        end_chapter: null,
        records: [{
          id: 104, chapter: 4, passage: 'John 4', notes_text: 'Another reading.',
          date_read: '2026-08-10', logged_at: null,
        }],
      }),
    ]));

    const screen = await renderHistory();
    const button = await screen.findByRole('button', { name: /Reading details for John 1-2/ });
    expect(screen.queryByText('Reading details')).not.toBeOnTheScreen();
    expect(button).toBeOnTheScreen();
    await fireEvent.press(within(button).getByText('A hopeful beginning.'));

    expect(screen.getByText('John 1')).toBeOnTheScreen();
    expect(screen.getByText('John 2')).toBeOnTheScreen();
    expect(screen.getByText('Note')).toBeOnTheScreen();
    expect(screen.getAllByText('A hopeful beginning.')).toHaveLength(1);
    expect(screen.queryByText('Another reading.')).not.toBeOnTheScreen();
    expect(screen.getByText('Monday, August 10, 2026')).toBeOnTheScreen();
  });

  it.each([1, 2])('edits a groups note and preserves details across %i loaded pages', async (lastPage) => {
    let note = 'A hopeful beginning.';
    request.mockImplementation((path: string) => {
      if (path === '/api/v1/bootstrap') {
        return Promise.resolve(bootstrapResponse(true));
      }
      if (path === '/api/v1/reading-logs/101/note') {
        note = 'Updated group note';
        return Promise.resolve(undefined);
      }
      if (path.startsWith('/api/v1/reading-logs?page=')) {
        const page = Number(path.split('=')[1]);
        if (lastPage === 2 && page === 1) {
          return Promise.resolve(historyResponse(1, 2, '2026-08-10', [readingGroup({
            log_ids: [104], passage: 'John 4', start_chapter: 4, end_chapter: null,
          })]));
        }
        return Promise.resolve(historyResponse(page, lastPage, '2026-08-10', [detailedGroup({
          notes_text: note,
          records: [1, 2].map((chapter) => ({
            id: 100 + chapter, chapter, passage: `John ${chapter}`, notes_text: note,
            date_read: '2026-08-10', logged_at: null,
          })),
        })]));
      }
      return Promise.reject(new Error('Unexpected request'));
    });
    const screen = await renderHistory();
    if (lastPage === 2) {
      await fireEvent.press(await screen.findByRole('button', { name: 'Load more' }));
    }
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));
    await fireEvent.press(screen.getByRole('button', { name: 'Edit note' }));
    await fireEvent.changeText(screen.getByLabelText('Note'), 'Updated group note');
    await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
    expect(await screen.findByText('Updated group note')).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Close reading details' })).toBeOnTheScreen();
    expect(request).toHaveBeenCalledWith('/api/v1/bootstrap');
  });

  it.each(['edge', 'middle', 'last'])('refreshes and reconciles details after removing an %s chapter',
    async (scenario) => {
      const record = (chapter: number) => ({
        id: 100 + chapter, chapter, passage: `John ${chapter}`, notes_text: 'Note',
        date_read: '2026-08-10', logged_at: null,
      });
      const initial = detailedGroup({
        log_ids: scenario === 'last' ? [101] : [101, 102, 103],
        passage: scenario === 'last' ? 'John 1' : 'John 1-3',
        end_chapter: scenario === 'last' ? null : 3,
        records: (scenario === 'last' ? [1] : [1, 2, 3]).map(record),
      });
      const remaining = scenario === 'last' ? [] : scenario === 'middle' ? [
        detailedGroup({ log_ids: [101], passage: 'John 1', end_chapter: null, records: [record(1)] }),
        detailedGroup({ log_ids: [103], passage: 'John 3', start_chapter: 3, end_chapter: null, records: [record(3)] }),
      ] : [detailedGroup({ log_ids: [102, 103], passage: 'John 2-3', start_chapter: 2, end_chapter: 3,
        records: [record(2), record(3)] })];
      let deleted = false;
      const id = scenario === 'middle' ? 102 : 101;
      request.mockImplementation((path: string) => {
        if (path === '/api/v1/bootstrap') return Promise.resolve(bootstrapResponse(true));
        if (path === `/api/v1/reading-logs/${id}`) {
          deleted = true;
          return Promise.resolve(undefined);
        }
        if (path.endsWith('page=1')) return Promise.resolve(historyResponse(1, 2, '2026-08-11', [
          readingGroup({ log_ids: [109], passage: 'John 9', start_chapter: 9, end_chapter: null }),
        ]));
        return Promise.resolve(historyResponse(2, 2, '2026-08-10', deleted ? remaining : [initial]));
      });
      const confirm = jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => buttons?.[1].onPress?.());
      try {
        const screen = await renderHistory();
        await fireEvent.press(await screen.findByRole('button', { name: 'Load more' }));
        await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1/ }));
        await fireEvent.press(screen.getByRole('button', {
          name: `Remove John ${scenario === 'middle' ? 2 : 1} from History`,
        }));
        if (scenario === 'edge') {
          expect(await screen.findByText('John 2-3')).toBeOnTheScreen();
          expect(screen.getByRole('button', { name: 'Close reading details' })).toBeOnTheScreen();
          await waitFor(() => expect(screen.getByRole('button', { name: 'Remove John 2 from History' })).toBeEnabled());
        } else {
          expect(await screen.findByText(scenario === 'middle'
            ? 'Chapter removed. The remaining chapters are now shown as separate readings in History.'
            : 'Chapter removed from History.')).toBeOnTheScreen();
          expect(screen.queryByRole('button', { name: 'Close reading details' })).not.toBeOnTheScreen();
        }
        expect(request).toHaveBeenCalledWith(`/api/v1/reading-logs/${id}`, { method: 'DELETE' });
        expect(request.mock.calls.filter(([path]) => path.endsWith('page=2'))).toHaveLength(2);
        expect(request.mock.calls.filter(([path]) => path === '/api/v1/bootstrap')).toHaveLength(1);
      } finally {
        confirm.mockRestore();
      }
    });

  it('shows each chapters distinct note and explicitly identifies a missing note', async () => {
    request.mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [detailedGroup({
      records: [
        { id: 101, chapter: 1, passage: 'John 1', notes_text: 'Chapter one note.',
          date_read: '2026-08-10', logged_at: null },
        { id: 102, chapter: 2, passage: 'John 2', notes_text: null,
          date_read: '2026-08-10', logged_at: null },
      ],
    })]));

    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));

    expect(screen.getByText('These chapters have different notes.')).toBeOnTheScreen();
    expect(screen.getByText('Chapter one note.')).toBeOnTheScreen();
    expect(screen.getByText('No note')).toBeOnTheScreen();
    expect(screen.queryByText('Shared note')).not.toBeOnTheScreen();
  });

  it('explains unavailable chapter details instead of deriving them from a passage range', async () => {
    request.mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10'));

    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));

    expect(screen.getByText(/Individual chapter details are unavailable/)).toBeOnTheScreen();
    expect(screen.queryByText('John 1')).not.toBeOnTheScreen();
    expect(screen.queryByText('John 2')).not.toBeOnTheScreen();
  });

  it('shows a single chapter with no note without a misleading shared-note label', async () => {
    request.mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [detailedGroup({
      log_ids: [101], passage: 'John 1', end_chapter: null, notes_text: null, logged_at: null,
      records: [{ id: 101, chapter: 1, passage: 'John 1', notes_text: null,
        date_read: '2026-08-10', logged_at: null }],
    })]));

    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1,/ }));

    expect(screen.queryByText('Chapter read')).not.toBeOnTheScreen();
    expect(screen.getAllByText('John 1')).toHaveLength(1);
    expect(screen.getByText('No note')).toBeOnTheScreen();
    expect(screen.queryByText('Shared note')).not.toBeOnTheScreen();
    expect(screen.queryByText(/Logged at/)).not.toBeOnTheScreen();
  });

  it.each(['Close reading details', 'Dismiss reading details', 'Android Back'])(
    'returns to History using %s', async (action) => {
      request.mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [detailedGroup()]));

      const screen = await renderHistory();
      await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));

      if (action === 'Android Back') {
        await fireEvent(screen.getByText('Chapters read'), 'requestClose');
      } else {
        await fireEvent.press(screen.getByRole('button', { name: action }));
      }

      await waitFor(() => {
        expect(screen.queryByRole('button', { name: 'Close reading details' })).not.toBeOnTheScreen();
      });
      expect(screen.getByRole('button', { name: /Reading details for John 1-2/ })).toBeOnTheScreen();
    },
  );

  it.each([
    { groups: [] },
    { groups: [detailedGroup({ log_ids: [101], end_chapter: null, passage: 'John 1' })] },
  ])('closes details and announces a removed or changed group after refresh', async ({ groups }) => {
    request
      .mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [detailedGroup()]))
      .mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', groups));

    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));
    await act(async () => {
      await screen.getByTestId('history-refresh-control', { includeHiddenElements: true }).props.onRefresh();
    });

    expect(await screen.findByText(/History changed. Open the reading details again/))
      .toHaveProp('accessibilityLiveRegion', 'polite');
    expect(screen.queryByRole('button', { name: 'Close reading details' })).not.toBeOnTheScreen();
  });

  it('updates open details from current History data without retaining the previous note', async () => {
    const updated = detailedGroup({
      notes_text: 'Updated reflection.',
      records: [
        { id: 101, chapter: 1, passage: 'John 1', notes_text: 'Updated reflection.',
          date_read: '2026-08-10', logged_at: null },
        { id: 102, chapter: 2, passage: 'John 2', notes_text: 'Updated reflection.',
          date_read: '2026-08-10', logged_at: null },
      ],
    });
    request
      .mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [detailedGroup()]))
      .mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [updated]));

    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));
    await act(async () => {
      await screen.getByTestId('history-refresh-control', { includeHiddenElements: true }).props.onRefresh();
    });

    expect(screen.getByRole('button', { name: 'Close reading details' })).toBeOnTheScreen();
    expect(screen.getByText('Updated reflection.')).toBeOnTheScreen();
    expect(screen.queryByText('A hopeful beginning.')).not.toBeOnTheScreen();
  });

  it('keeps details open after a failed refresh', async () => {
    request
      .mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [detailedGroup()]))
      .mockRejectedValueOnce(new Error('Delight could not connect.'));

    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));
    await act(async () => {
      await screen.getByTestId('history-refresh-control', { includeHiddenElements: true }).props.onRefresh();
    });

    expect(screen.getByRole('button', { name: 'Close reading details' })).toBeOnTheScreen();
    expect(screen.getByText('John 2')).toBeOnTheScreen();
    expect(screen.queryByText(/History changed/)).not.toBeOnTheScreen();
  });

  it('preserves a page-two draft across foreground refresh and reloads its page after saving', async () => {
    let note = 'Original note';
    request.mockImplementation((path: string) => {
      if (path === '/api/v1/bootstrap') return Promise.resolve(bootstrapResponse(true));
      if (path === '/api/v1/reading-logs/101/note') {
        note = 'Unsaved draft';
        return Promise.resolve(undefined);
      }
      if (path.endsWith('page=1')) {
        return Promise.resolve(historyResponse(1, 2, '2026-08-10', [readingGroup({
          log_ids: [104], passage: 'John 4',
        })]));
      }
      return Promise.resolve(historyResponse(2, 2, '2026-08-09', [detailedGroup({
        notes_text: note,
        records: [1, 2].map((chapter) => ({
          id: 100 + chapter, chapter, passage: `John ${chapter}`, notes_text: note,
          date_read: '2026-08-09', logged_at: null,
        })),
      })]));
    });
    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: 'Load more' }));
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));
    await fireEvent.press(screen.getByRole('button', { name: 'Edit note' }));
    await fireEvent.changeText(screen.getByLabelText('Note'), 'Unsaved draft');
    await act(async () => { mockAppStateListener?.('active'); });
    await waitFor(() => expect(screen.getByTestId('history-refresh-control', {
      includeHiddenElements: true,
    }).props.refreshing).toBe(false));
    expect(screen.getByDisplayValue('Unsaved draft')).toBeOnTheScreen();
    expect(screen.queryByText(/History changed/)).not.toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Edit note' })).toBeOnTheScreen());
    expect(screen.getByText('Unsaved draft')).toBeOnTheScreen();
    expect(request.mock.calls.filter(([path]) => path.endsWith('page=2'))).toHaveLength(2);
  });

  it('retains a draft when refresh removes its group and Save reports a conflict', async () => {
    let removed = false;
    request.mockImplementation((path: string) => {
      if (path === '/api/v1/bootstrap') return Promise.resolve(bootstrapResponse(true));
      if (path === '/api/v1/reading-logs/101/note') {
        return Promise.reject(new ApiError('This reading has changed. Refresh History and open it again.', 'http', 409));
      }
      return Promise.resolve(historyResponse(1, 1, '2026-08-10', removed ? [] : [detailedGroup()]));
    });
    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 1-2/ }));
    await fireEvent.press(screen.getByRole('button', { name: 'Edit note' }));
    await fireEvent.changeText(screen.getByLabelText('Note'), 'Keep this draft');
    removed = true;
    await act(async () => {
      await screen.getByTestId('history-refresh-control', { includeHiddenElements: true }).props.onRefresh();
    });
    expect(screen.getByDisplayValue('Keep this draft')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
    expect(await screen.findByText('This reading has changed. Refresh History and open it again.')).toBeOnTheScreen();
    expect(screen.getByDisplayValue('Keep this draft')).toBeOnTheScreen();
    expect(request).toHaveBeenCalledWith('/api/v1/reading-logs/101/note', {
      method: 'PATCH', body: { log_ids: [101, 102], notes_text: 'Keep this draft' },
    });
    await fireEvent.press(screen.getByRole('button', { name: 'Cancel note editing' }));
    expect(await screen.findByText(/History changed. Open the reading details again/)).toBeOnTheScreen();
  });

  it('closes an older pages details when a successful refresh replaces appended pages', async () => {
    const olderGroup = detailedGroup({
      log_ids: [104], passage: 'John 4', start_chapter: 4, end_chapter: null,
      date_read: '2026-08-09',
      records: [{ id: 104, chapter: 4, passage: 'John 4', notes_text: null,
        date_read: '2026-08-09', logged_at: null }],
    });
    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10', [detailedGroup()]))
      .mockResolvedValueOnce(historyResponse(2, 2, '2026-08-09', [olderGroup]))
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10', [detailedGroup()]));

    const screen = await renderHistory();
    await fireEvent.press(await screen.findByRole('button', { name: 'Load more' }));
    await fireEvent.press(await screen.findByRole('button', { name: /Reading details for John 4/ }));
    await act(async () => {
      await screen.getByTestId('history-refresh-control', { includeHiddenElements: true }).props.onRefresh();
    });

    expect(await screen.findByText(/History changed/)).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Close reading details' })).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: /Reading details for John 4/ })).not.toBeOnTheScreen();
  });

  it('shows an accessible Log today callout when bootstrap reports no reading today', async () => {
    request.mockResolvedValue(historyResponse(1, 1, '2026-08-09'));

    const screen = await renderHistory(false);

    expect(await screen.findByText('No reading logged today')).toBeOnTheScreen();
    const logButton = screen.getByRole('button', { name: 'Log a reading' });
    expect(logButton).toHaveProp('accessibilityHint', 'Opens the Log tab to record today’s reading.');
    expect(logButton).toHaveStyle({ minHeight: themeTokens.minimumTouchTarget });
    fireEvent.press(logButton);

    expect(router.navigate).toHaveBeenCalledWith('/(tabs)/log');
  });

  it('hides the Log today callout when bootstrap reports a reading today', async () => {
    request.mockResolvedValue(historyResponse(1, 1, '2026-08-10'));

    const screen = await renderHistory(true);

    await waitFor(() => expect(screen.getByText('John 1-2')).toBeOnTheScreen());
    expect(screen.queryByText('No reading logged today')).not.toBeOnTheScreen();
  });

  it('hides the Log today callout after bootstrap refetch reports a reading today', async () => {
    request.mockResolvedValue(historyResponse(1, 1, '2026-08-09'));

    const screen = await renderHistory(false);
    expect(await screen.findByText('No reading logged today')).toBeOnTheScreen();

    await act(async () => {
      screen.queryClient.setQueryData(['bootstrap'], bootstrapResponse(true).data);
    });

    await waitFor(() => expect(screen.queryByText('No reading logged today')).not.toBeOnTheScreen());
  });

  it('keeps History usable without a Log today claim when bootstrap is unavailable', async () => {
    request.mockImplementation((path: string) => {
      if (path === '/api/v1/bootstrap') {
        return Promise.reject(new Error('Delight could not connect.'));
      }

      return Promise.resolve(historyResponse(1, 1, '2026-08-09'));
    });

    const screen = await renderHistory('unavailable');

    expect(await screen.findByText('John 1-2')).toBeOnTheScreen();
    expect(screen.queryByText('No reading logged today')).not.toBeOnTheScreen();
  });

  it('refreshes Bootstrap with History so a new reading removes the Log today callout', async () => {
    let bootstrapRequests = 0;
    let historyRequests = 0;
    request.mockImplementation((path: string) => {
      if (path === '/api/v1/bootstrap') {
        bootstrapRequests += 1;

        return Promise.resolve(bootstrapResponse(bootstrapRequests > 1));
      }

      historyRequests += 1;

      return Promise.resolve(historyResponse(1, 1, historyRequests === 1 ? '2026-08-09' : '2026-08-10'));
    });

    const screen = await renderHistory('unavailable');
    expect(await screen.findByText('No reading logged today')).toBeOnTheScreen();

    await act(async () => {
      await screen.getByTestId('history-refresh-control').props.onRefresh();
    });

    await waitFor(() => expect(screen.getByText('Monday, August 10, 2026')).toBeOnTheScreen());
    expect(screen.queryByText('No reading logged today')).not.toBeOnTheScreen();
    expect(bootstrapRequests).toBe(2);
    expect(historyRequests).toBe(2);
  });

  it('keeps the empty-history Log action as the only CTA', async () => {
    request.mockResolvedValueOnce({ data: [], meta: { current_page: 1, last_page: 1 } });

    const screen = await renderHistory(false);

    expect(await screen.findByText('Your history is waiting')).toBeOnTheScreen();
    expect(screen.queryByText('No reading logged today')).not.toBeOnTheScreen();
    expect(screen.getAllByRole('button', { name: 'Log a reading' })).toHaveLength(1);
  });

  it('renders API-ordered day groups and only renders notes when present', async () => {
    const dateTimeFormatSpy = jest.spyOn(Intl, 'DateTimeFormat');
    request.mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [
      readingGroup(),
      readingGroup({
        log_ids: [106],
        book: { id: 22, name: 'Song of Solomon' },
        start_chapter: 5,
        end_chapter: 8,
        passage: 'Song of Solomon 5-8',
        notes_text: null,
      }),
      readingGroup({ log_ids: [103], start_chapter: 4, end_chapter: null, passage: 'John 4', notes_text: null }),
    ]));

    const screen = await renderHistory();

    await waitFor(() => expect(screen.getByText('John 1-2')).toBeOnTheScreen());

    expect(screen.getByText('2 chapters')).toBeOnTheScreen();
    expect(screen.getByText('Song of Solomon 5-8')).toBeOnTheScreen();
    expect(screen.getByText('4 chapters')).toBeOnTheScreen();
    expect(screen.queryByText('1 chapter')).not.toBeOnTheScreen();
    expect(screen.queryByText(/John ·/)).not.toBeOnTheScreen();
    expect(screen.getByText('A hopeful beginning.')).toBeOnTheScreen();
    expect(screen.getByText('John 4')).toBeOnTheScreen();
    expect(screen.getAllByText(/Logged at/)).toHaveLength(3);
    expect(screen.queryAllByText('A hopeful beginning.')).toHaveLength(1);
    expect(screen.getByText('You have reached the beginning of your history.')).toBeOnTheScreen();
    expect(dateTimeFormatSpy).toHaveBeenCalledWith('en-CA', { dateStyle: 'full' });
    expect(dateTimeFormatSpy).toHaveBeenCalledWith('en-CA', { hour: 'numeric', minute: '2-digit' });
    dateTimeFormatSpy.mockRestore();
  });

  it('appends a next page once and suppresses duplicate date groups and readings', async () => {
    let resolveNextPage: (value: unknown) => void = () => undefined;
    const nextPage = new Promise((resolve) => {
      resolveNextPage = resolve;
    });

    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10'))
      .mockReturnValueOnce(nextPage);

    const pageTwo = historyResponse(2, 2, '2026-08-10', [
        readingGroup(),
        readingGroup({ log_ids: [104], start_chapter: 4, end_chapter: null, passage: 'John 4' }),
      ]);

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Load more' })).toBeOnTheScreen());
    const loadMoreButton = screen.getByRole('button', { name: 'Load more' });
    expect(loadMoreButton).toHaveProp('accessibilityHint', 'Loads older readings from your history.');
    const user = userEvent.setup();

    await user.press(loadMoreButton);
    await user.press(loadMoreButton);

    await waitFor(() => expect(screen.getByLabelText('Loading more history')).toBeOnTheScreen());
    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeOnTheScreen();

    resolveNextPage(pageTwo);

    await waitFor(() => expect(screen.getByText(/John 4/)).toBeOnTheScreen());

    expect(request).toHaveBeenCalledTimes(2);
    expect(screen.queryAllByText('John 1-2')).toHaveLength(1);
    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeOnTheScreen();
    expect(screen.queryByLabelText('Loading more history')).not.toBeOnTheScreen();
    expect(screen.getByText('You have reached the beginning of your history.')).toBeOnTheScreen();
  });

  it('keeps the pagination control height when swapping Load more for loading', async () => {
    let resolveNextPage: (value: unknown) => void = () => undefined;
    const nextPage = new Promise((resolve) => {
      resolveNextPage = resolve;
    });

    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10'))
      .mockReturnValueOnce(nextPage);

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Load more' })).toBeOnTheScreen());

    expect(screen.getByRole('button', { name: 'Load more' })).toHaveStyle({
      minHeight: themeTokens.minimumTouchTarget,
    });

    const user = userEvent.setup();
    await user.press(screen.getByRole('button', { name: 'Load more' }));

    await waitFor(() => expect(screen.getByLabelText('Loading more history')).toBeOnTheScreen());
    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeOnTheScreen();
    expect(screen.getByLabelText('Loading more history')).toHaveStyle({
      minHeight: themeTokens.minimumTouchTarget,
    });

    resolveNextPage(historyResponse(2, 2, '2026-08-09', [
      readingGroup({ log_ids: [104], passage: 'John 4', date_read: '2026-08-09' }),
    ]));

    await waitFor(() => expect(screen.getByText('John 4')).toBeOnTheScreen());
  });

  it('restores Load more after a failed older-page request', async () => {
    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10'))
      .mockRejectedValueOnce(new Error('Delight could not connect.'));

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Load more' })).toBeOnTheScreen());
    const user = userEvent.setup();
    await user.press(screen.getByRole('button', { name: 'Load more' }));

    await waitFor(() => expect(screen.getByText('Delight could not connect.')).toBeOnTheScreen());
    expect(screen.getByRole('button', { name: 'Load more' })).toBeOnTheScreen();
    expect(screen.queryByLabelText('Loading more history')).not.toBeOnTheScreen();
  });

  it('refreshes instead of rendering a partial overlapping group', async () => {
    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10', [
        readingGroup({ log_ids: [101], end_chapter: null, passage: 'John 1' }),
      ]))
      .mockResolvedValueOnce(historyResponse(2, 2, '2026-08-10', [
        readingGroup({ log_ids: [101, 102], passage: 'John 1-2' }),
      ]))
      .mockResolvedValueOnce(historyResponse(1, 1, '2026-08-10', [
        readingGroup({ log_ids: [101, 102], passage: 'John 1-2' }),
      ]));

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByText('John 1')).toBeOnTheScreen());
    const user = userEvent.setup();
    await user.press(screen.getByRole('button', { name: 'Load more' }));

    await waitFor(() => expect(screen.getByText('John 1-2')).toBeOnTheScreen());

    expect(request).toHaveBeenCalledTimes(4);
    expect(request.mock.calls).toContainEqual(['/api/v1/reading-logs?page=1']);
    expect(screen.queryByText('John 1')).not.toBeOnTheScreen();
  });

  it('replaces appended pages from page one when refreshed', async () => {
    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10'))
      .mockResolvedValueOnce(historyResponse(2, 2, '2026-08-09', [
        readingGroup({ log_ids: [104], passage: 'John 4', date_read: '2026-08-09' }),
      ]))
      .mockResolvedValueOnce(historyResponse(1, 1, '2026-08-11', [
        readingGroup({ log_ids: [105], passage: 'John 5', date_read: '2026-08-11' }),
      ]));

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByText('John 1-2')).toBeOnTheScreen());
    const user = userEvent.setup();
    await user.press(screen.getByRole('button', { name: 'Load more' }));
    await waitFor(() => expect(screen.getByText(/John 4/)).toBeOnTheScreen());

    await act(async () => {
      await screen.getByTestId('history-refresh-control').props.onRefresh();
    });

    await waitFor(() => expect(screen.getByText(/John 5/)).toBeOnTheScreen());
    expect(screen.queryByText(/John 4/)).not.toBeOnTheScreen();
    expect(request.mock.calls).toContainEqual(['/api/v1/reading-logs?page=1']);
  });

  it('discards a pending load-more response after refresh', async () => {
    let resolveNextPage: (value: unknown) => void = () => undefined;
    let resolveRefresh: (value: unknown) => void = () => undefined;
    const nextPage = new Promise((resolve) => {
      resolveNextPage = resolve;
    });
    const refreshedPage = new Promise((resolve) => {
      resolveRefresh = resolve;
    });

    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10'))
      .mockReturnValueOnce(nextPage)
      .mockReturnValueOnce(refreshedPage);

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Load more' })).toBeOnTheScreen());
    const user = userEvent.setup();
    await user.press(screen.getByRole('button', { name: 'Load more' }));

    const refresh = screen.getByTestId('history-refresh-control').props.onRefresh as () => Promise<void>;
    let refreshResult: Promise<void> = Promise.resolve();
    await act(async () => {
      refreshResult = refresh();
    });
    await waitFor(() => expect(request).toHaveBeenCalledTimes(4));

    await act(async () => {
      resolveRefresh(historyResponse(1, 1, '2026-08-11', [
        readingGroup({ log_ids: [105], passage: 'John 5', date_read: '2026-08-11' }),
      ]));
      await refreshResult;
    });
    await waitFor(() => expect(screen.getByText('John 5')).toBeOnTheScreen());

    await act(async () => {
      resolveNextPage(historyResponse(2, 2, '2026-08-09', [
        readingGroup({ log_ids: [104], passage: 'John 4', date_read: '2026-08-09' }),
      ]));
    });

    await waitFor(() => expect(screen.queryByText('John 4')).not.toBeOnTheScreen());
  });

  it('preserves appended pages and announces a failed refresh', async () => {
    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10'))
      .mockResolvedValueOnce(historyResponse(2, 2, '2026-08-09', [
        readingGroup({ log_ids: [104], passage: 'John 4', date_read: '2026-08-09' }),
      ]))
      .mockRejectedValueOnce(new Error('Delight could not connect.'));

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByText('John 1-2')).toBeOnTheScreen());
    const user = userEvent.setup();
    await user.press(screen.getByRole('button', { name: 'Load more' }));
    await waitFor(() => expect(screen.getByText('John 4')).toBeOnTheScreen());

    await act(async () => {
      await screen.getByTestId('history-refresh-control').props.onRefresh();
    });

    await waitFor(() => expect(screen.getByText(/History could not be refreshed/)).toBeOnTheScreen());
    expect(screen.getByText('John 4')).toBeOnTheScreen();
  });

  it('refreshes when the app returns to the foreground', async () => {
    request.mockResolvedValue(historyResponse(1, 1, '2026-08-10'));
    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByText('John 1-2')).toBeOnTheScreen());

    await act(async () => {
      mockAppStateListener?.('active');
    });

    await waitFor(() => expect(request).toHaveBeenCalledTimes(3));
  });

  it('offers the Log tab when history is empty', async () => {
    request.mockResolvedValueOnce({ data: [], meta: { current_page: 1, last_page: 1 } });

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByText('Your history is waiting')).toBeOnTheScreen());

    const logButton = screen.getByRole('button', { name: 'Log a reading' });
    expect(logButton).toHaveProp('accessibilityHint', 'Opens the Log tab to record a reading.');
    fireEvent.press(logButton);

    expect(router.push).toHaveBeenCalledWith('/(tabs)/log');
  });

  it('uses the newest page metadata when the history grows during pagination', async () => {
    request
      .mockResolvedValueOnce(historyResponse(1, 2, '2026-08-10'))
      .mockResolvedValueOnce(historyResponse(2, 3, '2026-08-09', [
        readingGroup({ log_ids: [104], passage: 'John 4', date_read: '2026-08-09' }),
      ]))
      .mockResolvedValueOnce(historyResponse(3, 3, '2026-08-08', [
        readingGroup({ log_ids: [105], passage: 'John 5', date_read: '2026-08-08' }),
      ]));

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Load more' })).toBeOnTheScreen());

    const user = userEvent.setup();
    await user.press(screen.getByRole('button', { name: 'Load more' }));
    await waitFor(() => expect(screen.getByText('John 4')).toBeOnTheScreen());

    await user.press(screen.getByRole('button', { name: 'Load more' }));
    await waitFor(() => expect(screen.getByText('John 5')).toBeOnTheScreen());

    expect(request).toHaveBeenCalledTimes(3);
    expect(screen.getByText('You have reached the beginning of your history.')).toBeOnTheScreen();
  });

  it('shows a recoverable initial error', async () => {
    request.mockRejectedValueOnce(new Error('Delight could not connect.'));

    const screen = await renderHistory();
    await waitFor(() => expect(screen.getByText('History is unavailable')).toBeOnTheScreen());

    expect(screen.getByText('Delight could not connect.')).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Try again' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Try again' })).toHaveProp(
      'accessibilityHint',
      'Requests your reading history again.',
    );
  });
});

describe('canFitChapterCount', () => {
  it('keeps the count only when the passage, gap, and label fit on one line', () => {
    expect(canFitChapterCount(320, 90, 80)).toBe(true);
    expect(canFitChapterCount(320, 260, 80)).toBe(false);
    expect(canFitChapterCount(0, 90, 80)).toBe(false);
  });
});

describe('mergeReadingHistoryPages', () => {
  it('keeps the first complete server group when pages overlap', () => {
    const page = (days: ReadingHistoryPage['days']): ReadingHistoryPage => ({ days, currentPage: 1, lastPage: 2 });
    const group = (logIds: number[], passage: string) => ({
      logIds,
      book: { id: 43, name: 'John' },
      startChapter: 1,
      endChapter: null,
      passage,
      notesText: null,
      dateRead: '2026-08-10',
      loggedAt: null,
      records: null,
    });

    const result = mergeReadingHistoryPages([
      page([{ dateRead: '2026-08-10', groups: [group([1], 'John 1')] }]),
      page([{ dateRead: '2026-08-10', groups: [group([1, 2], 'John 1-2')] }]),
    ]);

    expect(result).toEqual([{ dateRead: '2026-08-10', groups: [group([1], 'John 1')] }]);
  });
});
