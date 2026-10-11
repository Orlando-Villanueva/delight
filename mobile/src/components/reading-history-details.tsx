import { useState } from 'react';
import { Alert, Keyboard, Pressable, ScrollView, Text, View } from 'react-native';

import type { ReadingHistoryGroup } from '@/api/reading-history';
import { ReadingNoteEditor } from '@/components/reading-note-editor';
import { BottomSheet, type SheetDismissReason } from '@/components/bottom-sheet';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

type ReadingHistoryDetailsProps = {
  group: ReadingHistoryGroup;
  dateLabel: string;
  onClose: () => void;
  onNoteSaved: () => Promise<void>;
  onEditingChange?: (editing: boolean) => void;
};

export function ReadingHistoryDetails({
  group,
  dateLabel,
  onClose,
  onNoteSaved,
  onEditingChange,
}: Readonly<ReadingHistoryDetailsProps>) {
  const { colors } = useTheme();
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const records = group.records;
  const hasRecords = records !== null && records.length > 0;
  const sharedNote = hasRecords ? records[0].notesText : group.notesText;
  const hasSharedNote = hasRecords && records.every((record) => record.notesText === sharedNote);
  const noteStyle = { color: colors.text, fontSize: 16, lineHeight: 24 };

  function cancelEditing() {
    Keyboard.dismiss();
    setDirty(false);
    setEditing(false);
    onEditingChange?.(false);
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
      dismissDisabled={saving}
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
        contentContainerStyle={{ gap: themeTokens.spacing.section }}
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
                onPress={() => {
                  setEditing(true);
                  onEditingChange?.(true);
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
                onEditingChange?.(false);
              }}
              onCancel={cancelEditing}
            />
          ) : hasSharedNote || !hasRecords ? (
            <Text selectable style={sharedNote ? noteStyle : { ...noteStyle, color: colors.mutedText }}>
              {sharedNote || 'No note'}
            </Text>
          ) : null}
        </View>
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
                <Text selectable style={{ color: colors.text, fontSize: 18, fontWeight: '600' }}>
                  {record.passage}
                </Text>
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
