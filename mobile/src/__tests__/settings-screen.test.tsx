import * as Linking from 'expo-linking';
import { fireEvent, render, screen } from '@testing-library/react-native';
import { AccessibilityInfo } from 'react-native';

import { SettingsScreen } from '@/components/settings-screen';

jest.mock('expo-linking', () => ({ openURL: jest.fn() }));
jest.mock('@/config/web-environment', () => ({
  getWebBaseUrl: () => 'https://delight-staging.laravel.cloud',
}));

describe('settings screen', () => {
  beforeEach(() => {
    jest.mocked(Linking.openURL).mockReset();
    jest.mocked(Linking.openURL).mockResolvedValue(true);
  });

  it('opens the privacy, support, and deletion resources', async () => {
    await render(<SettingsScreen />);

    expect(screen.getByText('HELP & LEGAL')).toBeOnTheScreen();
    expect(screen.getByText('ACCOUNT')).toBeOnTheScreen();
    expect(screen.getByLabelText('Privacy policy')).toHaveProp('accessibilityRole', 'link');
    expect(screen.getByLabelText('Contact support')).toHaveProp('accessibilityRole', 'link');
    expect(screen.getByLabelText('Request account deletion')).toHaveProp(
      'accessibilityRole',
      'link',
    );

    await fireEvent.press(screen.getByLabelText('Privacy policy'));
    await fireEvent.press(screen.getByLabelText('Contact support'));
    await fireEvent.press(screen.getByLabelText('Request account deletion'));

    expect(Linking.openURL).toHaveBeenNthCalledWith(
      1,
      'https://delight-staging.laravel.cloud/privacy-policy',
    );
    expect(Linking.openURL).toHaveBeenNthCalledWith(2, 'mailto:orlando@mg.mydelight.app');
    expect(Linking.openURL).toHaveBeenNthCalledWith(
      3,
      'https://delight-staging.laravel.cloud/account-deletion',
    );
  });

  it('shows and announces a recoverable error when a resource cannot open', async () => {
    jest.mocked(Linking.openURL).mockRejectedValueOnce(new Error('No browser available'));
    await render(<SettingsScreen />);

    await fireEvent.press(screen.getByLabelText('Privacy policy'));

    const message = 'That resource could not be opened. Try again.';
    expect(await screen.findByText(message)).toBeOnTheScreen();
    expect(AccessibilityInfo.announceForAccessibility).toHaveBeenCalledWith(message);
  });
});
