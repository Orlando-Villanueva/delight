import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';
import { AccessibilityInfo, AppState, Keyboard, ScrollView } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { ApiError } from '@/api/api-error';
import LogScreen from '@/app/(tabs)/log';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { formatReadingDate, readingLogSuccessDismissMs } from '@/features/reading-log/form';

jest.mock('@/auth/auth-context', () => ({ useAuthenticatedApi: jest.fn() }));

const request = jest.fn();
let queryClient: QueryClient;
let createReading: jest.Mock;
let appStateListener: ((state: 'active' | 'background') => void) | undefined;
let keyboardDidShowListener: ((event: unknown) => void) | undefined;

jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, listener) => {
  appStateListener = listener as (state: 'active' | 'background') => void;

  return { remove: jest.fn() };
});

jest.spyOn(Keyboard, 'addListener').mockImplementation((event, listener) => {
  if (event === 'keyboardDidShow') {
    keyboardDidShowListener = listener as unknown as (event: unknown) => void;
  }

  return { remove: jest.fn() } as never;
});

function bootstrap(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      user: { id: 1, name: 'Reader', email: 'reader@example.com' },
      today: '2026-08-10',
      yesterday: '2026-08-09',
      books: [
        { id: 1, name: 'Genesis', chapters: 50, testament: 'old' },
        { id: 31, name: 'Obadiah', chapters: 1, testament: 'old' },
        { id: 43, name: 'John', chapters: 21, testament: 'new' },
      ],
      recent_book_ids: [43],
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

function createdReading(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      log_ids: [101],
      book: { id: 43, name: 'John' },
      start_chapter: 3,
      end_chapter: null,
      passage: 'John 3',
      notes_text: 'Born of the Spirit.',
      date_read: '2026-08-10',
      logged_at: '2026-08-10T14:30:00.000Z',
      ...overrides,
    },
  };
}

async function renderLog() {
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

  return render(<LogScreen />, { wrapper: Wrapper });
}

async function fillValidReading() {
  await fireEvent.changeText(screen.getByLabelText('Start chapter'), '3');
  await fireEvent.press(screen.getByLabelText('Add a note or reflection'));
  await fireEvent.changeText(screen.getByLabelText('Note or reflection'), 'Born of the Spirit.');
}

function mockApi(
  create: jest.Mock = jest.fn().mockResolvedValue(createdReading()),
  bootstrapResponse: ReturnType<typeof bootstrap> = bootstrap(),
) {
  createReading = create;
  request.mockImplementation(async (path: string, options?: { method?: string }) => {
    if (path === '/api/v1/bootstrap') {
      return bootstrapResponse;
    }

    if (path === '/api/v1/reading-logs' && options?.method === 'POST') {
      return createReading();
    }

    throw new Error(`Unexpected request ${options?.method ?? 'GET'} ${path}`);
  });
}

function readingLogPosts() {
  return request.mock.calls.filter(([path, options]) => {
    return path === '/api/v1/reading-logs' && options?.method === 'POST';
  });
}

beforeEach(() => {
  request.mockReset();
  createReading = jest.fn().mockResolvedValue(createdReading());
  appStateListener = undefined;
  keyboardDidShowListener = undefined;
  jest.mocked(useAuthenticatedApi).mockReturnValue(request);
});

afterEach(async () => {
  await queryClient?.cancelQueries();
  queryClient?.clear();
  cleanup();
  jest.useRealTimers();
});

describe('native reading-log form', () => {
  it('prepares the focused note field to scroll above the Android keyboard', async () => {
    const scrollToEnd = jest.spyOn(ScrollView.prototype, 'scrollToEnd');
    const requestAnimationFrame = jest.spyOn(globalThis, 'requestAnimationFrame').mockImplementation((callback) => {
      callback(0);
      return 0;
    });

    try {
      mockApi();
      await renderLog();
      await screen.findByText('John has 21 chapters.');
      await fireEvent.press(screen.getByLabelText('Add a note or reflection'));

      fireEvent(screen.getByLabelText('Note or reflection'), 'focus');

      expect(keyboardDidShowListener).toEqual(expect.any(Function));
      expect(scrollToEnd).toHaveBeenCalledWith({ animated: true });

      keyboardDidShowListener?.({ endCoordinates: { height: 300, screenX: 0, screenY: 500, width: 360 } });

      expect(scrollToEnd).toHaveBeenCalledTimes(2);
    } finally {
      requestAnimationFrame.mockRestore();
      scrollToEnd.mockRestore();
    }
  });

  it('refreshes the untouched server date when the app returns to the foreground', async () => {
    request
      .mockResolvedValueOnce(bootstrap())
      .mockResolvedValueOnce(bootstrap({ today: '2026-08-11', yesterday: '2026-08-10' }));

    await renderLog();
    await screen.findByText('John has 21 chapters.');

    await act(async () => {
      appStateListener?.('active');
    });

    await waitFor(() => {
      expect(request).toHaveBeenCalledTimes(2);
      expect(screen.getByLabelText('Today')).toHaveProp('accessibilityState', { selected: true });
      expect(screen.getByLabelText('Yesterday')).toHaveProp('accessibilityHint', 'Uses the server date 2026-08-10');
    });
  });

  it('preserves a deliberate date choice when the app returns to the foreground', async () => {
    request
      .mockResolvedValueOnce(bootstrap())
      .mockResolvedValueOnce(bootstrap({ today: '2026-08-11', yesterday: '2026-08-10' }));

    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fireEvent.press(screen.getByLabelText('Yesterday'));

    await act(async () => {
      appStateListener?.('active');
    });

    await waitFor(() => {
      expect(request).toHaveBeenCalledTimes(2);
      expect(screen.getByLabelText('Yesterday')).toHaveProp(
        'accessibilityHint',
        'Uses the server date 2026-08-10',
      );
    });

    expect(screen.getByLabelText('Today')).toHaveProp('accessibilityState', { selected: false });
    expect(screen.getByLabelText('Yesterday')).toHaveProp('accessibilityState', { selected: false });
  });

  it('prefers a valid recent book and describes that book’s chapter count', async () => {
    mockApi();

    await renderLog();

    expect(await screen.findByText('John has 21 chapters.')).toBeOnTheScreen();
    expect(screen.getByLabelText('Recent book John')).toHaveProp('accessibilityState', { selected: true });
    expect(screen.getByLabelText('Start chapter')).toHaveDisplayValue('');

    await fireEvent.press(screen.getByLabelText('Log reading'));

    expect(await screen.findByText('Enter a start chapter.')).toBeOnTheScreen();
    expect(readingLogPosts()).toHaveLength(0);
  });

  it('keeps chapter inputs aligned when the optional label wraps at enlarged text sizes', async () => {
    mockApi();
    await renderLog();
    await screen.findByText('John has 21 chapters.');

    const startLabel = screen.getByText('Start chapter');
    const endLabel = screen.getByText('End chapter (optional)');

    expect(startLabel).toHaveStyle({ flex: 1, lineHeight: 21 });
    expect(endLabel).toHaveStyle({ flex: 1, lineHeight: 21 });
    expect(startLabel.parent).toBe(endLabel.parent);
  });

  it('clamps chapter controls when a shorter book is selected', async () => {
    mockApi();
    await renderLog();
    await screen.findByText('John has 21 chapters.');

    await fireEvent.changeText(screen.getByLabelText('Start chapter'), '21');
    await fireEvent.changeText(screen.getByLabelText('End chapter'), '21');
    await fireEvent.press(screen.getByLabelText('Bible book'));
    await fireEvent.press(screen.getByLabelText('Show Old Testament books'));
    await fireEvent.press(screen.getByLabelText('Obadiah'));

    expect(await screen.findByText('Obadiah has 1 chapter.')).toBeOnTheScreen();
    expect(screen.getByLabelText('Start chapter')).toHaveDisplayValue('1');
    expect(screen.getByLabelText('End chapter')).toHaveDisplayValue('');
  });

  it('opens the picker on the selected book’s testament and lets readers switch testament lists', async () => {
    mockApi();
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fireEvent.press(screen.getByLabelText('Bible book'));

    expect(screen.getByLabelText('Show New Testament books')).toHaveProp('accessibilityState', {
      selected: true,
    });
    expect(screen.getByLabelText('John')).toBeOnTheScreen();
    expect(screen.queryByLabelText('Genesis')).not.toBeOnTheScreen();

    await fireEvent.press(screen.getByLabelText('Show Old Testament books'));

    expect(screen.getByLabelText('Show Old Testament books')).toHaveProp('accessibilityState', {
      selected: true,
    });
    expect(screen.getByLabelText('Genesis')).toBeOnTheScreen();
    expect(screen.queryByLabelText('John')).not.toBeOnTheScreen();
  });

  it('opens the picker on Old Testament when no book is selected', async () => {
    mockApi(undefined, bootstrap({ recent_book_ids: [] }));
    await renderLog();
    await screen.findByText('Select a book to see available chapters.');
    await fireEvent.press(screen.getByLabelText('Bible book'));

    expect(screen.getByLabelText('Show Old Testament books')).toHaveProp('accessibilityState', {
      selected: true,
    });
    expect(screen.getByLabelText('Genesis')).toBeOnTheScreen();
  });

  it('closes the book picker when the dimmed backdrop is pressed', async () => {
    mockApi();
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fireEvent.press(screen.getByLabelText('Bible book'));

    expect(await screen.findByText('Choose a book')).toBeOnTheScreen();
    await fireEvent.press(screen.getByLabelText('Dismiss book list'));

    expect(screen.queryByText('Choose a book')).not.toBeOnTheScreen();
  });

  it('submits only a server-provided date and the entered reading', async () => {
    mockApi();
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fireEvent.press(screen.getByLabelText('Yesterday'));
    await fillValidReading();
    await fireEvent.press(screen.getByLabelText('Log reading'));

    await waitFor(() => {
      expect(request).toHaveBeenCalledWith('/api/v1/reading-logs', {
        method: 'POST',
        body: {
          book_id: 43,
          start_chapter: 3,
          end_chapter: null,
          date_read: '2026-08-09',
          notes_text: 'Born of the Spirit.',
        },
      });
    });
  });

  it('maps field-level 422 errors onto the relevant controls', async () => {
    mockApi(jest.fn().mockRejectedValue(new ApiError('The given data was invalid.', 'http', 422, {
      start_chapter: ['The start chapter is invalid for the selected book.'],
    })));
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fillValidReading();
    await fireEvent.press(screen.getByLabelText('Log reading'));

    expect(await screen.findByText('The start chapter is invalid for the selected book.')).toBeOnTheScreen();
    expect(screen.getByDisplayValue('Born of the Spirit.')).toBeOnTheScreen();
  });

  it('preserves the form after a network failure and allows a manual retry', async () => {
    mockApi(jest.fn()
      .mockRejectedValueOnce(
        new ApiError('Delight could not connect. Check your connection and try again.', 'network'),
      )
      .mockResolvedValueOnce(createdReading()));
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fillValidReading();
    await fireEvent.press(screen.getByLabelText('Log reading'));

    expect(await screen.findByText('Delight could not connect. Check your connection and try again.')).toBeOnTheScreen();
    expect(screen.getByDisplayValue('Born of the Spirit.')).toBeOnTheScreen();

    await fireEvent.press(screen.getByLabelText('Log reading'));

    await waitFor(() => expect(readingLogPosts()).toHaveLength(2));
    expect(await screen.findByText(`John 3 recorded for ${formatReadingDate('2026-08-10')}.`)).toBeOnTheScreen();
  });

  it('starts a retry cooldown after a rate-limit response', async () => {
    jest.useFakeTimers();
    mockApi(jest.fn().mockRejectedValue(new ApiError('Too many attempts.', 'http', 429, {}, 30)));
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fillValidReading();
    await fireEvent.press(screen.getByLabelText('Log reading'));

    await waitFor(() => expect(screen.getByText('Try again in 30s')).toBeOnTheScreen());
    expect(screen.getByLabelText('Try again in 30s')).toBeDisabled();
    expect(screen.getByDisplayValue('Born of the Spirit.')).toBeOnTheScreen();

    await act(async () => {
      jest.advanceTimersByTime(1_000);
    });

    expect(screen.getByText('Try again in 29s')).toBeOnTheScreen();
  });

  it('does not create a parallel submission from repeated taps', async () => {
    let resolveCreate: ((value: ReturnType<typeof createdReading>) => void) | undefined;
    mockApi(jest.fn().mockImplementation(() => new Promise((resolve) => {
      resolveCreate = resolve;
    })));
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fillValidReading();

    fireEvent.press(screen.getByLabelText('Log reading'));
    await waitFor(() => expect(createReading).toHaveBeenCalledTimes(1));
    fireEvent.press(screen.getByLabelText('Log reading'));
    fireEvent.press(screen.getByLabelText('Log reading'));

    expect(createReading).toHaveBeenCalledTimes(1);
    expect(readingLogPosts()).toHaveLength(1);
    await act(async () => {
      resolveCreate?.(createdReading());
    });
    await screen.findByText(`John 3 recorded for ${formatReadingDate('2026-08-10')}.`);
  });

  it('shows the canonical reading after success, clears the form, and refreshes Home and History', async () => {
    mockApi();
    const invalidate = jest.spyOn(QueryClient.prototype, 'invalidateQueries');
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fillValidReading();
    await fireEvent.press(screen.getByLabelText('Log reading'));

    expect(await screen.findByText(`John 3 recorded for ${formatReadingDate('2026-08-10')}.`)).toBeOnTheScreen();
    expect(screen.queryByDisplayValue('Born of the Spirit.')).not.toBeOnTheScreen();
    expect(screen.getByLabelText('Start chapter')).toHaveDisplayValue('');
    expect(screen.getByLabelText('End chapter')).toHaveDisplayValue('');
    expect(screen.getByLabelText('Add a note or reflection')).toBeOnTheScreen();
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith(
      `John 3 recorded for ${formatReadingDate('2026-08-10')}.`,
    );
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['bootstrap'] });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['reading-history'] });
    invalidate.mockRestore();

    await fireEvent.press(screen.getByLabelText('Dismiss success message'));
    expect(screen.queryByText('Reading logged')).not.toBeOnTheScreen();
  });

  it('selects the book that was just logged when refreshed recent books arrive', async () => {
    const create = jest.fn().mockResolvedValue(createdReading({
      book: { id: 1, name: 'Genesis' },
      passage: 'Genesis 3',
    }));
    let bootstrapRequestCount = 0;
    request.mockImplementation(async (path: string, options?: { method?: string }) => {
      if (path === '/api/v1/bootstrap') {
        bootstrapRequestCount += 1;

        return bootstrap({ recent_book_ids: bootstrapRequestCount === 1 ? [43] : [1, 43] });
      }

      if (path === '/api/v1/reading-logs' && options?.method === 'POST') {
        return create();
      }

      throw new Error(`Unexpected request ${options?.method ?? 'GET'} ${path}`);
    });
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fireEvent.press(screen.getByLabelText('Bible book'));
    await fireEvent.press(screen.getByLabelText('Show Old Testament books'));
    await fireEvent.press(screen.getByLabelText('Genesis'));
    await fireEvent.changeText(screen.getByLabelText('Start chapter'), '3');
    await fireEvent.press(screen.getByLabelText('Log reading'));

    expect(await screen.findByText(`Genesis 3 recorded for ${formatReadingDate('2026-08-10')}.`)).toBeOnTheScreen();
    await waitFor(() => expect(screen.getByLabelText('Recent book Genesis')).toBeOnTheScreen());
    expect(screen.getByLabelText('Recent book Genesis')).toHaveProp('accessibilityState', { selected: true });
    expect(screen.getByLabelText('Recent book John')).toHaveProp('accessibilityState', { selected: false });
  });

  it('auto-dismisses the success banner after a short delay', async () => {
    jest.useFakeTimers();
    mockApi();
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fillValidReading();
    await fireEvent.press(screen.getByLabelText('Log reading'));

    expect(await screen.findByText(`John 3 recorded for ${formatReadingDate('2026-08-10')}.`)).toBeOnTheScreen();

    await act(async () => {
      await jest.advanceTimersByTimeAsync(readingLogSuccessDismissMs);
    });

    expect(screen.queryByText('Reading logged')).not.toBeOnTheScreen();
    jest.useRealTimers();
  });

  it('offers a recoverable bootstrap error before the form is available', async () => {
    request
      .mockRejectedValueOnce(new Error('Network error'))
      .mockImplementation(async (path: string) => {
        if (path === '/api/v1/bootstrap') {
          return bootstrap();
        }

        throw new Error(`Unexpected request ${path}`);
      });
    await renderLog();

    expect(await screen.findByText('The reading form could not be loaded')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Try again' }));

    expect(await screen.findByText('When did you read?')).toBeOnTheScreen();
  });

  it('does not automatically retry a failed POST', async () => {
    mockApi(jest.fn().mockRejectedValue(new ApiError('Service unavailable.', 'http', 503)));
    await renderLog();
    await screen.findByText('John has 21 chapters.');
    await fillValidReading();
    await fireEvent.press(screen.getByLabelText('Log reading'));

    expect(await screen.findByText('Service unavailable.')).toBeOnTheScreen();
    expect(readingLogPosts()).toHaveLength(1);
    expect(createReading).toHaveBeenCalledTimes(1);
    expect(screen.getByDisplayValue('Born of the Spirit.')).toBeOnTheScreen();
  });
});
