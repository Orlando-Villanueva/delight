import { z } from 'zod';

import type { ValidationErrors } from '@/api/api-error';
import type { BootstrapBook, BootstrapData } from '@/api/bootstrap';
import type { CreateReadingInput } from '@/api/reading-log';

export const readingLogSuccessDismissMs = 4_000;

export type ReadingLogFormValues = {
  bookId: number | null;
  startChapter: string;
  endChapter: string;
  dateRead: string;
  notesText: string;
};

export const readingLogFieldMap = {
  book_id: 'bookId',
  start_chapter: 'startChapter',
  end_chapter: 'endChapter',
  date_read: 'dateRead',
  notes_text: 'notesText',
} as const;

export type ReadingLogFieldName = (typeof readingLogFieldMap)[keyof typeof readingLogFieldMap];

export function preferredBookId(
  books: readonly BootstrapBook[],
  recentBookIds: readonly number[],
): number | null {
  const availableIds = new Set(books.map((book) => book.id));
  return recentBookIds.find((bookId) => availableIds.has(bookId)) ?? null;
}

export function createReadingLogDefaults(bootstrap: BootstrapData): ReadingLogFormValues {
  return {
    bookId: preferredBookId(bootstrap.books, bootstrap.recent_book_ids),
    startChapter: '',
    endChapter: '',
    dateRead: bootstrap.today,
    notesText: '',
  };
}

export function findBook(
  books: readonly BootstrapBook[],
  bookId: number | null,
): BootstrapBook | undefined {
  return bookId === null ? undefined : books.find((book) => book.id === bookId);
}

function parseChapter(value: string): number | null {
  const trimmed = value.trim();

  if (!/^[1-9]\d*$/.test(trimmed)) {
    return null;
  }

  return Number(trimmed);
}

export function clampChapterInput(value: string, maximum: number): string {
  const chapter = parseChapter(value);

  if (chapter === null) {
    return value;
  }

  return String(Math.min(maximum, chapter));
}

export function chaptersAfterBookChange(
  book: BootstrapBook | undefined,
  startChapter: string,
  endChapter: string,
): Pick<ReadingLogFormValues, 'startChapter' | 'endChapter'> {
  if (!book) {
    return { startChapter, endChapter };
  }

  const start = parseChapter(startChapter);
  const nextStart = start === null ? startChapter : String(Math.min(book.chapters, Math.max(1, start)));
  const end = parseChapter(endChapter);

  if (end === null) {
    return { startChapter: nextStart, endChapter: endChapter.trim() === '' ? '' : endChapter };
  }

  const parsedStart = parseChapter(nextStart);

  if (parsedStart !== null && (end < parsedStart || end > book.chapters)) {
    return { startChapter: nextStart, endChapter: '' };
  }

  return { startChapter: nextStart, endChapter: String(Math.min(book.chapters, end)) };
}

export function toCreateReadingInput(values: ReadingLogFormValues): CreateReadingInput {
  const endChapter = parseChapter(values.endChapter);

  return {
    book_id: values.bookId as number,
    start_chapter: parseChapter(values.startChapter) as number,
    end_chapter: endChapter,
    date_read: values.dateRead,
    notes_text: values.notesText.trim() === '' ? null : values.notesText.trim(),
  };
}

export function createReadingLogSchema(
  books: readonly BootstrapBook[],
  allowedDates: readonly [string, string],
) {
  const booksById = new Map(books.map((book) => [book.id, book]));

  return z.object({
    bookId: z
      .number('Select a Bible book.')
      .nullable()
      .refine((bookId) => bookId !== null && booksById.has(bookId), 'Select a Bible book.'),
    startChapter: z.string().trim().min(1, 'Enter a start chapter.'),
    endChapter: z.string(),
    dateRead: z.string().refine(
      (value) => allowedDates.includes(value),
      'Choose today or yesterday.',
    ),
    notesText: z.string().max(1000, 'The notes may not be greater than 1,000 characters.'),
  }).superRefine((values, context) => {
    if (values.bookId === null) {
      return;
    }

    const book = booksById.get(values.bookId);

    if (!book) {
      return;
    }

    const startChapter = parseChapter(values.startChapter);

    if (startChapter === null) {
      context.addIssue({
        code: 'custom',
        path: ['startChapter'],
        message: 'Enter a start chapter.',
      });
      return;
    }

    if (startChapter > book.chapters) {
      context.addIssue({
        code: 'custom',
        path: ['startChapter'],
        message: `${book.name} has ${book.chapters} chapters.`,
      });
    }

    if (values.endChapter.trim() === '') {
      return;
    }

    const endChapter = parseChapter(values.endChapter);

    if (endChapter === null) {
      context.addIssue({
        code: 'custom',
        path: ['endChapter'],
        message: 'Enter a valid end chapter.',
      });
      return;
    }

    if (endChapter < startChapter) {
      context.addIssue({
        code: 'custom',
        path: ['endChapter'],
        message: 'The end chapter must be greater than or equal to the start chapter.',
      });
    }

    if (endChapter > book.chapters) {
      context.addIssue({
        code: 'custom',
        path: ['endChapter'],
        message: `${book.name} has ${book.chapters} chapters.`,
      });
    }
  });
}

export function mapReadingLogValidationErrors(
  errors: ValidationErrors,
): Partial<Record<ReadingLogFieldName, string>> {
  const mapped: Partial<Record<ReadingLogFieldName, string>> = {};

  (Object.entries(readingLogFieldMap) as [keyof typeof readingLogFieldMap, ReadingLogFieldName][])
    .forEach(([serverField, formField]) => {
      const message = errors[serverField]?.[0];

      if (message) {
        mapped[formField] = message;
      }
    });

  return mapped;
}

export function formatReadingDate(date: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(`${date}T12:00:00`));
}

export function chapterCountLabel(book: BootstrapBook): string {
  const noun = book.chapters === 1 ? 'chapter' : 'chapters';

  return `${book.name} has ${book.chapters} ${noun}.`;
}

export function testamentLabel(testament: string): string {
  if (testament === 'new') {
    return 'New Testament';
  }

  if (testament === 'deuterocanonical') {
    return 'Deuterocanonical';
  }

  return 'Old Testament';
}

export function booksByTestament(books: readonly BootstrapBook[]): [string, BootstrapBook[]][] {
  const groups = new Map<string, BootstrapBook[]>();

  for (const book of books) {
    const group = groups.get(book.testament) ?? [];
    group.push(book);
    groups.set(book.testament, group);
  }

  const order = ['old', 'new', 'deuterocanonical'];

  return [...groups.entries()].sort((left, right) => {
    const leftIndex = order.indexOf(left[0]);
    const rightIndex = order.indexOf(right[0]);

    return (leftIndex === -1 ? order.length : leftIndex) - (rightIndex === -1 ? order.length : rightIndex);
  });
}
