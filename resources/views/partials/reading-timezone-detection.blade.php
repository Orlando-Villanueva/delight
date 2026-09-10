<script data-reload-after-detection="{{ ($reloadAfterDetection ?? true) ? 'true' : 'false' }}">
    (() => {
        try {
            const reloadAfterDetection = document.currentScript?.dataset.reloadAfterDetection === 'true';
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!timezone) return;

            const cookie = 'reading_timezone_report=' + encodeURIComponent(timezone);
            if (document.cookie.split('; ').includes(cookie)) return;

            document.cookie = cookie + '; Path=/; Max-Age=3600; SameSite=Lax' +
                (location.protocol === 'https:' ? '; Secure' : '');

            // Reload before rendering the reading form, but never loop when cookies are blocked.
            if (reloadAfterDetection && document.cookie.split('; ').includes(cookie)) {
                location.replace(location.href);
            }
        } catch {
            // Keep the server-rendered fallback available when detection is unsupported.
        }
    })();
</script>
