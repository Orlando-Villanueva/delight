import { environment } from '@/config/environment';

export function getWebBaseUrl(appVariant = environment.appVariant, apiUrl = environment.apiUrl): string {
  return appVariant === 'production' ? 'https://mydelight.app' : apiUrl;
}
