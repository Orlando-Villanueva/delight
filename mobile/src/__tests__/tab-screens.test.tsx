import { render } from '@testing-library/react-native';

import LogScreen from '@/app/(tabs)/log';

describe('mobile tab screens', () => {
  it.each([
    [LogScreen, 'The focused reading form will live here.'],
  ])('renders its scoped placeholder', async (Screen, message) => {
    const { getByText } = await render(<Screen />);

    expect(getByText(message)).toBeOnTheScreen();
  });
});
