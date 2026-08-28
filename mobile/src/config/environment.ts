import Constants from 'expo-constants';

type PublicEnvironment = {
  apiUrl: string;
  appVariant: string;
  googleWebClientId?: string;
};

const extra = Constants.expoConfig?.extra;

if (typeof extra?.apiUrl !== 'string' || typeof extra.appVariant !== 'string') {
  throw new Error('The public mobile environment is not configured.');
}

export const environment: PublicEnvironment = {
  apiUrl: extra.apiUrl,
  appVariant: extra.appVariant,
  googleWebClientId:
    typeof extra.googleWebClientId === 'string' && extra.googleWebClientId.trim() !== ''
      ? extra.googleWebClientId
      : undefined,
};
