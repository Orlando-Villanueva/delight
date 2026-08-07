const STAGING_API_URL = 'https://delight-staging.laravel.cloud';
const PRODUCTION_API_URL = 'https://mydelight.app';

const variants = {
  development: {
    name: 'Delight',
    packageIdentifier: 'com.orlandovillanueva.delight',
    apiUrl: STAGING_API_URL,
  },
  preview: {
    name: 'Delight Preview',
    packageIdentifier: 'com.orlandovillanueva.delight.preview',
    apiUrl: STAGING_API_URL,
  },
  dogfood: {
    name: 'Delight',
    packageIdentifier: 'com.orlandovillanueva.delight',
    apiUrl: PRODUCTION_API_URL,
  },
};

module.exports = ({ config }) => {
  const appVariant = process.env.APP_VARIANT ?? 'development';
  const variant = variants[appVariant];

  if (!variant) {
    throw new Error(`Unsupported APP_VARIANT: ${appVariant}`);
  }

  return {
    ...config,
    name: variant.name,
    slug: 'delight-mobile',
    version: '0.1.0',
    orientation: 'portrait',
    icon: './assets/images/icon.png',
    scheme: 'delight',
    userInterfaceStyle: 'automatic',
    ios: {
      bundleIdentifier: variant.packageIdentifier,
    },
    android: {
      package: variant.packageIdentifier,
      versionCode: 1,
      adaptiveIcon: {
        backgroundColor: '#0f172a',
        foregroundImage: './assets/images/android-icon-foreground.png',
        monochromeImage: './assets/images/android-icon-monochrome.png',
      },
    },
    web: {
      output: 'static',
      favicon: './assets/images/favicon.png',
    },
    plugins: [
      'expo-router',
      [
        'expo-splash-screen',
        {
          backgroundColor: '#0f172a',
          image: './assets/images/splash-icon.png',
          imageWidth: 96,
        },
      ],
    ],
    experiments: {
      typedRoutes: true,
    },
    extra: {
      apiUrl: process.env.EXPO_PUBLIC_API_URL ?? variant.apiUrl,
      appVariant,
    },
  };
};
