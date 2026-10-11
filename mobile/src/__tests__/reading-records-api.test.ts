import { fetchReadingHistoryPage, type ReadingGroupResponse } from '@/api/reading-history';
import { createReadingLog } from '@/api/reading-log';

const group: ReadingGroupResponse = {
  log_ids: [92, 15],
  book: { id: 43, name: 'John' },
  start_chapter: 1,
  end_chapter: 2,
  passage: 'John 1-2',
  notes_text: 'First chapter note.',
  date_read: '2026-10-08',
  logged_at: '2026-10-08T19:00:00.000000Z',
};

const records: NonNullable<ReadingGroupResponse['records']> = [
  {
    id: 92,
    chapter: 1,
    passage: 'John 1',
    notes_text: 'First chapter note.',
    date_read: '2026-10-08',
    logged_at: '2026-10-08T19:00:00.000000Z',
  },
  {
    id: 15,
    chapter: 2,
    passage: 'John 2',
    notes_text: null,
    date_read: '2026-10-08',
    logged_at: '2026-10-08T19:00:00.000000Z',
  },
];

const expectedRecords = [
  {
    id: 92,
    chapter: 1,
    passage: 'John 1',
    notesText: 'First chapter note.',
    dateRead: '2026-10-08',
    loggedAt: '2026-10-08T19:00:00.000000Z',
  },
  {
    id: 15,
    chapter: 2,
    passage: 'John 2',
    notesText: null,
    dateRead: '2026-10-08',
    loggedAt: '2026-10-08T19:00:00.000000Z',
  },
];

const input = {
  book_id: 43,
  start_chapter: 1,
  end_chapter: 2,
  date_read: '2026-10-08',
  notes_text: 'First chapter note.',
};

describe('reading record API mapping', () => {
  it('keeps authoritative chapter identities and distinct notes in history', async () => {
    const request = jest.fn().mockResolvedValue({
      data: [{ date_read: '2026-10-08', groups: [{ ...group, records }] }],
      meta: { current_page: 2, last_page: 3 },
    });

    const page = await fetchReadingHistoryPage(request, 2);

    expect(request).toHaveBeenCalledWith('/api/v1/reading-logs?page=2');
    expect(page.currentPage).toBe(2);
    expect(page.lastPage).toBe(3);
    expect(page.days[0].groups[0].records).toEqual(expectedRecords);
    expect(page.days[0].groups[0].notesText).toBe('First chapter note.');
  });

  it('returns the same record details after creating a reading', async () => {
    const request = jest.fn().mockResolvedValue({ data: { ...group, records } });

    const reading = await createReadingLog(request, input);

    expect(request).toHaveBeenCalledWith('/api/v1/reading-logs', { method: 'POST', body: input });
    expect(reading.records).toEqual(expectedRecords);
  });

  it('marks record details unavailable in older history responses without inventing chapter mappings', async () => {
    const request = jest.fn().mockResolvedValue({
      data: [{ date_read: '2026-10-08', groups: [group] }],
      meta: { current_page: 1, last_page: 1 },
    });

    const page = await fetchReadingHistoryPage(request, 1);

    expect(page.days[0].groups[0]).toMatchObject({
      logIds: [92, 15],
      passage: 'John 1-2',
      records: null,
    });
  });

  it('marks record details unavailable in older creation responses', async () => {
    const request = jest.fn().mockResolvedValue({ data: group });

    const reading = await createReadingLog(request, input);

    expect(reading).toMatchObject({ logIds: [92, 15], passage: 'John 1-2', records: null });
  });
});
