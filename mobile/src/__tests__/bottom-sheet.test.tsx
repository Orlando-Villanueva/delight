import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { Animated, PanResponder, type PanResponderGestureState, type GestureResponderEvent, Text } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { BottomSheet, sheetPaddingBottom } from '@/components/bottom-sheet';
import { themeTokens } from '@/theme/tokens';

async function renderSheet(visible = true, onClose = jest.fn(), draggable = false) {
  await render(
    <SafeAreaProvider
      initialMetrics={{
        frame: { x: 0, y: 0, width: 390, height: 844 },
        insets: { top: 47, left: 0, right: 0, bottom: 34 },
      }}
    >
      <BottomSheet
        visible={visible}
        draggable={draggable}
        title="Choose a book"
        onClose={onClose}
        dismissAccessibilityLabel="Dismiss book list"
        dismissAccessibilityHint="Closes the Bible book list"
        closeAccessibilityLabel="Close book list"
        closeAccessibilityHint="Closes the Bible book list"
      >
        <Text>Sheet body</Text>
      </BottomSheet>
    </SafeAreaProvider>,
  );

  return { onClose };
}

describe('bottom sheet padding', () => {
  it('keeps default chrome padding, honors an override, and only grows for an explicit safe-area inset', () => {
    expect(sheetPaddingBottom({ padBottomSafeArea: false, insetBottom: 48 })).toBe(
      themeTokens.spacing.screen,
    );
    expect(sheetPaddingBottom({ padBottomSafeArea: true, insetBottom: 48 })).toBe(48);
    expect(sheetPaddingBottom({ padBottomSafeArea: true, insetBottom: 8 })).toBe(
      themeTokens.spacing.screen,
    );
    expect(sheetPaddingBottom({
      padBottomSafeArea: false,
      paddingBottom: 0,
      insetBottom: 48,
    })).toBe(0);
  });
});

describe('bottom sheet', () => {
  it('renders the shared chrome and body when visible', async () => {
    await renderSheet();

    expect(screen.getByText('Choose a book')).toBeOnTheScreen();
    expect(screen.getByText('Sheet body')).toBeOnTheScreen();
    expect(screen.getByLabelText('Close book list')).toBeOnTheScreen();
    expect(screen.getByLabelText('Dismiss book list')).toBeOnTheScreen();
  });

  it('hides the sheet when it is not visible', async () => {
    await renderSheet(false);

    expect(screen.queryByText('Choose a book')).not.toBeOnTheScreen();
    expect(screen.queryByText('Sheet body')).not.toBeOnTheScreen();
  });

  it.each(['Dismiss book list', 'Close book list'])('animates before closing from %s', async (label) => {
    const { onClose } = await renderSheet();
    await fireEvent.press(screen.getByLabelText(label));
    expect(onClose).not.toHaveBeenCalled();
    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
  });

  it('ignores repeated dismissal while the exit animation is running', async () => {
    const { onClose } = await renderSheet();
    await fireEvent.press(screen.getByLabelText('Dismiss book list'));
    await fireEvent.press(screen.getByLabelText('Close book list'));
    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
  });

  it('does not close after an interrupted exit animation', async () => {
    let finish: ((result: { finished: boolean }) => void) | undefined;
    const animation = jest.spyOn(Animated, 'parallel').mockImplementation(() => ({
      start: (callback) => { finish = callback; },
      stop: jest.fn(), reset: jest.fn(),
    }));
    try {
      const { onClose } = await renderSheet();
      await fireEvent.press(screen.getByLabelText('Close book list'));
      await act(() => finish?.({ finished: false }));
      expect(onClose).not.toHaveBeenCalled();
    } finally {
      animation.mockRestore();
    }
  });

  it('claims the handle touch and dismisses through the native responder handlers', async () => {
    const { onClose } = await renderSheet(true, jest.fn(), true);
    const handle = screen.getByTestId('bottom-sheet-drag-handle');
    const touchEvent = (y: number, previousY: number, timestamp: number) => ({
      nativeEvent: { touches: [{ identifier: 0, pageX: 100, pageY: y }] },
      touchHistory: {
        numberActiveTouches: 1,
        indexOfSingleActiveTouch: 0,
        mostRecentTimeStamp: timestamp,
        touchBank: [{
          touchActive: true,
          startPageX: 100, startPageY: 100, startTimeStamp: 0,
          currentPageX: 100, currentPageY: y, currentTimeStamp: timestamp,
          previousPageX: 100, previousPageY: previousY, previousTimeStamp: 0,
        }],
      },
    });
    const start = touchEvent(100, 100, 0);
    expect(await fireEvent(handle, 'startShouldSetResponder', start)).toBe(true);
    await fireEvent(handle, 'responderGrant', start);
    await fireEvent(handle, 'responderMove', touchEvent(200, 100, 200));
    await fireEvent(handle, 'responderRelease', touchEvent(200, 200, 220));
    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
  });

  it.each([
    { dy: -120, vy: -1, closes: false },
    { dy: 30, vy: 0.1, closes: false },
    { dy: 100, vy: 0.1, closes: true },
    { dy: 20, vy: 1, closes: true },
  ])('handles a drag of $dy points at velocity $vy', async ({ dy, vy, closes }) => {
    const responder = jest.spyOn(PanResponder, 'create');
    try {
      const { onClose } = await renderSheet(true, jest.fn(), true);
      expect(screen.getByTestId('bottom-sheet-drag-handle')).toBeOnTheScreen();
      const handlers = responder.mock.calls.at(-1)![0];
      const event = {} as GestureResponderEvent;
      const gesture = { dy, dx: 0, vy } as PanResponderGestureState;
      await act(() => {
        handlers.onPanResponderGrant?.(event, gesture);
        handlers.onPanResponderMove?.(event, gesture);
        handlers.onPanResponderRelease?.(event, gesture);
      });
      if (closes) {
        await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
      } else {
        expect(onClose).not.toHaveBeenCalled();
      }
    } finally {
      responder.mockRestore();
    }
  });
});
