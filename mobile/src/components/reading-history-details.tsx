import { useCallback, useState } from 'react';
import { ActivityIndicator, Alert, Keyboard, Pressable, ScrollView, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { ReadingHistoryGroup, ReadingHistoryRecord } from '@/api/reading-history';
import { ReadingNoteEditor } from '@/components/reading-note-editor';
import { TransientNotice } from '@/components/transient-notice';
import { BottomSheet, type SheetDismissReason } from '@/components/bottom-sheet';
import { useReadingRecordRemoval } from '@/features/reading-log/use-reading-record-removal';
import { readingRemovalNotice } from '@/features/reading-log/removal-notice';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

type ReadingHistoryDetailsProps = {
  group: ReadingHistoryGroup;
  dateLabel: string;
  onClose: () => void;
  onNoteSaved: () => Promise<void>;
  onRecordsRemoved: (recordIds: number[]) => Promise<void>;
  onPreserveDetailsChange?: (editing: boolean) => void;
};

export function ReadingHistoryDetails({
  group,
  dateLabel,
  onClose,
  onNoteSaved,
  onPreserveDetailsChange,
  onRecordsRemoved,
}: Readonly<ReadingHistoryDetailsProps>) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const [successNotice, setSuccessNotice] = useState<string | null>(null);
  const dismissSuccessNotice = useCallback(() => setSuccessNotice(null), []);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [selecting, setSelecting] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const removal = useReadingRecordRemoval({
    group, dateLabel, onProtectDetails: () => onPreserveDetailsChange?.(true),
    onRemoved: async (ids) => {
      await onRecordsRemoved(ids);
      setSuccessNotice(readingRemovalNotice(ids.length));
      setSelecting(false);
      setSelectedIds([]);
    },
  });
  const records = group.records;
  const hasRecords = records !== null && records.length > 0;
  const sharedNote = hasRecords ? records[0].notesText : group.notesText;
  const hasSharedNote = hasRecords && records.every((record) => record.notesText === sharedNote);
  const noteStyle = { color: colors.text, fontSize: 16, lineHeight: 24 };

  function removalAction(record: ReadingHistoryRecord, label = 'Remove') {
    const disabled = removal.busy || removal.removedIds.length > 0;
    const active = removal.activeIds.includes(record.id) && removal.busy;
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`Remove ${record.passage} from History`}
        accessibilityHint="Asks for confirmation before removing this chapter record."
        disabled={disabled}
        accessibilityState={{ disabled, busy: active }}
        onPress={() => removal.confirmRemoval([record.id], [record.passage])}
        style={{
          minHeight: themeTokens.minimumTouchTarget, minWidth: label === 'Remove' ? 112 : 136,
          flexDirection: 'row', gap: 6, alignItems: 'center', justifyContent: 'flex-end',
          opacity: disabled && !active ? 0.5 : 1,
        }}
      >
        {active ? <ActivityIndicator size="small" color={colors.danger} accessible={false} /> : null}
        <Text accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 16, fontWeight: '600' }}>
          {active ? 'Removing…' : removal.removedIds.includes(record.id) ? 'Removed' : label}
        </Text>
      </Pressable>
    );
  }

  function removalFeedback(record: ReadingHistoryRecord) {
    if (!removal.activeIds.includes(record.id) || !removal.error) return null;
    return (
      <View style={{ gap: 8 }}>
        <Text accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 16 }}>
          {removal.error}
        </Text>
        {removal.removedIds.length > 0 ? (
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

  function cancelSelection() {
    setSelecting(false);
    setSelectedIds([]);
    removal.clearError();
    onPreserveDetailsChange?.(false);
  }

  function toggleSelection(id: number) {
    setSelectedIds((ids) => ids.includes(id) ? ids.filter((selected) => selected !== id) : [...ids, id]);
    removal.clearError();
  }

  function cancelEditing() {
    Keyboard.dismiss();
    setDirty(false);
    setEditing(false);
    onPreserveDetailsChange?.(false);
  }

  async function beforeDismiss(reason: SheetDismissReason): Promise<boolean> {
    if (selecting && reason === 'back' && removal.removedIds.length === 0) {
      cancelSelection();
      return false;
    }
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
                disabled={selecting || removal.busy || removal.removedIds.length > 0}
                accessibilityState={{ disabled: selecting || removal.busy || removal.removedIds.length > 0 }}
                onPress={() => {
                  setSuccessNotice(null);
                  setEditing(true);
                  onPreserveDetailsChange?.(true);
                }}
                style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center',
                  opacity: selecting ? 0.5 : 1 }}
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
                setSuccessNotice('Note saved.');
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
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center',
              justifyContent: 'space-between', gap: 12, minHeight: themeTokens.minimumTouchTarget }}>
              <Text accessibilityRole="header" style={{ color: colors.text, fontSize: 18, fontWeight: '600' }}>
                Chapters read
              </Text>
              {selecting ? (
                <Pressable accessibilityRole="button"
                  accessibilityLabel={selectedIds.length === records.length ? 'Deselect all chapters' : 'Select all chapters'}
                  disabled={removal.busy || removal.removedIds.length > 0}
                  accessibilityState={{ disabled: removal.busy || removal.removedIds.length > 0 }}
                  onPress={() => {
                    setSelectedIds(selectedIds.length === records.length ? [] : records.map((record) => record.id));
                    removal.clearError();
                  }}
                  style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}>
                  <Text style={{ color: colors.primary, fontSize: 16 }}>
                    {selectedIds.length === records.length ? 'Deselect all' : 'Select all'}
                  </Text>
                </Pressable>
              ) : !editing ? (
                <Pressable accessibilityRole="button" accessibilityLabel="Remove chapters"
                  onPress={() => {
                    setSuccessNotice(null);
                    setSelecting(true);
                    onPreserveDetailsChange?.(true);
                  }}
                  style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}>
                  <Text style={{ color: colors.danger, fontSize: 16, fontWeight: '600' }}>Remove chapters</Text>
                </Pressable>
              ) : null}
            </View>
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
                  borderBottomWidth: 1,
                  borderBottomColor: colors.border,
                }}
              >
                {selecting ? (
                  <Pressable accessibilityRole="checkbox" accessibilityLabel={record.passage}
                    accessibilityHint="Toggles this chapter in the removal selection."
                    accessibilityState={{ checked: selectedIds.includes(record.id),
                      disabled: removal.busy || removal.removedIds.length > 0 }}
                    disabled={removal.busy || removal.removedIds.length > 0}
                    onPress={() => toggleSelection(record.id)}
                    style={{ minHeight: themeTokens.minimumTouchTarget,
                      flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                    <View style={{ width: 24, height: 24, borderWidth: 2,
                      borderRadius: 4, borderColor: colors.primary, alignItems: 'center', justifyContent: 'center' }}>
                      <Text accessible={false} style={{ color: colors.primary, fontSize: 16 }}>
                        {selectedIds.includes(record.id) ? '✓' : ''}
                      </Text>
                    </View>
                    <Text style={{ color: colors.text, fontSize: 18, fontWeight: '600', flexShrink: 1 }}>
                      {record.passage}
                    </Text>
                  </Pressable>
                ) : (
                  <View style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}>
                    <Text selectable style={{ color: colors.text, fontSize: 18, fontWeight: '600' }}>
                      {record.passage}
                    </Text>
                  </View>
                )}
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
      {selecting && hasRecords ? (
        <View style={{ paddingTop: 12, gap: 8, borderTopWidth: 1, borderTopColor: colors.border }}>
          {removal.error ? (
            <Text accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 16 }}>
              {removal.error}
            </Text>
          ) : null}
          <View style={{ flexDirection: 'row', gap: 16, alignItems: 'center' }}>
            <Pressable accessibilityRole="button"
              accessibilityLabel={removal.removedIds.length > 0 && !removal.busy
                ? 'Retry refresh' : `Remove ${selectedIds.length} ${selectedIds.length === 1 ? 'chapter' : 'chapters'}`}
              accessibilityState={{ disabled: removal.busy || selectedIds.length === 0, busy: removal.busy }}
              disabled={removal.busy || selectedIds.length === 0}
              onPress={() => {
                if (removal.removedIds.length > 0) { removal.refresh(); return; }
                const selected = records.filter((record) => selectedIds.includes(record.id));
                removal.confirmRemoval(selected.map((record) => record.id), selected.map((record) => record.passage));
              }}
              style={{ minHeight: themeTokens.minimumTouchTarget,
                flexDirection: 'row', alignItems: 'center', gap: 6, flexShrink: 1 }}>
              {removal.busy ? <ActivityIndicator size="small" color={colors.danger} accessible={false} /> : null}
              <Text accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 16, fontWeight: '600' }}>
                {removal.busy ? 'Removing…' : removal.removedIds.length > 0
                  ? 'Retry refresh' : `Remove ${selectedIds.length} ${selectedIds.length === 1 ? 'chapter' : 'chapters'}`}
              </Text>
            </Pressable>
            <Pressable accessibilityRole="button" accessibilityLabel="Cancel chapter selection"
              accessibilityState={{ disabled: removal.busy || removal.removedIds.length > 0 }}
              disabled={removal.busy || removal.removedIds.length > 0}
              onPress={cancelSelection}
              style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}>
              <Text style={{ color: colors.primary, fontSize: 16 }}>Cancel</Text>
            </Pressable>
          </View>
        </View>
      ) : null}
      {successNotice ? (
        <TransientNotice
          message={successNotice}
          bottom={insets.bottom + themeTokens.spacing.screen}
          onDismiss={dismissSuccessNotice}
        />
      ) : null}
    </BottomSheet>
  );
}
