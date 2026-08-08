const { describe, expect, it } = require('@jest/globals');

const createAppConfig = require('../../app.config');

function configFor(appVariant) {
  const previousAppVariant = process.env.APP_VARIANT;

  process.env.APP_VARIANT = appVariant;

  try {
    return createAppConfig({ config: {} });
  } finally {
    if (previousAppVariant === undefined) {
      delete process.env.APP_VARIANT;
    } else {
      process.env.APP_VARIANT = previousAppVariant;
    }
  }
}

function configForWithApiOverride(appVariant, apiUrl) {
  const previousApiUrl = process.env.EXPO_PUBLIC_API_URL;
  process.env.EXPO_PUBLIC_API_URL = apiUrl;

  try {
    return configFor(appVariant);
  } finally {
    if (previousApiUrl === undefined) {
      delete process.env.EXPO_PUBLIC_API_URL;
    } else {
      process.env.EXPO_PUBLIC_API_URL = previousApiUrl;
    }
  }
}

describe('app configuration identities', () => {
  it('omits standalone native identifiers for Expo Go development', () => {
    const config = configFor('development');

    expect(config.ios).toBeUndefined();
    expect(config.android).not.toHaveProperty('package');
  });

  it.each([
    ['preview', 'com.orlandovillanueva.delight.preview'],
    ['dogfood', 'com.orlandovillanueva.delight'],
  ])('reserves the %s native identity', (appVariant, packageIdentifier) => {
    const config = configFor(appVariant);

    expect(config.ios.bundleIdentifier).toBe(packageIdentifier);
    expect(config.android.package).toBe(packageIdentifier);
  });

  it.each([
    ['development', 'https://delight-staging.laravel.cloud'],
    ['preview', 'https://delight-staging.laravel.cloud'],
    ['dogfood', 'https://mydelight.app'],
  ])('uses the matching %s web and API environment', (appVariant, apiUrl) => {
    expect(configFor(appVariant).extra.apiUrl).toBe(apiUrl);
  });

  it('allows a development override without changing preview or dogfood targets', () => {
    expect(configForWithApiOverride('development', 'https://local.example').extra.apiUrl).toBe('https://local.example');
    expect(configForWithApiOverride('preview', 'https://local.example').extra.apiUrl).toBe('https://delight-staging.laravel.cloud');
    expect(configForWithApiOverride('dogfood', 'https://local.example').extra.apiUrl).toBe('https://mydelight.app');
  });
});
