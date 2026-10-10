import { ScrollView, Text, View } from 'react-native';

import type { ReadingHistoryGroup } from '@/api/reading-history';
import { BottomSheet } from '@/components/bottom-sheet';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

type ReadingHistoryDetailsProps = {
  group: ReadingHistoryGroup;
  dateLabel: string;
  onClose: () => void;
};

export function ReadingHistoryDetails({
  group,
  dateLabel,
  onClose,
}: Readonly<ReadingHistoryDetailsProps>) {
  const { colors } = useTheme();
  const records = group.records;
  const hasRecords = records !== null && records.length > 0;
  const sharedNote = hasRecords ? records[0].notesText : group.notesText;
  const hasSharedNote = hasRecords && records.every((record) => record.notesText === sharedNote);
  const noteStyle = { color: colors.text, fontSize: 16, lineHeight: 24 };

  return (
    <BottomSheet
      visible
      draggable
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
        contentContainerStyle={{ gap: themeTokens.spacing.section }}
      >
        <View style={{ gap: 8 }}>
          <Text selectable style={{ color: colors.mutedText, fontSize: 16, lineHeight: 24 }}>
            {dateLabel}
          </Text>
        </View>
        {hasSharedNote || !hasRecords ? (
          <View style={{ gap: 8 }}>
            <Text accessibilityRole="header" style={{ color: colors.text, fontSize: 18, fontWeight: '600' }}>
              {hasSharedNote && records.length > 1 ? 'Shared note' : 'Note'}
            </Text>
            <Text selectable style={sharedNote ? noteStyle : { ...noteStyle, color: colors.mutedText }}>
              {sharedNote || 'No note'}
            </Text>
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
