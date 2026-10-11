import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiError } from '@/api/api-error';
import type { ReadingHistoryGroup } from '@/api/reading-history';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { ReadingNoteEditor } from '@/components/reading-note-editor';

jest.mock('@/auth/auth-context', () => ({ useAuthenticatedApi: jest.fn() }));
const request = jest.fn();
const group: ReadingHistoryGroup = {
  logIds: [101, 102], book: { id: 43, name: 'John' }, startChapter: 1, endChapter: 2,
  passage: 'John 1-2', notesText: 'Original', dateRead: '2026-08-08', loggedAt: null, records: [],
};

async function editor(different = false, onSaved = jest.fn().mockResolvedValue(undefined)) {
  const onCancel = jest.fn();
  const onSavingChange = jest.fn();
  await render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false, gcTime: Infinity } } })}>
      <ReadingNoteEditor
        group={group}
        initialNote="Original"
        replacesDifferentNotes={different}
        onSaved={onSaved}
        onCancel={onCancel}
        onSavingChange={onSavingChange}
      />
    </QueryClientProvider>,
  );
  return { onSaved, onCancel, onSavingChange };
}

beforeEach(() => {
  request.mockReset().mockResolvedValue(undefined);
  jest.mocked(useAuthenticatedApi).mockReturnValue(request);
});
afterEach(cleanup);

it('expands long notes beyond the old cap and shrinks without resetting the draft', async () => {
  await editor();
  const draft = 'A paragraph about this reading.\n'.repeat(20);
  await fireEvent.changeText(screen.getByLabelText('Note'), draft);
  await fireEvent(screen.getByLabelText('Note'), 'contentSizeChange', {
    nativeEvent: { contentSize: { width: 300, height: 600 } },
  });
  expect(screen.getByDisplayValue(draft)).toHaveStyle({ height: 624 });
  await fireEvent.changeText(screen.getByLabelText('Note'), 'Short note');
  await fireEvent(screen.getByLabelText('Note'), 'contentSizeChange', {
    nativeEvent: { contentSize: { width: 300, height: 40 } },
  });
  expect(screen.getByDisplayValue('Short note')).toHaveStyle({ height: 120 });
  expect(request).not.toHaveBeenCalled();
});

it('saves all selected record IDs and refreshes after success', async () => {
  const { onSaved } = await editor();
  await fireEvent.changeText(screen.getByLabelText('Note'), ' Updated ');
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  await waitFor(() => expect(onSaved).toHaveBeenCalledTimes(1));
  expect(request).toHaveBeenCalledWith('/api/v1/reading-logs/101/note', {
    method: 'PATCH', body: { log_ids: [101, 102], notes_text: 'Updated' },
  });
});

it('clears notes and prevents duplicate submissions while pending', async () => {
  let finish: (() => void) | undefined;
  request.mockReturnValue(new Promise<void>((resolve) => { finish = resolve; }));
  const { onSavingChange } = await editor();
  await fireEvent.changeText(screen.getByLabelText('Note'), '');
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  await waitFor(() => expect(request).toHaveBeenCalledTimes(1));
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  expect(request).toHaveBeenCalledTimes(1);
  expect(request.mock.calls[0][1].body.notes_text).toBeNull();
  expect(screen.getByRole('button', { name: 'Cancel note editing' })).toBeDisabled();
  expect(onSavingChange).toHaveBeenCalledWith(true);
  finish?.();
  await waitFor(() => expect(onSavingChange).toHaveBeenLastCalledWith(false));
});

it('preserves draft text on validation and connection failures without retrying', async () => {
  request.mockRejectedValueOnce(new ApiError('Invalid', 'http', 422, { notes_text: ['Invalid note'] }));
  await editor();
  await fireEvent.changeText(screen.getByLabelText('Note'), 'Keep this draft');
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  expect(await screen.findByText('Invalid note')).toBeOnTheScreen();
  expect(screen.getByDisplayValue('Keep this draft')).toBeOnTheScreen();
  expect(request).toHaveBeenCalledTimes(1);
  request.mockRejectedValueOnce(new ApiError('Connection lost', 'network'));
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  expect(await screen.findByText('Connection lost')).toBeOnTheScreen();
  expect(screen.getByDisplayValue('Keep this draft')).toBeOnTheScreen();
  expect(request).toHaveBeenCalledTimes(2);
});

it('requires confirmation before replacing differing notes', async () => {
  await editor(true);
  expect(screen.getByRole('button', { name: 'Save note' })).toBeDisabled();
  await fireEvent.press(screen.getByRole('checkbox', { name: 'Replace the different chapter notes' }));
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  await waitFor(() => expect(request).toHaveBeenCalledTimes(1));
});

it('retries only refreshing when the write succeeded but refresh failed', async () => {
  const onSaved = jest.fn().mockRejectedValueOnce(new Error('Refresh failed')).mockResolvedValue(undefined);
  await editor(false, onSaved);
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  expect(await screen.findByText(/Your note was saved, but History could not refresh/)).toBeOnTheScreen();
  await fireEvent.press(screen.getByRole('button', { name: 'Refresh History' }));
  await waitFor(() => expect(onSaved).toHaveBeenCalledTimes(2));
  expect(request).toHaveBeenCalledTimes(1);
});

it('validates note length locally and allows cancel without writing', async () => {
  const { onCancel } = await editor();
  await fireEvent.changeText(screen.getByLabelText('Note'), 'a'.repeat(1001));
  await fireEvent.press(screen.getByRole('button', { name: 'Save note' }));
  expect(await screen.findByText('The notes may not be greater than 1,000 characters.')).toBeOnTheScreen();
  expect(request).not.toHaveBeenCalled();
  await fireEvent.press(screen.getByRole('button', { name: 'Cancel note editing' }));
  expect(onCancel).toHaveBeenCalledTimes(1);
});
