import type { BootstrapBook, BootstrapData } from '@/api/bootstrap';
import {
  booksByTestament,
  chaptersAfterBookChange,
  clampChapterInput,
  createReadingLogDefaults,
  createReadingLogSchema,
  formatReadingDate,
  mapReadingLogValidationErrors,
  preferredBookId,
  testamentLabel,
  toCreateReadingInput,
} from '@/features/reading-log/form';

const john: BootstrapBook = { id: 43, name: 'John', chapters: 21, testament: 'new' };
const genesis: BootstrapBook = { id: 1, name: 'Genesis', chapters: 50, testament: 'old' };
const obadiah: BootstrapBook = { id: 31, name: 'Obadiah', chapters: 1, testament: 'old' };
const tobit: BootstrapBook = { id: 67, name: 'Tobit', chapters: 14, testament: 'deuterocanonical' };

function bootstrap(overrides: Partial<BootstrapData> = {}): BootstrapData {
  return {
    user: { id: 1, name: 'Reader', email: 'reader@example.com' },
    today: '2026-08-10',
    yesterday: '2026-08-09',
    books: [genesis, obadiah, john],
    recent_book_ids: [43, 1],
    has_read_today: false,
    current_streak: 0,
    longest_streak: 0,
    this_week_days: 0,
    this_month_days: 0,
    activity: [],
    ...overrides,
  };
}

describe('reading log form helpers', () => {
  it('formats the recorded date in the app locale', () => {
    const dateTimeFormatSpy = jest.spyOn(Intl, 'DateTimeFormat');

    formatReadingDate('2026-08-10');

    expect(dateTimeFormatSpy).toHaveBeenCalledWith('en-CA', { dateStyle: 'medium' });
    dateTimeFormatSpy.mockRestore();
  });

  it('prefers the first recent book that is still in the allowed list', () => {
    expect(preferredBookId([genesis, john], [67, 43, 1])).toBe(43);
    expect(preferredBookId([genesis, john], [67])).toBeNull();
    expect(createReadingLogDefaults(bootstrap()).bookId).toBe(43);
    expect(createReadingLogDefaults(bootstrap()).startChapter).toBe('');
    expect(createReadingLogDefaults(bootstrap({ recent_book_ids: [] })).bookId).toBeNull();
  });

  it('clamps chapter fields to the selected book and clears an invalid range', () => {
    expect(clampChapterInput('99', 21)).toBe('21');
    expect(clampChapterInput('0', 21)).toBe('0');
    expect(chaptersAfterBookChange(obadiah, '21', '21')).toEqual({
      startChapter: '1',
      endChapter: '',
    });
    expect(chaptersAfterBookChange(john, '3', '5')).toEqual({
      startChapter: '3',
      endChapter: '5',
    });
  });

  it('submits only the selected server date and omits a blank end chapter or notes', () => {
    expect(toCreateReadingInput({
      bookId: 43,
      startChapter: '3',
      endChapter: '',
      dateRead: '2026-08-09',
      notesText: '  ',
    })).toEqual({
      book_id: 43,
      start_chapter: 3,
      end_chapter: null,
      date_read: '2026-08-09',
      notes_text: null,
    });
  });

  it('maps Laravel field errors onto the native controls', () => {
    expect(mapReadingLogValidationErrors({
      start_chapter: ['The start chapter is invalid for the selected book.'],
      notes_text: ['The notes may not be greater than 1,000 characters.'],
    })).toEqual({
      startChapter: 'The start chapter is invalid for the selected book.',
      notesText: 'The notes may not be greater than 1,000 characters.',
    });
  });

  it('keeps client validation aligned with book chapter counts and server dates', () => {
    const schema = createReadingLogSchema([john], ['2026-08-10', '2026-08-09']);

    expect(schema.safeParse({
      bookId: 43,
      startChapter: '22',
      endChapter: '',
      dateRead: '2026-08-10',
      notesText: '',
    }).success).toBe(false);
    expect(schema.safeParse({
      bookId: 43,
      startChapter: '5',
      endChapter: '3',
      dateRead: '2026-08-10',
      notesText: '',
    }).success).toBe(false);
    expect(schema.safeParse({
      bookId: 43,
      startChapter: '3',
      endChapter: '',
      dateRead: '2026-08-08',
      notesText: '',
    }).success).toBe(false);
    expect(schema.safeParse({
      bookId: 43,
      startChapter: '3',
      endChapter: '4',
      dateRead: '2026-08-10',
      notesText: '',
    }).success).toBe(true);
  });

  it('groups books by testament in Old, New, Deuterocanonical order', () => {
    expect(testamentLabel('new')).toBe('New Testament');
    expect(booksByTestament([john, tobit, genesis]).map(([testament]) => testament)).toEqual([
      'old',
      'new',
      'deuterocanonical',
    ]);
  });
});
