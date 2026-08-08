import { Redirect } from 'expo-router';

import { AuthGate } from '@/auth/auth-gate';
import { useAuth } from '@/auth/auth-context';

export default function IndexRoute() {
  const { status } = useAuth();

  return (
    <AuthGate>
      <Redirect href={status === 'authenticated' ? '/(tabs)/home' : '/(auth)/login'} />
    </AuthGate>
  );
}
