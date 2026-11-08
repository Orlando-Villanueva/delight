type ReadingConfettiOptions = { particleCount: number; spread: number; origin: { y: number }; disableForReducedMotion: boolean };

type ReadingConfetti = {
    (options: ReadingConfettiOptions): void;
    reset(): void;
};

interface Window {
    confetti?: ReadingConfetti & {
        create(canvas: HTMLCanvasElement, options: { resize: boolean }): ReadingConfetti;
    };
}

declare module 'canvas-confetti' {
    const confetti: NonNullable<Window['confetti']>;
    export default confetti;
}
