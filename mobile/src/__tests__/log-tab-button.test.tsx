import { fireEvent, render, screen } from '@testing-library/react-native';

import { LogTabButton } from '@/components/log-tab-button';

describe('central Log tab action', () => {
  it('uses an accessible action label and opens the Log route when pressed', async () => {
    const onPress = jest.fn();

    await render(<LogTabButton accessibilityState={{ selected: true }} onPress={onPress} />);

    const button = screen.getByRole('button', { name: 'Log a reading' });
    fireEvent.press(button);

    expect(onPress).toHaveBeenCalledTimes(1);
    expect(button).toHaveStyle({ top: -20, minHeight: 64 });
    expect(screen.getByText('+')).toBeOnTheScreen();
    expect(screen.queryByText('Log')).not.toBeOnTheScreen();
  });
});
