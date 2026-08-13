import { useCallback, useEffect, useRef, type RefObject } from 'react';
import { Keyboard, type ScrollView } from 'react-native';

export function useKeyboardFocusedScroll(scrollViewRef: RefObject<ScrollView | null>) {
  const isFocusedRef = useRef(false);

  const scrollFocusedFieldIntoView = useCallback(() => {
    requestAnimationFrame(() => {
      scrollViewRef.current?.scrollToEnd({ animated: true });
    });
  }, [scrollViewRef]);

  useEffect(() => {
    const subscription = Keyboard.addListener('keyboardDidShow', () => {
      if (isFocusedRef.current) {
        scrollFocusedFieldIntoView();
      }
    });

    return () => subscription.remove();
  }, [scrollFocusedFieldIntoView]);

  return {
    onFocusedFieldBlur: () => {
      isFocusedRef.current = false;
    },
    onFocusedFieldFocus: () => {
      isFocusedRef.current = true;
      scrollFocusedFieldIntoView();
    },
    onKeyboardLayoutChange: () => {
      if (isFocusedRef.current) {
        scrollFocusedFieldIntoView();
      }
    },
  };
}
