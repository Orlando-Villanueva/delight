import { useColorScheme } from 'react-native';

import { themeTokens, type ThemeMode } from '@/theme/tokens';

export function useTheme() {
  const colorScheme = useColorScheme();
  const mode: ThemeMode = colorScheme === 'dark' ? 'dark' : 'light';

  return {
    colors: themeTokens[mode],
    mode,
  };
}
