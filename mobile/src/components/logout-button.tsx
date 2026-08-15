import { AccessibilityInfo, Alert, Pressable, Text } from 'react-native';

import { ApiError } from '@/api/api-error';
import { useAuth } from '@/auth/auth-context';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

export function LogoutButton() {
  const { logout, isLoggingOut } = useAuth();
  const { colors } = useTheme();

  async function handleLogout() {
    AccessibilityInfo.announceForAccessibility('Signing out.');

    try {
      await logout();
    } catch (error) {
      const message = error instanceof ApiError
        ? error.message
        : 'Logout could not be completed. Try again.';
      Alert.alert('Still signed in', message);
      AccessibilityInfo.announceForAccessibility(message);
    }
  }

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Sign out"
      accessibilityHint="Revokes this device session and returns to Login"
      accessibilityState={{ disabled: isLoggingOut }}
      disabled={isLoggingOut}
      onPress={handleLogout}
      style={{
        minHeight: themeTokens.minimumTouchTarget,
        justifyContent: 'center',
      }}
    >
      <Text style={{ color: colors.danger, fontSize: 16, fontWeight: '600' }}>
        {isLoggingOut ? 'Signing out…' : 'Sign out'}
      </Text>
    </Pressable>
  );
}
