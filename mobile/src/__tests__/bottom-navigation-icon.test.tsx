import { render, screen } from '@testing-library/react-native';

import { BottomNavigationIcon, bottomNavigationIcons } from '@/components/bottom-navigation-icon';

describe('bottom navigation icons', () => {
  it('maps the native icons to the web navigation meanings', async () => {
    expect(bottomNavigationIcons).toEqual({
      home: 'chart-line',
      log: 'plus',
      history: 'history',
    });

    await render(
      <BottomNavigationIcon
        color="#3366cc"
        name={bottomNavigationIcons.home}
        size={24}
        testID="home-tab-icon"
      />,
    );

    expect(screen.getByTestId('home-tab-icon')).toBeOnTheScreen();
  });
});
