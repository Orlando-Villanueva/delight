import { fireEvent, render, screen } from '@testing-library/react-native';
import { Text } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { BottomSheet, sheetPaddingBottom } from '@/components/bottom-sheet';
import { themeTokens } from '@/theme/tokens';

async function renderSheet(visible = true, onClose = jest.fn()) {
  await render(
    <SafeAreaProvider
      initialMetrics={{
        frame: { x: 0, y: 0, width: 390, height: 844 },
        insets: { top: 47, left: 0, right: 0, bottom: 34 },
      }}
    >
      <BottomSheet
        visible={visible}
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

  it('closes from the overlay and the close control', async () => {
    const { onClose } = await renderSheet();

    await fireEvent.press(screen.getByLabelText('Dismiss book list'));
    await fireEvent.press(screen.getByLabelText('Close book list'));

    expect(onClose).toHaveBeenCalledTimes(2);
  });
});
