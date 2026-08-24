import { themeTokens } from '@/theme/tokens';

describe('mobile semantic theme tokens', () => {
  it('keeps the Delight brand roles explicit in both system themes', () => {
    expect(themeTokens.light).toMatchObject({
      primary: '#3366cc',
      primaryContrast: '#ffffff',
      accent: '#f97316',
      accentContrast: '#0f172a',
      accentAction: '#f97316',
      accentActionContrast: '#3b1a0a',
      success: '#15803d',
      successContrast: '#ffffff',
    });
    expect(themeTokens.dark).toMatchObject({
      primary: '#7aa2f7',
      primaryContrast: '#0f172a',
      accent: '#fb923c',
      accentContrast: '#0f172a',
      accentSubtle: '#3f2a1d',
      accentAction: '#f97316',
      accentActionContrast: '#3b1a0a',
      success: '#86efac',
      successContrast: '#052e16',
    });
  });
});
