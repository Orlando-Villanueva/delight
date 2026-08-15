import { useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { BootstrapBook } from '@/api/bootstrap';
import { BottomSheet } from '@/components/bottom-sheet';
import { booksByTestament, chapterCountLabel, testamentLabel } from '@/features/reading-log/form';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

function selectedTestament(
  books: readonly BootstrapBook[],
  selectedBookId: number | null,
): string {
  return books.find((book) => book.id === selectedBookId)?.testament ?? 'old';
}

type BookPickerModalProps = {
  books: readonly BootstrapBook[];
  selectedBookId: number | null;
  visible: boolean;
  onClose: () => void;
  onSelect: (book: BootstrapBook) => void;
};

export function BookPickerModal({
  books,
  selectedBookId,
  visible,
  onClose,
  onSelect,
}: Readonly<BookPickerModalProps>) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const [activeTestamentOverride, setActiveTestamentOverride] = useState<string | null>(null);
  const availableTestaments = booksByTestament(books);
  const activeTestament = activeTestamentOverride ?? selectedTestament(books, selectedBookId);
  const activeBooks = availableTestaments.find(([testament]) => testament === activeTestament)?.[1] ?? [];

  function close() {
    setActiveTestamentOverride(null);
    onClose();
  }

  function selectBook(book: BootstrapBook) {
    setActiveTestamentOverride(null);
    onSelect(book);
  }

  return (
    <BottomSheet
      visible={visible}
      title="Choose a book"
      onClose={close}
      maxHeight="80%"
      paddingBottom={0}
      dismissAccessibilityLabel="Dismiss book list"
      dismissAccessibilityHint="Closes the Bible book list"
      closeAccessibilityLabel="Close book list"
      closeAccessibilityHint="Closes the Bible book list"
    >
      <View
        accessibilityRole="tablist"
        style={{
          flexDirection: 'row',
          padding: 4,
          borderRadius: themeTokens.radius.control,
          backgroundColor: colors.input,
        }}
      >
        {availableTestaments.map(([testament]) => {
          const isSelected = testament === activeTestament;
          const label = testamentLabel(testament);

          return (
            <Pressable
              key={testament}
              accessibilityRole="tab"
              accessibilityLabel={`Show ${label} books`}
              accessibilityState={{ selected: isSelected }}
              onPress={() => setActiveTestamentOverride(testament)}
              style={{
                flex: 1,
                minHeight: themeTokens.minimumTouchTarget,
                alignItems: 'center',
                justifyContent: 'center',
                paddingHorizontal: 6,
                borderRadius: themeTokens.radius.control - 4,
                backgroundColor: isSelected ? colors.surface : 'transparent',
              }}
            >
              <Text
                style={{
                  color: isSelected ? colors.primary : colors.mutedText,
                  fontSize: 13,
                  fontWeight: '700',
                  textAlign: 'center',
                }}
              >
                {label}
              </Text>
            </Pressable>
          );
        })}
      </View>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ gap: 8, paddingBottom: Math.max(insets.bottom, 8) }}
      >
        {activeBooks.map((book) => (
          <Pressable
            key={book.id}
            accessibilityRole="button"
            accessibilityLabel={book.name}
            accessibilityHint={`Selects ${book.name}. ${chapterCountLabel(book)}`}
            accessibilityState={{ selected: selectedBookId === book.id }}
            onPress={() => selectBook(book)}
            style={{
              minHeight: themeTokens.minimumTouchTarget,
              justifyContent: 'center',
              paddingHorizontal: 12,
              borderWidth: 1,
              borderColor: selectedBookId === book.id ? colors.primary : colors.border,
              borderRadius: themeTokens.radius.control,
              backgroundColor: selectedBookId === book.id ? colors.primarySubtle : colors.input,
            }}
          >
            <Text style={{ color: colors.text, fontSize: 16 }}>{book.name}</Text>
          </Pressable>
        ))}
      </ScrollView>
    </BottomSheet>
  );
}
