import { fireEvent, render, screen } from '@testing-library/react-native';
import { Text } from 'react-native';

import { StandardTabButton } from '@/components/standard-tab-button';

describe('standard tab action', () => {
  it('preserves the navigation-provided content and responds to presses', async () => {
    const onPress = jest.fn();

    await render(
      <StandardTabButton accessibilityLabel="Home tab" onPress={onPress}>
        <Text>Home</Text>
      </StandardTabButton>,
    );

    fireEvent.press(screen.getByRole('button', { name: 'Home tab' }));

    expect(onPress).toHaveBeenCalledTimes(1);
    expect(screen.getByText('Home')).toBeOnTheScreen();
  });
});
