document.addEventListener('click', (event) => {
    for (const menu of document.querySelectorAll('[data-language-menu][open]')) {
        if (event.target instanceof Node && !menu.contains(event.target)) menu.open = false;
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    for (const menu of document.querySelectorAll('[data-language-menu][open]')) {
        menu.open = false;
        menu.querySelector('summary')?.focus();
    }
});
