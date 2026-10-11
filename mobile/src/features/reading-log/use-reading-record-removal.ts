import { useMutation } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { AccessibilityInfo, Alert } from 'react-native';

import { deleteReadingRecord } from '@/api/reading-history';
import { useAuthenticatedApi } from '@/auth/auth-context';

export function useReadingRecordRemoval({
  dateLabel, onProtectDetails, onRemoved,
}: {
  dateLabel: string;
  onProtectDetails: () => void;
  onRemoved: (recordId: number) => Promise<void>;
}) {
  const request = useAuthenticatedApi();
  const busyRef = useRef(false);
  const confirmingRef = useRef(false);
  const [busy, setBusy] = useState(false);
  const [removedId, setRemovedId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const mutation = useMutation({
    mutationFn: (id: number) => deleteReadingRecord(request, id),
    retry: false,
  });

  async function remove(recordId: number, refreshOnly = false) {
    if (busyRef.current) return;
    busyRef.current = true;
    setBusy(true);
    setError(null);
    onProtectDetails();
    let deleted = refreshOnly;
    try {
      if (!deleted) {
        await mutation.mutateAsync(recordId);
        deleted = true;
        setRemovedId(recordId);
        AccessibilityInfo.announceForAccessibility('Chapter removed from History.');
      }
      await onRemoved(recordId);
      setRemovedId(null);
    } catch (failure) {
      setError(deleted
        ? 'The chapter was removed, but History could not refresh. Refresh History to try again.'
        : failure instanceof Error ? failure.message : 'The chapter could not be removed. Try again.');
    } finally {
      busyRef.current = false;
      setBusy(false);
    }
  }

  function confirmRemoval(recordId: number, passage: string) {
    if (busyRef.current || confirmingRef.current || removedId !== null) return;
    confirmingRef.current = true;
    Alert.alert(`Remove ${passage}?`, `Remove this chapter from your reading on ${dateLabel}?`, [
      { text: 'Cancel', style: 'cancel', onPress: () => { confirmingRef.current = false; } },
      { text: 'Remove', style: 'destructive', onPress: () => {
        confirmingRef.current = false;
        void remove(recordId);
      } },
    ], { cancelable: true, onDismiss: () => { confirmingRef.current = false; } });
  }

  return {
    busy, error, removedId, confirmRemoval,
    refresh: () => { if (removedId !== null) void remove(removedId, true); },
  };
}
