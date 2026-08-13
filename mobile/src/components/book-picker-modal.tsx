import { useEffect, useState } from 'react';
import {
  Animated,
  Modal,
  Pressable,
  ScrollView,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';

import type { BootstrapBook } from '@/api/bootstrap';
import { booksByTestament, chapterCountLabel, testamentLabel } from '@/features/reading-log/form';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

const overlayColor = 'rgba(15, 23, 42, 0.48)';
const overlayEnterMs = 200;
const sheetEnterMs = 280;

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
  const { height } = useWindowDimensions();
  const [overlayOpacity] = useState(() => new Animated.Value(0));
  const [sheetTranslateY] = useState(() => new Animated.Value(height));
  const [activeTestamentOverride, setActiveTestamentOverride] = useState<string | null>(null);
  const availableTestaments = booksByTestament(books);
  const activeTestament = activeTestamentOverride ?? selectedTestament(books, selectedBookId);
  const activeBooks = availableTestaments.find(([testament]) => testament === activeTestament)?.[1] ?? [];

  useEffect(() => {
    if (!visible) {
      return;
    }

    const offscreen = height;
    overlayOpacity.setValue(0);
    sheetTranslateY.setValue(offscreen);

    Animated.parallel([
      Animated.timing(overlayOpacity, {
        toValue: 1,
        duration: overlayEnterMs,
        useNativeDriver: true,
      }),
      Animated.timing(sheetTranslateY, {
        toValue: 0,
        duration: sheetEnterMs,
        useNativeDriver: true,
      }),
    ]).start();
  }, [height, overlayOpacity, sheetTranslateY, visible]);

  function close() {
    setActiveTestamentOverride(null);
    onClose();
  }

  function selectBook(book: BootstrapBook) {
    setActiveTestamentOverride(null);
    onSelect(book);
  }

  return (
    <Modal
      animationType="none"
      transparent
      visible={visible}
      onRequestClose={close}
      accessibilityViewIsModal
    >
      <View style={{ flex: 1, justifyContent: 'flex-end' }}>
        <Animated.View
          pointerEvents="none"
          style={{
            position: 'absolute',
            top: 0,
            right: 0,
            bottom: 0,
            left: 0,
            backgroundColor: overlayColor,
            opacity: overlayOpacity,
          }}
        />
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Dismiss book list"
          accessibilityHint="Closes the Bible book list"
          onPress={close}
          style={{ position: 'absolute', top: 0, right: 0, bottom: 0, left: 0 }}
        />
        <Animated.View
          testID="book-picker-modal"
          style={{
            maxHeight: '80%',
            padding: themeTokens.spacing.screen,
            borderTopLeftRadius: themeTokens.radius.card,
            borderTopRightRadius: themeTokens.radius.card,
            borderCurve: 'continuous',
            backgroundColor: colors.surface,
            transform: [{ translateY: sheetTranslateY }],
          }}
        >
          <View
            style={{
              flexDirection: 'row',
              alignItems: 'center',
              justifyContent: 'space-between',
              gap: 12,
            }}
          >
            <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700', flex: 1 }}>
              Choose a book
            </Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Close book list"
              accessibilityHint="Closes the Bible book list"
              onPress={close}
              style={{
                minHeight: themeTokens.minimumTouchTarget,
                minWidth: themeTokens.minimumTouchTarget,
                justifyContent: 'center',
              }}
            >
              <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>Close</Text>
            </Pressable>
          </View>
          <View
            accessibilityRole="tablist"
            style={{
              flexDirection: 'row',
              marginTop: themeTokens.spacing.section,
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
            style={{ marginTop: themeTokens.spacing.section }}
            contentContainerStyle={{ gap: 8, paddingBottom: 24 }}
          >
            {activeBooks.map((book) => (
              <Pressable
                key={book.id}
                accessibilityRole="button"
                accessibilityLabel={book.name}
                accessibilityHint={`Selects ${book.name}. ${chapterCountLabel(book)}`}
                accessibilityState={{ selected: selectedBookId === book.id }}
                onPress={() => selectBook(book)}
                testID={`book-option-${book.id}`}
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
        </Animated.View>
      </View>
    </Modal>
  );
}
