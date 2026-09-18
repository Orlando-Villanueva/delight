import { requireNativeModule } from 'expo-modules-core';
import { Platform } from 'react-native';

const playStorePackageName = 'com.android.vending';

export type AndroidInstallerSource = 'play' | 'non-play' | 'unknown';

type InstallerSourceModule = {
  getInstallerPackageName: () => string | null;
};

export function classifyInstallerPackage(packageName: string | null): AndroidInstallerSource {
  if (!packageName) {
    return 'unknown';
  }

  return packageName === playStorePackageName ? 'play' : 'non-play';
}

export function getAndroidInstallerSource(): AndroidInstallerSource {
  if (Platform.OS !== 'android') {
    return 'unknown';
  }

  try {
    const module = requireNativeModule<InstallerSourceModule>('DelightInstallerSource');

    return classifyInstallerPackage(module.getInstallerPackageName());
  } catch {
    return 'unknown';
  }
}
