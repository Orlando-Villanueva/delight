import { useQuery } from '@tanstack/react-query';
import { useCallback, useEffect } from 'react';
import { AppState, type AppStateStatus } from 'react-native';

import { fetchBootstrap } from '@/api/bootstrap';
import { useAuthenticatedApi } from '@/auth/auth-context';

export function useBootstrap() {
  const request = useAuthenticatedApi();
  const { refetch, ...query } = useQuery({
    queryKey: ['bootstrap'],
    queryFn: () => fetchBootstrap(request),
  });

  const refresh = useCallback(async () => {
    await refetch();
  }, [refetch]);

  useEffect(() => {
    function refreshWhenForegrounded(nextAppState: AppStateStatus) {
      if (nextAppState === 'active') {
        void refresh();
      }
    }

    return AppState.addEventListener('change', refreshWhenForegrounded).remove;
  }, [refresh]);

  return { ...query, refetch, refresh };
}
