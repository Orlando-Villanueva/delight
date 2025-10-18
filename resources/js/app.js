import './bootstrap';

// Note: Flowbite CSS is imported via app.css, JS is loaded via CDN in layout

// Initialize Flowbite on page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initFlowbite === 'function') {
        window.initFlowbite();
    }
});

// Re-initialize Flowbite after HTMX content swaps
document.body.addEventListener('htmx:afterSwap', function() {
    // Flowbite CDN provides window.initFlowbite()
    if (typeof window.initFlowbite === 'function') {
        window.initFlowbite();
    }
});
