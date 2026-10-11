import { useState } from 'react';
import { ActivityIndicator, Alert, Keyboard, Pressable, ScrollView, Text, View } from 'react-native';

import type { ReadingHistoryGroup, ReadingHistoryRecord } from '@/api/reading-history';
import { ReadingNoteEditor } from '@/components/reading-note-editor';
import { BottomSheet, type SheetDismissReason } from '@/components/bottom-sheet';
import { useReadingRecordRemoval } from '@/features/reading-log/use-reading-record-removal';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

type ReadingHistoryDetailsProps = {
  group: ReadingHistoryGroup;
  dateLabel: string;
  onClose: () => void;
  onNoteSaved: () => Promise<void>;
  onRecordRemoved: (recordId: number) => Promise<void>;
  onPreserveDetailsChange?: (editing: boolean) => void;
};

export function ReadingHistoryDetails({
  group,
  dateLabel,
  onClose,
  onNoteSaved,
  onPreserveDetailsChange,
  onRecordRemoved,
}: Readonly<ReadingHistoryDetailsProps>) {
  const { colors } = useTheme();
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const removal = useReadingRecordRemoval({
    dateLabel, onProtectDetails: () => onPreserveDetailsChange?.(true), onRemoved: onRecordRemoved,
  });
  const records = group.records;
  const hasRecords = records !== null && records.length > 0;
  const sharedNote = hasRecords ? records[0].notesText : group.notesText;
  const hasSharedNote = hasRecords && records.every((record) => record.notesText === sharedNote);
  const noteStyle = { color: colors.text, fontSize: 16, lineHeight: 24 };

  function removalAction(record: ReadingHistoryRecord, label = 'Remove') {
    const disabled = removal.busy || removal.removedId !== null;
    const active = removal.activeId === record.id && removal.busy;
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`Remove ${record.passage} from History`}
        accessibilityHint="Asks for confirmation before removing this chapter record."
        disabled={disabled}
        accessibilityState={{ disabled, busy: active }}
        onPress={() => removal.confirmRemoval(record.id, record.passage)}
        style={{
          minHeight: themeTokens.minimumTouchTarget, minWidth: label === 'Remove' ? 112 : 136,
          flexDirection: 'row', gap: 6, alignItems: 'center', justifyContent: 'flex-end',
          opacity: disabled && !active ? 0.5 : 1,
        }}
      >
        {active ? <ActivityIndicator size="small" color={colors.danger} accessible={false} /> : null}
        <Text accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 16, fontWeight: '600' }}>
          {active ? 'Removing…' : record.id === removal.removedId ? 'Removed' : label}
        </Text>
      </Pressable>
    );
  }

  function removalFeedback(record: ReadingHistoryRecord) {
    if (removal.activeId !== record.id || !removal.error) return null;
    return (
      <View style={{ gap: 8 }}>
        <Text accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 16 }}>
          {removal.error}
        </Text>
        {removal.removedId !== null ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Retry refresh"
            disabled={removal.busy}
            accessibilityState={{ disabled: removal.busy }}
            onPress={removal.refresh}
            style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}
          >
            <Text style={{ color: colors.primary, fontSize: 16 }}>Retry refresh</Text>
          </Pressable>
        ) : null}
      </View>
    );
  }

  function cancelEditing() {
    Keyboard.dismiss();
    setDirty(false);
    setEditing(false);
    onPreserveDetailsChange?.(false);
  }

  async function beforeDismiss(reason: SheetDismissReason): Promise<boolean> {
    if (!editing) {
      return true;
    }
    if (reason === 'back' && Keyboard.isVisible()) {
      Keyboard.dismiss();
      return false;
    }
    if (dirty) {
      const discard = await new Promise<boolean>((resolve) => {
        Alert.alert('Discard changes?', 'Your unsaved note changes will be lost.', [
          { text: 'Keep editing', style: 'cancel', onPress: () => resolve(false) },
          { text: 'Discard', style: 'destructive', onPress: () => resolve(true) },
        ], { cancelable: true, onDismiss: () => resolve(false) });
      });
      if (!discard) {
        return false;
      }
    }
    if (reason === 'back') {
      cancelEditing();
      return false;
    }
    Keyboard.dismiss();
    return true;
  }

  return (
    <BottomSheet
      visible
      draggable
      avoidKeyboard={editing}
      dismissDisabled={saving || removal.busy}
      onBeforeDismiss={beforeDismiss}
      title={group.passage}
      onClose={onClose}
      maxHeight="85%"
      padBottomSafeArea
      dismissAccessibilityLabel="Dismiss reading details"
      dismissAccessibilityHint="Returns to your reading history."
      closeAccessibilityLabel="Close reading details"
      closeAccessibilityHint="Returns to your reading history."
    >
      <ScrollView
        style={{ flexShrink: 1 }}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ gap: themeTokens.spacing.section, paddingRight: 12 }}
      >
        <Text selectable style={{ color: colors.mutedText, fontSize: 16, lineHeight: 24 }}>
          {dateLabel}
        </Text>
        <View style={{ gap: 8 }}>
          <View
            style={{
              flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
              gap: 12, minHeight: themeTokens.minimumTouchTarget,
            }}
          >
            <Text accessibilityRole="header" style={{ color: colors.text, fontSize: 18, fontWeight: '600' }}>
              Note
            </Text>
            {hasRecords && !editing ? (
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Edit note"
                disabled={removal.busy || removal.removedId !== null}
                accessibilityState={{ disabled: removal.busy || removal.removedId !== null }}
                onPress={() => {
                  setEditing(true);
                  onPreserveDetailsChange?.(true);
                }}
                style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}
              >
                <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>Edit note</Text>
              </Pressable>
            ) : null}
          </View>
          {editing ? (
            <ReadingNoteEditor
              group={group}
              initialNote={hasSharedNote ? sharedNote ?? '' : ''}
              replacesDifferentNotes={hasRecords && !hasSharedNote}
              onSavingChange={setSaving}
              onDirtyChange={setDirty}
              onSaved={async () => {
                await onNoteSaved();
                Keyboard.dismiss();
                setEditing(false);
                onPreserveDetailsChange?.(false);
              }}
              onCancel={cancelEditing}
            />
          ) : hasSharedNote || !hasRecords ? (
            <Text selectable style={sharedNote ? noteStyle : { ...noteStyle, color: colors.mutedText }}>
              {sharedNote || 'No note'}
            </Text>
          ) : null}
        </View>
        {hasRecords && records.length === 1 && !editing ? (
          <View style={{ gap: 8, alignItems: 'flex-start' }}>
            {removalAction(records[0], 'Remove chapter')}
            {removalFeedback(records[0])}
          </View>
        ) : null}
        {hasRecords && records.length > 1 ? (
          <View style={{ gap: 12 }}>
            <Text accessibilityRole="header" style={{ color: colors.text, fontSize: 18, fontWeight: '600' }}>
              Chapters read
            </Text>
            {!hasSharedNote ? (
              <Text selectable style={{ color: colors.mutedText, fontSize: 16, lineHeight: 24 }}>
                These chapters have different notes.
              </Text>
            ) : null}
            {records.map((record) => (
              <View
                key={record.id}
                style={{
                  gap: 8,
                  paddingVertical: 10,
                  borderBottomWidth: 1,
                  borderBottomColor: colors.border,
                }}
              >
                <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                  <Text selectable style={{ color: colors.text, fontSize: 18, fontWeight: '600', flexShrink: 1 }}>
                    {record.passage}
                  </Text>
                  {!editing ? removalAction(record) : null}
                </View>
                {removalFeedback(record)}
                {!hasSharedNote ? (
                  <Text
                    selectable
                    style={record.notesText ? noteStyle : { ...noteStyle, color: colors.mutedText }}
                  >
                    {record.notesText || 'No note'}
                  </Text>
                ) : null}
              </View>
            ))}
          </View>
        ) : !hasRecords ? (
          <Text accessibilityLiveRegion="polite" selectable style={{ color: colors.mutedText, fontSize: 16, lineHeight: 24 }}>
            Individual chapter details are unavailable. Close this sheet and refresh History to try again.
          </Text>
        ) : null}
      </ScrollView>
    </BottomSheet>
  );
}
