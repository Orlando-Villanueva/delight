import { fireEvent, render, screen } from '@testing-library/react-native';

import { bottomNavigationIcons } from '@/components/bottom-navigation-icon';
import { LogTabButton } from '@/components/log-tab-button';

describe('central Log tab action', () => {
  it('uses an accessible action label and opens the Log route when pressed', async () => {
    const onPress = jest.fn();

    await render(<LogTabButton bottom={20} isSelected onPress={onPress} />);

    const button = screen.getByRole('button', { name: 'Log a reading' });
    fireEvent.press(button);

    expect(onPress).toHaveBeenCalledTimes(1);
    expect(button.props.accessibilityState).toEqual({ selected: true });
    expect(button).toHaveStyle({ position: 'absolute', bottom: 20, width: 56, height: 56 });
    expect(bottomNavigationIcons.log).toBe('plus');
    expect(screen.getByTestId('log-tab-icon')).toBeOnTheScreen();
    expect(screen.queryByText('Log')).not.toBeOnTheScreen();
  });
});
