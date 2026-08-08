export const themeTokens = {
  light: {
    background: '#f8fafc',
    surface: '#ffffff',
    text: '#0f172a',
    mutedText: '#475569',
    primary: '#2563eb',
    primaryContrast: '#ffffff',
    danger: '#b91c1c',
    input: '#ffffff',
    border: '#cbd5e1',
  },
  dark: {
    background: '#0f172a',
    surface: '#1e293b',
    text: '#f8fafc',
    mutedText: '#cbd5e1',
    primary: '#60a5fa',
    primaryContrast: '#0f172a',
    danger: '#fca5a5',
    input: '#0f172a',
    border: '#475569',
  },
  spacing: {
    screen: 24,
    section: 16,
  },
  radius: {
    card: 12,
    control: 8,
  },
  minimumTouchTarget: 44,
} as const;

export type ThemeColors = (typeof themeTokens)['light'];
