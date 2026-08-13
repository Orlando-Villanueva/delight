import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import {
  AccessibilityInfo,
  ActivityIndicator,
  AppState,
  type AppStateStatus,
  KeyboardAvoidingView,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';

import { ApiError } from '@/api/api-error';
import { fetchBootstrap, type BootstrapBook, type BootstrapData } from '@/api/bootstrap';
import { createReadingLog, type CreateReadingInput, type CreatedReading } from '@/api/reading-log';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { BookPickerModal } from '@/components/book-picker-modal';
import { useKeyboardFocusedScroll } from '@/hooks/use-keyboard-focused-scroll';
import {
  chapterCountLabel,
  chaptersAfterBookChange,
  clampChapterInput,
  createReadingLogDefaults,
  createReadingLogSchema,
  findBook,
  formatReadingDate,
  mapReadingLogValidationErrors,
  readingLogSuccessDismissMs,
  toCreateReadingInput,
  type ReadingLogFieldName,
  type ReadingLogFormValues,
} from '@/features/reading-log/form';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

function getButtonOpacity(disabled: boolean, pressed: boolean): number {
  if (disabled) {
    return 0.55;
  }

  return pressed ? 0.82 : 1;
}

function FieldError({ message }: Readonly<{ message?: string }>) {
  const { colors } = useTheme();

  if (!message) {
    return null;
  }

  return (
    <Text selectable accessibilityLiveRegion="polite" style={{ color: colors.danger, fontSize: 15 }}>
      {message}
    </Text>
  );
}

function ActionButton({
  label,
  accessibilityLabel,
  accessibilityHint,
  disabled,
  loadingLabel,
  isLoading = false,
  onPress,
  testID,
}: Readonly<{
  label: string;
  accessibilityLabel?: string;
  accessibilityHint: string;
  disabled: boolean;
  loadingLabel?: string;
  isLoading?: boolean;
  onPress: () => void;
  testID?: string;
}>) {
  const { colors } = useTheme();

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityHint={accessibilityHint}
      accessibilityState={{ disabled }}
      disabled={disabled}
      onPress={onPress}
      testID={testID}
      style={({ pressed }) => ({
        minHeight: themeTokens.minimumTouchTarget,
        alignItems: 'center',
        justifyContent: 'center',
        paddingHorizontal: themeTokens.spacing.section,
        borderRadius: themeTokens.radius.control,
        borderCurve: 'continuous',
        backgroundColor: colors.accentAction,
        opacity: getButtonOpacity(disabled, pressed),
      })}
    >
      {isLoading ? (
        <ActivityIndicator accessibilityLabel={loadingLabel} color={colors.accentActionContrast} />
      ) : (
        <Text style={{ color: colors.accentActionContrast, fontSize: 17, fontWeight: '700' }}>
          {label}
        </Text>
      )}
    </Pressable>
  );
}

function LoadingState() {
  const { colors } = useTheme();

  return (
    <View
      accessibilityLiveRegion="polite"
      style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 }}
    >
      <ActivityIndicator color={colors.primary} />
      <Text selectable style={{ color: colors.mutedText }}>
        Loading the reading form…
      </Text>
    </View>
  );
}

function ReadingLogFields({ bootstrap }: Readonly<{ bootstrap: BootstrapData }>) {
  const { colors } = useTheme();
  const request = useAuthenticatedApi();
  const queryClient = useQueryClient();
  const bootstrapRef = useRef(bootstrap);
  const isSubmittingRef = useRef(false);
  const previousBookId = useRef(preferredInitialBookId(bootstrap));
  const scrollViewRef = useRef<ScrollView>(null);
  const keyboardFocusedScroll = useKeyboardFocusedScroll(scrollViewRef);
  const [formMessage, setFormMessage] = useState<string | null>(null);
  const [success, setSuccess] = useState<CreatedReading | null>(null);
  const [cooldownSeconds, setCooldownSeconds] = useState(0);
  const [isBookPickerOpen, setIsBookPickerOpen] = useState(false);
  const [areNotesVisible, setAreNotesVisible] = useState(false);

  useEffect(() => {
    bootstrapRef.current = bootstrap;
  }, [bootstrap]);

  const {
    control,
    handleSubmit,
    reset,
    setError,
    setValue,
    formState: { dirtyFields, errors },
  } = useForm<ReadingLogFormValues>({
    defaultValues: createReadingLogDefaults(bootstrap),
    resolver: (values, context, options) => {
      const { books, today, yesterday } = bootstrapRef.current;

      return zodResolver(createReadingLogSchema(books, [today, yesterday]))(values, context, options);
    },
  });

  const bookId = useWatch({ control, name: 'bookId' });
  const startChapter = useWatch({ control, name: 'startChapter' });
  const endChapter = useWatch({ control, name: 'endChapter' });
  const selectedBook = findBook(bootstrap.books, bookId);
  const recentBooks = bootstrap.recent_book_ids
    .map((recentBookId) => findBook(bootstrap.books, recentBookId))
    .filter((book): book is BootstrapBook => book !== undefined);

  useEffect(() => {
    if (!dirtyFields.dateRead) {
      setValue('dateRead', bootstrap.today, { shouldDirty: false });
    }
  }, [bootstrap.today, dirtyFields.dateRead, setValue]);

  useEffect(() => {
    if (previousBookId.current === bookId) {
      return;
    }

    const nextChapters = chaptersAfterBookChange(selectedBook, startChapter, endChapter);
    previousBookId.current = bookId;
    setValue('startChapter', nextChapters.startChapter);
    setValue('endChapter', nextChapters.endChapter);
  }, [bookId, endChapter, selectedBook, setValue, startChapter]);

  useEffect(() => {
    if (cooldownSeconds <= 0) {
      return;
    }

    const timer = setTimeout(() => setCooldownSeconds((seconds) => seconds - 1), 1_000);

    return () => clearTimeout(timer);
  }, [cooldownSeconds]);

  useEffect(() => {
    if (!success) {
      return;
    }

    const timer = setTimeout(() => setSuccess(null), readingLogSuccessDismissMs);

    return () => clearTimeout(timer);
  }, [success]);

  const mutation = useMutation({
    mutationFn: (input: CreateReadingInput) => createReadingLog(request, input),
    retry: false,
  });

  async function submit(values: ReadingLogFormValues) {
    if (isSubmittingRef.current || mutation.isPending || cooldownSeconds > 0) {
      return;
    }

    isSubmittingRef.current = true;
    setFormMessage(null);
    setSuccess(null);

    try {
      const created = await mutation.mutateAsync(toCreateReadingInput(values));
      const defaults = {
        ...createReadingLogDefaults(bootstrapRef.current),
        bookId: created.book.id,
      };
      previousBookId.current = defaults.bookId;
      reset(defaults);
      setAreNotesVisible(false);
      setSuccess(created);
      AccessibilityInfo.announceForAccessibility(
        `${created.passage} recorded for ${formatReadingDate(created.dateRead)}.`,
      );
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['bootstrap'] }),
        queryClient.invalidateQueries({ queryKey: ['reading-history'] }),
      ]);
    } catch (error) {
      if (error instanceof ApiError) {
        const fieldErrors = mapReadingLogValidationErrors(error.validationErrors);
        let mappedFieldMessage: string | undefined;

        (Object.entries(fieldErrors) as [ReadingLogFieldName, string][]).forEach(([field, message]) => {
          setError(field, { message });
          mappedFieldMessage ??= message;
        });

        if (error.status === 429) {
          setCooldownSeconds(error.retryAfterSeconds ?? 60);
        }

        if (!mappedFieldMessage) {
          setFormMessage(error.message);
        }

        AccessibilityInfo.announceForAccessibility(mappedFieldMessage ?? error.message);
        return;
      }

      const message = 'The reading could not be saved. Try again.';
      setFormMessage(message);
      AccessibilityInfo.announceForAccessibility(message);
    } finally {
      isSubmittingRef.current = false;
    }
  }

  const disabled = mutation.isPending || cooldownSeconds > 0;
  const submitLabel = cooldownSeconds > 0 ? `Try again in ${cooldownSeconds}s` : 'Log reading';

  function selectBook(book: BootstrapBook) {
    setValue('bookId', book.id, { shouldValidate: true, shouldDirty: true });
    setIsBookPickerOpen(false);
  }

  return (
    <KeyboardAvoidingView
      behavior={process.env.EXPO_OS === 'ios' ? 'padding' : 'height'}
      onLayout={keyboardFocusedScroll.onKeyboardLayoutChange}
      style={{ flex: 1, backgroundColor: colors.background }}
    >
      <ScrollView
        ref={scrollViewRef}
        testID="reading-log-form"
        keyboardShouldPersistTaps="handled"
        contentInsetAdjustmentBehavior="automatic"
        contentContainerStyle={{ gap: themeTokens.spacing.screen, padding: themeTokens.spacing.screen }}
      >
        {success ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Dismiss success message"
            accessibilityHint="Hides the confirmation that your reading was saved"
            accessibilityLiveRegion="polite"
            onPress={() => setSuccess(null)}
            style={{
              gap: 8,
              padding: themeTokens.spacing.section,
              borderWidth: 1,
              borderColor: colors.success,
              borderRadius: themeTokens.radius.card,
              borderCurve: 'continuous',
              backgroundColor: colors.successSubtle,
            }}
          >
            <Text selectable style={{ color: colors.success, fontSize: 18, fontWeight: '700' }}>
              Reading logged
            </Text>
            <Text selectable style={{ color: colors.text, fontSize: 16, lineHeight: 24 }}>
              {success.passage} recorded for {formatReadingDate(success.dateRead)}.
            </Text>
          </Pressable>
        ) : null}

        <View style={{ gap: 8 }}>
          <Text selectable style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>
            When did you read?
          </Text>
          <Controller
            control={control}
            name="dateRead"
            render={({ field: { onChange, value } }) => (
              <View
                style={{
                  flexDirection: 'row',
                  borderWidth: 1,
                  borderColor: colors.border,
                  borderRadius: themeTokens.radius.control,
                  overflow: 'hidden',
                }}
              >
                {[
                  { label: 'Today', date: bootstrap.today },
                  { label: 'Yesterday', date: bootstrap.yesterday },
                ].map((option, index) => {
                  const isSelected = value === option.date;

                  return (
                    <Pressable
                      key={option.date}
                      accessibilityRole="button"
                      accessibilityLabel={option.label}
                      accessibilityHint={`Uses the server date ${option.date}`}
                      accessibilityState={{ selected: isSelected }}
                      onPress={() => onChange(option.date)}
                      testID={
                        option.label === 'Today' ? 'reading-log-today' : 'reading-log-yesterday'
                      }
                      style={{
                        flex: 1,
                        minHeight: themeTokens.minimumTouchTarget,
                        alignItems: 'center',
                        justifyContent: 'center',
                        borderLeftWidth: index === 0 ? 0 : 1,
                        borderLeftColor: colors.border,
                        backgroundColor: isSelected ? colors.primarySubtle : colors.surface,
                      }}
                    >
                      <Text style={{ color: isSelected ? colors.primary : colors.text, fontWeight: '700' }}>
                        {option.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            )}
          />
          <Text selectable style={{ color: colors.mutedText, fontSize: 14 }}>
            Forgot to log? Choose yesterday.
          </Text>
          <FieldError message={errors.dateRead?.message} />
        </View>

        <View style={{ gap: 8 }}>
          <Text selectable style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>
            Bible book
          </Text>
          {recentBooks.length > 0 ? (
            <ScrollView
              horizontal
              keyboardShouldPersistTaps="handled"
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={{ gap: 8 }}
            >
              <Text
                selectable
                style={{
                  color: colors.mutedText,
                  fontSize: 13,
                  fontWeight: '600',
                  alignSelf: 'center',
                }}
              >
                Recent
              </Text>
              {recentBooks.map((book) => (
                <Pressable
                  key={book.id}
                  accessibilityRole="button"
                  accessibilityLabel={`Recent book ${book.name}`}
                  accessibilityHint={`Selects ${book.name} as the book you read`}
                  accessibilityState={{ selected: bookId === book.id }}
                  onPress={() => selectBook(book)}
                  style={{
                    minHeight: themeTokens.minimumTouchTarget,
                    justifyContent: 'center',
                    paddingHorizontal: 12,
                    borderWidth: 1,
                    borderColor: bookId === book.id ? colors.primary : colors.border,
                    borderRadius: 999,
                    backgroundColor: bookId === book.id ? colors.primarySubtle : colors.surface,
                  }}
                >
                  <Text style={{ color: colors.text, fontWeight: '600' }}>{book.name}</Text>
                </Pressable>
              ))}
            </ScrollView>
          ) : null}
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Bible book"
            accessibilityHint="Opens the list of Bible books"
            onPress={() => setIsBookPickerOpen(true)}
            testID="reading-log-book-picker"
            style={{
              minHeight: 50,
              justifyContent: 'center',
              paddingHorizontal: 14,
              borderWidth: 1,
              borderColor: errors.bookId ? colors.danger : colors.border,
              borderRadius: themeTokens.radius.control,
              backgroundColor: colors.input,
            }}
          >
            <Text style={{ color: selectedBook ? colors.text : colors.mutedText, fontSize: 17 }}>
              {selectedBook?.name ?? 'Select a book'}
            </Text>
          </Pressable>
          <FieldError message={errors.bookId?.message} />
        </View>

        <View style={{ flexDirection: 'row', gap: themeTokens.spacing.section }}>
          <Controller
            control={control}
            name="startChapter"
            render={({ field: { onBlur, onChange, value } }) => (
              <View style={{ flex: 1, gap: 7 }}>
                <Text style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>Start chapter</Text>
                <TextInput
                  accessibilityLabel="Start chapter"
                  accessibilityHint={
                    selectedBook
                      ? `Enter a chapter from 1 to ${selectedBook.chapters}`
                      : 'Select a book first, then enter the starting chapter'
                  }
                  inputMode="numeric"
                  keyboardType="number-pad"
                  onBlur={() => {
                    if (selectedBook) {
                      onChange(clampChapterInput(value, selectedBook.chapters));
                    }
                    onBlur();
                  }}
                  onChangeText={onChange}
                  testID="reading-log-start-chapter"
                  value={value}
                  style={{
                    minHeight: 50,
                    paddingHorizontal: 14,
                    color: colors.text,
                    fontSize: 17,
                    borderWidth: 1,
                    borderColor: errors.startChapter ? colors.danger : colors.border,
                    borderRadius: themeTokens.radius.control,
                    backgroundColor: colors.input,
                  }}
                />
                <FieldError message={errors.startChapter?.message} />
              </View>
            )}
          />
          <Controller
            control={control}
            name="endChapter"
            render={({ field: { onBlur, onChange, value } }) => (
              <View style={{ flex: 1, gap: 7 }}>
                <Text style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>
                  End chapter (optional)
                </Text>
                <TextInput
                  accessibilityLabel="End chapter"
                  accessibilityHint={
                    selectedBook
                      ? `Leave blank for one chapter, or enter a chapter up to ${selectedBook.chapters}`
                      : 'Select a book first, then enter an optional end chapter'
                  }
                  inputMode="numeric"
                  keyboardType="number-pad"
                  onBlur={() => {
                    if (selectedBook && value.trim() !== '') {
                      onChange(clampChapterInput(value, selectedBook.chapters));
                    }
                    onBlur();
                  }}
                  onChangeText={onChange}
                  testID="reading-log-end-chapter"
                  value={value}
                  style={{
                    minHeight: 50,
                    paddingHorizontal: 14,
                    color: colors.text,
                    fontSize: 17,
                    borderWidth: 1,
                    borderColor: errors.endChapter ? colors.danger : colors.border,
                    borderRadius: themeTokens.radius.control,
                    backgroundColor: colors.input,
                  }}
                />
                <FieldError message={errors.endChapter?.message} />
              </View>
            )}
          />
        </View>
        <Text selectable style={{ color: colors.mutedText, fontSize: 14 }}>
          {selectedBook ? chapterCountLabel(selectedBook) : 'Select a book to see available chapters.'}
        </Text>

        {areNotesVisible || errors.notesText ? (
          <Controller
            control={control}
            name="notesText"
            render={({ field: { onBlur, onChange, value } }) => (
              <View style={{ gap: 7 }}>
                <Text style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>
                  Note or reflection
                </Text>
                <TextInput
                  accessibilityLabel="Note or reflection"
                  accessibilityHint="Optional notes, limited to 1,000 characters"
                  multiline
                  onBlur={() => {
                    keyboardFocusedScroll.onFocusedFieldBlur();
                    onBlur();
                  }}
                  onChangeText={onChange}
                  onFocus={() => {
                    keyboardFocusedScroll.onFocusedFieldFocus();
                  }}
                  testID="reading-log-notes"
                  value={value}
                  style={{
                    minHeight: 96,
                    paddingHorizontal: 14,
                    paddingVertical: 12,
                    color: colors.text,
                    fontSize: 17,
                    lineHeight: 24,
                    borderWidth: 1,
                    borderColor: errors.notesText ? colors.danger : colors.border,
                    borderRadius: themeTokens.radius.control,
                    backgroundColor: colors.input,
                    textAlignVertical: 'top',
                  }}
                />
                <Text selectable style={{ color: colors.mutedText, fontSize: 13 }}>
                  {value.length}/1000
                </Text>
                <FieldError message={errors.notesText?.message} />
              </View>
            )}
          />
        ) : (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Add a note or reflection"
            accessibilityHint="Shows the optional notes field"
            onPress={() => setAreNotesVisible(true)}
            testID="reading-log-add-note"
            style={{ minHeight: themeTokens.minimumTouchTarget, justifyContent: 'center' }}
          >
            <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>
              Add a note or reflection
            </Text>
          </Pressable>
        )}

        {formMessage ? (
          <Text
            selectable
            accessibilityLiveRegion="assertive"
            style={{ color: colors.danger, fontSize: 15, lineHeight: 22 }}
          >
            {formMessage}
          </Text>
        ) : null}

        <ActionButton
          label={submitLabel}
          accessibilityLabel={submitLabel}
          accessibilityHint="Saves this reading on the server"
          disabled={disabled}
          isLoading={mutation.isPending}
          loadingLabel="Saving reading"
          onPress={() => {
            void handleSubmit(submit)();
          }}
          testID="reading-log-submit"
        />
      </ScrollView>

      <BookPickerModal
        books={bootstrap.books}
        selectedBookId={bookId}
        visible={isBookPickerOpen}
        onClose={() => setIsBookPickerOpen(false)}
        onSelect={selectBook}
      />
    </KeyboardAvoidingView>
  );
}

function preferredInitialBookId(bootstrap: BootstrapData): number | null {
  return createReadingLogDefaults(bootstrap).bookId;
}

export function ReadingLogForm() {
  const { colors } = useTheme();
  const request = useAuthenticatedApi();
  const { data, isPending, refetch } = useQuery({
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

  if (isPending) {
    return <LoadingState />;
  }

  if (!data) {
    return (
      <View
        accessibilityLiveRegion="polite"
        style={{
          flex: 1,
          alignItems: 'center',
          justifyContent: 'center',
          gap: themeTokens.spacing.section,
          padding: themeTokens.spacing.screen,
        }}
      >
        <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700', textAlign: 'center' }}>
          The reading form could not be loaded
        </Text>
        <Text selectable style={{ color: colors.mutedText, textAlign: 'center' }}>
          Check your connection and try again.
        </Text>
        <ActionButton
          label="Try again"
          accessibilityHint="Requests the latest books and dates"
          disabled={false}
          onPress={() => void refresh()}
        />
      </View>
    );
  }

  return <ReadingLogFields bootstrap={data} />;
}
