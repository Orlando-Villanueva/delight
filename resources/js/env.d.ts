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
