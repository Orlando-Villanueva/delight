export type BootstrapActivity = {
  date: string;
  count: number;
};

export type BootstrapData = {
  user: {
    id: number;
    name: string;
    email: string;
  };
  today: string;
  yesterday: string;
  books: {
    id: number;
    name: string;
  }[];
  recent_book_ids: number[];
  has_read_today: boolean;
  current_streak: number;
  longest_streak: number;
  this_week_days: number;
  this_month_days: number;
  activity: BootstrapActivity[];
};

type BootstrapResponse = {
  data: BootstrapData;
};

export type HomeDashboardData = Pick<
  BootstrapData,
  | 'today'
  | 'has_read_today'
  | 'current_streak'
  | 'longest_streak'
  | 'this_week_days'
  | 'this_month_days'
  | 'activity'
>;

export function mapBootstrapToHomeDashboard(response: BootstrapResponse): HomeDashboardData {
  const { data } = response;

  return {
    today: data.today,
    has_read_today: data.has_read_today,
    current_streak: data.current_streak,
    longest_streak: data.longest_streak,
    this_week_days: data.this_week_days,
    this_month_days: data.this_month_days,
    activity: data.activity,
  };
}

export async function fetchBootstrap(
  request: <T>(path: string) => Promise<T>,
): Promise<HomeDashboardData> {
  return mapBootstrapToHomeDashboard(await request<BootstrapResponse>('/api/v1/bootstrap'));
}
