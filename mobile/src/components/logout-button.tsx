import { Alert, Pressable, Text } from 'react-native';

import { ApiError } from '@/api/api-error';
import { useAuth } from '@/auth/auth-context';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

export function LogoutButton() {
  const { logout, isLoggingOut } = useAuth();
  const { colors } = useTheme();

  async function handleLogout() {
    try {
      await logout();
    } catch (error) {
      const message = error instanceof ApiError
        ? error.message
        : 'Logout could not be completed. Try again.';
      Alert.alert('Still signed in', message);
    }
  }

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Log out"
      accessibilityHint="Revokes this device session and returns to Login"
      disabled={isLoggingOut}
      onPress={handleLogout}
      style={{ minWidth: themeTokens.minimumTouchTarget, minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center', paddingHorizontal: 12 }}
    >
      <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>
        {isLoggingOut ? 'Logging out…' : 'Log out'}
      </Text>
    </Pressable>
  );
}
