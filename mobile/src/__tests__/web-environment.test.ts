import { getWebBaseUrl } from '@/config/web-environment';

jest.mock('@/config/environment', () => ({
  environment: { apiUrl: 'https://delight-staging.laravel.cloud', appVariant: 'development' },
}));

describe('matching web environment', () => {
  it.each([
    ['development', 'https://delight-staging.laravel.cloud', 'https://delight-staging.laravel.cloud'],
    ['preview', 'https://delight-staging.laravel.cloud', 'https://delight-staging.laravel.cloud'],
    ['dogfood', 'https://delight-staging.laravel.cloud', 'https://mydelight.app'],
  ])('maps %s links to the correct web host', (variant, apiUrl, expected) => {
    expect(getWebBaseUrl(variant, apiUrl)).toBe(expected);
  });
});
