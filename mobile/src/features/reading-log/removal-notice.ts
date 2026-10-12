export function readingRemovalNotice(count: number, split = false): string {
  const subject = count === 1 ? 'Chapter' : `${count} chapters`;
  return split
    ? `${subject} removed. The remaining chapters are now shown as separate readings in History.`
    : `${subject} removed from History.`;
}
