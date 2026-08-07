import { render } from '@testing-library/react-native';

import HistoryScreen from '@/app/(tabs)/history';
import HomeScreen from '@/app/(tabs)/home';
import LogScreen from '@/app/(tabs)/log';

describe('mobile tab screens', () => {
  it.each([
    [HomeScreen, 'Your reading dashboard will live here.'],
    [LogScreen, 'The focused reading form will live here.'],
    [HistoryScreen, 'Your reading history will live here.'],
  ])('renders its scoped placeholder', async (Screen, message) => {
    const { getByText } = await render(<Screen />);

    expect(getByText(message)).toBeOnTheScreen();
  });
});
