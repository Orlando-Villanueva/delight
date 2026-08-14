const { describe, expect, it } = require('@jest/globals');
const path = require('node:path');

const createAppConfig = require('../../app.config');
const easConfig = require(path.resolve(process.cwd(), 'eas.json'));

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
  it.each(['development', 'preview', 'dogfood'])('uses the Delight slug for the %s variant', (appVariant) => {
    expect(configFor(appVariant).slug).toBe('delight');
  });

  it.each(['development', 'preview', 'dogfood'])('uses Delight branding for the %s native icon surfaces', (appVariant) => {
    const config = configFor(appVariant);
    const splashPlugin = config.plugins.find(([plugin]) => plugin === 'expo-splash-screen');

    expect(config.icon).toBe('./assets/images/delight-logo.png');
    expect(config.android.adaptiveIcon).toEqual({
      backgroundColor: '#0d67f9',
      foregroundImage: './assets/images/android-icon-foreground-delight.png',
    });
    expect(splashPlugin).toEqual([
      'expo-splash-screen',
      {
        backgroundColor: '#0f172a',
        image: './assets/images/delight-logo.png',
        imageWidth: 96,
      },
    ]);
  });

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

  it.each(['development', 'preview', 'dogfood'])('links the %s variant to the existing EAS project', (appVariant) => {
    expect(configFor(appVariant).extra.eas.projectId).toBe('aa50d7fa-9028-4991-abb9-8f58d306cadf');
  });

  it.each(['development', 'preview', 'dogfood'])('delegates the %s Android version code to EAS', (appVariant) => {
    expect(configFor(appVariant).android).not.toHaveProperty('versionCode');
  });

  it.each(['preview', 'dogfood'])('uses EAS remote version increments for the %s APK', (profile) => {
    expect(easConfig.cli.appVersionSource).toBe('remote');
    expect(easConfig.build[profile]).toMatchObject({
      autoIncrement: true,
      distribution: 'internal',
      android: { buildType: 'apk' },
    });
  });
});
