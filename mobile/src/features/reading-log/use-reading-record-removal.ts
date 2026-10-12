import { useMutation } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { Alert } from 'react-native';

import { deleteReadingChapters, deleteReadingRecord, type ReadingHistoryGroup } from '@/api/reading-history';
import { useAuthenticatedApi } from '@/auth/auth-context';

export function useReadingRecordRemoval({
  group, dateLabel, onProtectDetails, onRemoved,
}: {
  group: ReadingHistoryGroup;
  dateLabel: string;
  onProtectDetails: () => void;
  onRemoved: (recordIds: number[]) => Promise<void>;
}) {
  const request = useAuthenticatedApi();
  const busyRef = useRef(false);
  const confirmingRef = useRef(false);
  const [busy, setBusy] = useState(false);
  const [activeIds, setActiveIds] = useState<number[]>([]);
  const [removedIds, setRemovedIds] = useState<number[]>([]);
  const [error, setError] = useState<string | null>(null);
  const mutation = useMutation({
    mutationFn: (ids: number[]) => group.records?.length === 1
      ? deleteReadingRecord(request, ids[0])
      : deleteReadingChapters(request, group.logIds, ids),
    retry: false,
  });

  async function remove(recordIds: number[], refreshOnly = false) {
    if (busyRef.current) return;
    busyRef.current = true;
    setBusy(true);
    setActiveIds(recordIds);
    setError(null);
    onProtectDetails();
    let deleted = refreshOnly;
    try {
      if (!deleted) {
        await mutation.mutateAsync(recordIds);
        deleted = true;
        setRemovedIds(recordIds);
      }
      await onRemoved(recordIds);
      setRemovedIds([]);
    } catch (failure) {
      setError(deleted
        ? `${recordIds.length === 1 ? 'The chapter was' : 'The chapters were'} removed, but History could not refresh. Retry refresh to try again.`
        : failure instanceof Error ? failure.message : 'The chapter could not be removed. Try again.');
    } finally {
      busyRef.current = false;
      setBusy(false);
    }
  }

  function confirmRemoval(recordIds: number[], passages: string[]) {
    if (busyRef.current || confirmingRef.current || removedIds.length > 0 || recordIds.length === 0) return;
    confirmingRef.current = true;
    const title = recordIds.length === 1 ? `Remove ${passages[0]}?` : `Remove ${recordIds.length} chapters?`;
    const message = group.records?.length === 1
      ? `Remove this chapter from your reading on ${dateLabel}?`
      : `Remove ${passages.join(', ')} from your reading of ${group.passage} on ${dateLabel}?`;
    Alert.alert(title, message, [
      { text: 'Cancel', style: 'cancel', onPress: () => { confirmingRef.current = false; } },
      { text: 'Remove', style: 'destructive', onPress: () => {
        confirmingRef.current = false;
        void remove(recordIds);
      } },
    ], { cancelable: true, onDismiss: () => { confirmingRef.current = false; } });
  }

  return {
    busy, activeIds, error, removedIds, confirmRemoval,
    refresh: () => { if (removedIds.length > 0) void remove(removedIds, true); },
    clearError: () => setError(null),
  };
}
