const STAGING_API_URL = 'https://delight-staging.laravel.cloud';
const PRODUCTION_API_URL = 'https://mydelight.app';
const EAS_PROJECT_ID = 'aa50d7fa-9028-4991-abb9-8f58d306cadf';
const DELIGHT_APP_ICON = './assets/images/delight-logo.png';

const variants = {
  development: {
    name: 'Delight',
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
    slug: 'delight',
    version: '0.1.0',
    orientation: 'portrait',
    icon: DELIGHT_APP_ICON,
    scheme: 'delight',
    userInterfaceStyle: 'automatic',
    ios: variant.packageIdentifier
      ? {
          bundleIdentifier: variant.packageIdentifier,
        }
      : undefined,
    android: {
      ...(variant.packageIdentifier ? { package: variant.packageIdentifier } : {}),
      adaptiveIcon: {
        backgroundColor: '#0f172a',
        foregroundImage: DELIGHT_APP_ICON,
      },
    },
    web: {
      output: 'static',
      favicon: './assets/images/favicon.png',
    },
    plugins: [
      'expo-router',
      'expo-secure-store',
      [
        'expo-splash-screen',
        {
          backgroundColor: '#0f172a',
          image: DELIGHT_APP_ICON,
          imageWidth: 96,
        },
      ],
    ],
    experiments: {
      typedRoutes: true,
    },
    extra: {
      eas: {
        projectId: EAS_PROJECT_ID,
      },
      apiUrl:
        appVariant === 'development'
          ? (process.env.EXPO_PUBLIC_API_URL ?? variant.apiUrl)
          : variant.apiUrl,
      appVariant,
    },
  };
};
