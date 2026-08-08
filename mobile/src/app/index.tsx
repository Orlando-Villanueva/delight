import { Redirect } from 'expo-router';

import { useAuth } from '@/auth/auth-context';

export default function IndexRoute() {
  const { status } = useAuth();

  return <Redirect href={status === 'authenticated' ? '/(tabs)/home' : '/(auth)/login'} />;
}
