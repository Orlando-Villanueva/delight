import type { AuthenticatedRequestOptions } from '@/auth/auth-context';
import { mapReadingGroup, type ReadingGroupResponse, type ReadingHistoryGroup } from '@/api/reading-history';

export type CreateReadingInput = {
  book_id: number;
  start_chapter: number;
  end_chapter: number | null;
  date_read: string;
  notes_text: string | null;
};

export type CreatedReading = ReadingHistoryGroup;

type CreateReadingResponse = {
  data: ReadingGroupResponse;
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
  return mapReadingGroup(response.data);
}
