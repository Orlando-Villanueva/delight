import { Stack } from 'expo-router/stack';

import { AuthGate } from '@/auth/auth-gate';

export default function AuthLayout() {
  return (
    <AuthGate>
      <Stack screenOptions={{ headerShown: false }} />
    </AuthGate>
  );
}
