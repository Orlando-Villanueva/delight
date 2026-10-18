# Vue component structure

- In Vue single-file components (`*.vue`), place the `<template>` block before all `<script>` blocks. Place any `<style>` blocks after the scripts.
- Within scripts, use one blank line after the imports, between logical groups of declarations, and before each top-level function, watcher, or lifecycle hook. Keep related declarations together without a blank line between every variable; follow the spacing in `pages/Settings.vue`.
- Prefer root-based `@/...` imports for first-party modules in Vue components and browser entry modules. The Laravel Vite plugin maps `@` to `resources/js`; for example, use `@/components/BrowserReminders.vue` instead of `../components/BrowserReminders.vue`. Keep package imports unchanged. Shared JavaScript modules executed directly by Node may use relative imports because Node does not resolve the Vite alias.
