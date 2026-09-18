import Constants from 'expo-constants';

type PublicEnvironment = {
  apiUrl: string;
  appVariant: string;
  androidUpdateCheckerEnabled: boolean;
  googleWebClientId?: string;
};

const extra = Constants.expoConfig?.extra;

if (
  typeof extra?.apiUrl !== 'string'
  || typeof extra.appVariant !== 'string'
  || typeof extra.androidUpdateCheckerEnabled !== 'boolean'
) {
  throw new Error('The public mobile environment is not configured.');
}

export const environment: PublicEnvironment = {
  apiUrl: extra.apiUrl,
  appVariant: extra.appVariant,
  androidUpdateCheckerEnabled: extra.androidUpdateCheckerEnabled,
  googleWebClientId:
    typeof extra.googleWebClientId === 'string' && extra.googleWebClientId.trim() !== ''
      ? extra.googleWebClientId
      : undefined,
};
