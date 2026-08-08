import { environment } from '@/config/environment';

export function getWebBaseUrl(appVariant = environment.appVariant, apiUrl = environment.apiUrl): string {
  return appVariant === 'dogfood' ? 'https://mydelight.app' : apiUrl;
}
