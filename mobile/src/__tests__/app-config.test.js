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
});
