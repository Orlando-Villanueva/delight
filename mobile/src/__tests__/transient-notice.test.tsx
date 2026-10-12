import { act, fireEvent, render, screen } from '@testing-library/react-native';
import { AccessibilityInfo, Platform } from 'react-native';

import { TransientNotice } from '@/components/transient-notice';

describe('temporary History feedback', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.spyOn(AccessibilityInfo, 'isReduceMotionEnabled').mockResolvedValue(true);
  });
  afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it('dismisses automatically after the reading time', async () => {
    const dismiss = jest.fn();
    await render(<TransientNotice message="Chapter removed" onDismiss={dismiss} />);
    await act(() => jest.advanceTimersByTime(4999));
    expect(dismiss).not.toHaveBeenCalled();
    await act(() => jest.advanceTimersByTime(1));
    expect(dismiss).toHaveBeenCalledTimes(1);
  });

  it('allows immediate dismissal and gives replacement messages their full duration', async () => {
    const dismiss = jest.fn();
    const view = await render(<TransientNotice message="Chapter removed" onDismiss={dismiss} />);
    await act(() => jest.advanceTimersByTime(4000));
    await view.rerender(<TransientNotice message="Remaining readings split" duration={8000} onDismiss={dismiss} />);
    await act(() => jest.advanceTimersByTime(7999));
    expect(dismiss).not.toHaveBeenCalled();
    await fireEvent.press(screen.getByRole('button', { name: 'Dismiss notification' }));
    expect(dismiss).toHaveBeenCalledTimes(1);
    await view.unmount();
    await act(() => jest.advanceTimersByTime(10000));
    expect(dismiss).toHaveBeenCalledTimes(1);
  });

  it('respects the Android accessibility timeout preference', async () => {
    jest.replaceProperty(Platform, 'OS', 'android');
    jest.spyOn(AccessibilityInfo, 'getRecommendedTimeoutMillis').mockResolvedValue(20000);
    const announce = jest.spyOn(AccessibilityInfo, 'announceForAccessibility').mockClear();
    const dismiss = jest.fn();
    await render(<TransientNotice message="Chapter removed" onDismiss={dismiss} />);
    expect(screen.getByRole('alert')).toHaveTextContent('Chapter removed');
    expect(screen.getByRole('alert')).toHaveProp('accessibilityLiveRegion', 'polite');
    expect(announce).not.toHaveBeenCalled();
    await act(() => jest.advanceTimersByTime(19999));
    expect(dismiss).not.toHaveBeenCalled();
    await act(() => jest.advanceTimersByTime(1));
    expect(dismiss).toHaveBeenCalledTimes(1);
  });
});
