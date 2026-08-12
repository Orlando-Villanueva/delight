import type { AuthenticatedRequestOptions } from '@/auth/auth-context';

export type CreateReadingInput = {
  book_id: number;
  start_chapter: number;
  end_chapter: number | null;
  date_read: string;
  notes_text: string | null;
};

export type CreatedReading = {
  logIds: number[];
  book: {
    id: number;
    name: string;
  };
  startChapter: number;
  endChapter: number | null;
  passage: string;
  notesText: string | null;
  dateRead: string;
  loggedAt: string | null;
};

type CreateReadingResponse = {
  data: {
    log_ids: number[];
    book: { id: number; name: string };
    start_chapter: number;
    end_chapter: number | null;
    passage: string;
    notes_text: string | null;
    date_read: string;
    logged_at: string | null;
  };
};

type AuthenticatedApi = <T>(path: string, options?: AuthenticatedRequestOptions) => Promise<T>;

export async function createReadingLog(
  request: AuthenticatedApi,
  input: CreateReadingInput,
): Promise<CreatedReading> {
  const response = await request<CreateReadingResponse>('/api/v1/reading-logs', {
    method: 'POST',
    body: input,
  });
  const reading = response.data;

  return {
    logIds: reading.log_ids,
    book: reading.book,
    startChapter: reading.start_chapter,
    endChapter: reading.end_chapter,
    passage: reading.passage,
    notesText: reading.notes_text,
    dateRead: reading.date_read,
    loggedAt: reading.logged_at,
  };
}