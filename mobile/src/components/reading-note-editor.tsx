import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { AccessibilityInfo, Pressable, Text, TextInput, View } from 'react-native';
import { z } from 'zod';

import { ApiError } from '@/api/api-error';
import { type ReadingHistoryGroup, updateReadingNote } from '@/api/reading-history';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { readingNoteSchema } from '@/features/reading-log/form';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

const schema = z.object({ note: readingNoteSchema });

export function ReadingNoteEditor({
  group, initialNote, replacesDifferentNotes, onSavingChange, onSaved, onCancel, onDirtyChange,
}: Readonly<{
  group: ReadingHistoryGroup;
  initialNote: string;
  replacesDifferentNotes: boolean;
  onSavingChange: (saving: boolean) => void;
  onSaved: () => Promise<void>;
  onCancel: () => void;
  onDirtyChange?: (dirty: boolean) => void;
}>) {
  const { colors } = useTheme();
  const request = useAuthenticatedApi();
  const busyRef = useRef(false);
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(false);
  const [confirmed, setConfirmed] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [inputHeight, setInputHeight] = useState(120);
  const { control, handleSubmit, setError, formState: { errors, isDirty } } = useForm<{ note: string }>({
    defaultValues: { note: initialNote }, resolver: zodResolver(schema),
  });
  useEffect(() => {
    onDirtyChange?.(isDirty && !saved);
  }, [isDirty, onDirtyChange, saved]);
  const mutation = useMutation({
    mutationFn: (note: string) => updateReadingNote(request, group.logIds, note),
    retry: false,
  });

  async function save({ note }: { note: string }) {
    if (busyRef.current || (replacesDifferentNotes && !confirmed)) {
      return;
    }
    busyRef.current = true;
    setBusy(true);
    onSavingChange(true);
    setMessage(null);
    let didSave = saved;
    try {
      if (!didSave) {
        await mutation.mutateAsync(note);
        didSave = true;
        setSaved(true);
        AccessibilityInfo.announceForAccessibility('Note saved.');
      }
      await onSaved();
    } catch (error) {
      if (didSave) {
        setMessage('Your note was saved, but History could not refresh. Tap Refresh History to try again.');
      } else if (error instanceof ApiError && error.validationErrors.notes_text?.[0]) {
        setError('note', { message: error.validationErrors.notes_text[0] });
      } else {
        setMessage(error instanceof Error ? error.message : 'The note could not be saved. Try again.');
      }
    } finally {
      busyRef.current = false;
      setBusy(false);
      onSavingChange(false);
    }
  }

  return (
    <View style={{ gap: 12 }}>
      {replacesDifferentNotes ? (
        <View style={{ gap: 8 }}>
          <Text style={{ color: colors.text, fontSize: 16, lineHeight: 24 }}>
            These chapters have different notes. Saving will replace all of them with this note.
          </Text>
          <Pressable
            accessibilityRole="checkbox"
            accessibilityLabel="Replace the different chapter notes"
            accessibilityState={{ checked: confirmed, disabled: busy || saved }}
            disabled={busy || saved}
            onPress={() => setConfirmed((current) => !current)}
            style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}
          >
            <Text style={{ color: colors.primary, fontSize: 16 }}>
              {confirmed ? 'Replacement confirmed' : 'Confirm replacement'}
            </Text>
          </Pressable>
        </View>
      ) : null}
      <Controller
        control={control}
        name="note"
        render={({ field: { value, onChange, onBlur } }) => (
          <TextInput
            accessibilityLabel="Note"
            multiline
            scrollEnabled={false}
            editable={!busy && !saved}
            value={value}
            onChangeText={onChange}
            onBlur={onBlur}
            onContentSizeChange={({ nativeEvent }) => {
              setInputHeight(Math.max(120, Math.ceil(nativeEvent.contentSize.height) + 24));
            }}
            textAlignVertical="top"
            placeholder="Add a note"
            placeholderTextColor={colors.mutedText}
            style={{
              height: inputHeight, minHeight: 120, padding: 12,
              borderWidth: 1, borderRadius: themeTokens.radius.control,
              borderColor: errors.note ? colors.danger : colors.border,
              color: colors.text, backgroundColor: colors.surface, fontSize: 16,
            }}
          />
        )}
      />
      {errors.note?.message || message ? (
        <Text accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 16 }}>
          {errors.note?.message ?? message}
        </Text>
      ) : null}
      <View style={{ flexDirection: 'row', gap: 16 }}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={saved ? 'Refresh History' : 'Save note'}
          accessibilityState={{ disabled: busy || (replacesDifferentNotes && !confirmed) }}
          disabled={busy || (replacesDifferentNotes && !confirmed)}
          onPress={() => { void handleSubmit(save)(); }}
          style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center', opacity: busy ? 0.5 : 1 }}
        >
          <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>
            {busy ? (saved ? 'Refreshing…' : 'Saving…') : (saved ? 'Refresh History' : 'Save')}
          </Text>
        </Pressable>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Cancel note editing"
          accessibilityState={{ disabled: busy }}
          disabled={busy}
          onPress={onCancel}
          style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}
        >
          <Text style={{ color: colors.primary, fontSize: 16 }}>Cancel</Text>
        </Pressable>
      </View>
    </View>
  );
}
