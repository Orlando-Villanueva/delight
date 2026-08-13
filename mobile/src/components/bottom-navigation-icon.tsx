import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { ColorValue } from 'react-native';

export const bottomNavigationIcons = {
  home: 'chart-line',
  log: 'plus',
  history: 'history',
} as const;

type BottomNavigationIconProps = {
  color: ColorValue;
  name: (typeof bottomNavigationIcons)[keyof typeof bottomNavigationIcons];
  size: number;
  testID?: string;
};

export function BottomNavigationIcon({ color, name, size, testID }: Readonly<BottomNavigationIconProps>) {
  return (
    <MaterialCommunityIcons
      accessible={false}
      color={color}
      name={name}
      size={size}
      testID={testID}
    />
  );
}
