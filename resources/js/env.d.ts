interface Window {
    htmx?: {
        swap(target: string, content: string, options: { swapStyle: string }): void;
    };
}
