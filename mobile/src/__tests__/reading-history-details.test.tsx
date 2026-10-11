import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { Alert, Keyboard } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import type { ReadingHistoryGroup } from '@/api/reading-history';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { ReadingHistoryDetails } from '@/components/reading-history-details';

jest.mock('@/auth/auth-context', () => ({ useAuthenticatedApi: jest.fn() }));
const request = jest.fn();
const group: ReadingHistoryGroup = {
  logIds: [101], book: { id: 43, name: 'John' }, startChapter: 1, endChapter: 1,
  passage: 'John 1', notesText: 'Original', dateRead: '2026-08-08', loggedAt: null,
  records: [{ id: 101, chapter: 1, passage: 'John 1', notesText: 'Original',
    dateRead: '2026-08-08', loggedAt: null }],
};

async function details(edit = true, onRecordRemoved = jest.fn().mockResolvedValue(undefined)) {
  const onClose = jest.fn();
  await render(
    <SafeAreaProvider initialMetrics={{ frame: { x: 0, y: 0, width: 390, height: 844 },
      insets: { top: 0, left: 0, right: 0, bottom: 0 } }}>
      <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { gcTime: 0 } } })}>
        <ReadingHistoryDetails group={group} dateLabel="August 8" onClose={onClose}
          onNoteSaved={jest.fn().mockResolvedValue(undefined)} onRecordRemoved={onRecordRemoved} />
      </QueryClientProvider>
    </SafeAreaProvider>,
  );
  if (edit) await fireEvent.press(screen.getByRole('button', { name: 'Edit note' }));
  return { onClose, onRecordRemoved };
}

beforeEach(() => {
  jest.mocked(useAuthenticatedApi).mockReturnValue(request);
  jest.spyOn(Keyboard, 'isVisible').mockReturnValue(false);
  jest.spyOn(Keyboard, 'dismiss').mockImplementation(() => undefined);
  jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
  request.mockReset().mockResolvedValue(undefined);
});
afterEach(() => jest.restoreAllMocks());

it('Back leaves unchanged editing before closing details', async () => {
  const { onClose } = await details();
  await fireEvent(screen.getByTestId('bottom-sheet-modal'), 'requestClose');
  expect(screen.getByRole('button', { name: 'Edit note' })).toBeOnTheScreen();
  expect(onClose).not.toHaveBeenCalled();
  expect(Alert.alert).not.toHaveBeenCalled();
});

it('Back hides the keyboard while preserving the edited draft', async () => {
  const { onClose } = await details();
  await fireEvent.changeText(screen.getByLabelText('Note'), 'Draft');
  jest.mocked(Keyboard.isVisible).mockReturnValue(true);
  await fireEvent(screen.getByTestId('bottom-sheet-modal'), 'requestClose');
  expect(Keyboard.dismiss).toHaveBeenCalled();
  expect(screen.getByDisplayValue('Draft')).toBeOnTheScreen();
  expect(Alert.alert).not.toHaveBeenCalled();
  expect(onClose).not.toHaveBeenCalled();
});

it('keeps changed drafts on rejected dismissal and discards to details on Back confirmation', async () => {
  const { onClose } = await details();
  await fireEvent.changeText(screen.getByLabelText('Note'), 'Draft');
  jest.mocked(Alert.alert).mockImplementationOnce((_title, _message, buttons) => buttons?.[0].onPress?.());
  await fireEvent.press(screen.getByLabelText('Close reading details'));
  expect(screen.getByDisplayValue('Draft')).toBeOnTheScreen();
  expect(onClose).not.toHaveBeenCalled();
  jest.mocked(Alert.alert).mockImplementationOnce((_title, _message, buttons) => buttons?.[1].onPress?.());
  await fireEvent(screen.getByTestId('bottom-sheet-modal'), 'requestClose');
  await waitFor(() => expect(screen.getByRole('button', { name: 'Edit note' })).toBeOnTheScreen());
  expect(onClose).not.toHaveBeenCalled();
  expect(request).not.toHaveBeenCalled();
});

it('Cancel discards explicitly without a confirmation', async () => {
  await details();
  await fireEvent.changeText(screen.getByLabelText('Note'), 'Draft');
  await fireEvent.press(screen.getByRole('button', { name: 'Cancel note editing' }));
  expect(screen.getByRole('button', { name: 'Edit note' })).toBeOnTheScreen();
  expect(Alert.alert).not.toHaveBeenCalled();
  expect(request).not.toHaveBeenCalled();
});

it.each(['Close reading details', 'Dismiss reading details'])('confirms before closing a changed draft via %s',
  async (label) => {
    const { onClose } = await details();
    await fireEvent.changeText(screen.getByLabelText('Note'), 'Draft');
    jest.mocked(Alert.alert).mockImplementationOnce((_title, _message, buttons) => buttons?.[1].onPress?.());
    await fireEvent.press(screen.getByLabelText(label));
    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(Alert.alert).toHaveBeenCalledWith('Discard changes?', expect.any(String), expect.any(Array),
      expect.any(Object));
    expect(request).not.toHaveBeenCalled();
  });

it('cancels removal without sending a request', async () => {
  await details(false);
  jest.mocked(Alert.alert).mockImplementationOnce((_title, _message, buttons) => buttons?.[0].onPress?.());
  await fireEvent.press(screen.getByRole('button', { name: 'Remove John 1 from History' }));
  expect(Alert.alert).toHaveBeenCalledWith('Remove John 1?', expect.stringContaining('August 8'),
    expect.any(Array), expect.any(Object));
  expect(request).not.toHaveBeenCalled();
});

it('removes the authoritative record after confirmation and blocks dismissal while pending', async () => {
  let finish: (() => void) | undefined;
  request.mockReturnValue(new Promise<void>((resolve) => { finish = resolve; }));
  const { onClose, onRecordRemoved } = await details(false);
  jest.mocked(Alert.alert).mockImplementationOnce((_title, _message, buttons) => buttons?.[1].onPress?.());
  await fireEvent.press(screen.getByRole('button', { name: 'Remove John 1 from History' }));
  await waitFor(() => expect(request).toHaveBeenCalledWith('/api/v1/reading-logs/101', { method: 'DELETE' }));
  expect(screen.getByRole('button', { name: 'Remove John 1 from History' })).toBeDisabled();
  await fireEvent.press(screen.getByRole('button', { name: 'Close reading details' }));
  expect(onClose).not.toHaveBeenCalled();
  finish?.();
  await waitFor(() => expect(onRecordRemoved).toHaveBeenCalledWith(101));
});

it('preserves the reading and reports a failed deletion without retrying automatically', async () => {
  request.mockRejectedValue(new Error('Connection lost'));
  await details(false);
  jest.mocked(Alert.alert).mockImplementationOnce((_title, _message, buttons) => buttons?.[1].onPress?.());
  await fireEvent.press(screen.getByRole('button', { name: 'Remove John 1 from History' }));
  expect(await screen.findByText('Connection lost')).toBeOnTheScreen();
  expect(screen.getByText('Original')).toBeOnTheScreen();
  expect(request).toHaveBeenCalledTimes(1);
});

it('retries only refresh after removal succeeded but refreshing failed', async () => {
  const onRemoved = jest.fn().mockRejectedValueOnce(new Error('Refresh failed')).mockResolvedValue(undefined);
  await details(false, onRemoved);
  jest.mocked(Alert.alert).mockImplementationOnce((_title, _message, buttons) => buttons?.[1].onPress?.());
  await fireEvent.press(screen.getByRole('button', { name: 'Remove John 1 from History' }));
  expect(await screen.findByText(/The chapter was removed, but History could not refresh/)).toBeOnTheScreen();
  expect(screen.queryByRole('button', { name: 'Edit note' })).not.toBeOnTheScreen();
  await fireEvent.press(screen.getByRole('button', { name: 'Refresh History' }));
  await waitFor(() => expect(onRemoved).toHaveBeenCalledTimes(2));
  expect(request).toHaveBeenCalledTimes(1);
});
