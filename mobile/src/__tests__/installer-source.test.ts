import {
  classifyInstallerPackage,
} from '@/native/installer-source';

describe('Android installer source', () => {
  it('recognizes Google Play as the Play installer', () => {
    expect(classifyInstallerPackage('com.android.vending')).toBe('play');
  });

  it('recognizes another installer as a direct-download candidate', () => {
    expect(classifyInstallerPackage('com.google.android.packageinstaller')).toBe('non-play');
  });

  it('fails closed when Android does not report an installer', () => {
    expect(classifyInstallerPackage(null)).toBe('unknown');
  });
});
