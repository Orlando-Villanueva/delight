import { useColorScheme } from 'react-native';

import { themeTokens } from '@/theme/tokens';

export function useTheme() {
  const colorScheme = useColorScheme();
  const mode = colorScheme === 'dark' ? 'dark' : 'light';

  return {
    colors: themeTokens[mode],
    mode,
  };
}
