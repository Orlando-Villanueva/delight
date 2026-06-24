export const authenticatedShell = () => ({
    sidebarCollapsed: false,
    sidebarUserToggled: false,
    compactSidebarQuery: null,
    currentSidebarPath: window.location.pathname,
    init() {
        this.compactSidebarQuery = window.matchMedia('(min-width: 1024px) and (max-width: 1279.98px)');

        const syncSidebarToViewport = () => {
            if (!this.sidebarUserToggled) {
                this.setSidebarCollapsed(this.compactSidebarQuery.matches);
            }
        };

        syncSidebarToViewport();

        if (typeof this.compactSidebarQuery.addEventListener === 'function') {
            this.compactSidebarQuery.addEventListener('change', () => syncSidebarToViewport());
        } else {
            this.compactSidebarQuery.addListener(() => syncSidebarToViewport());
        }

        const syncSidebarPath = () => {
            this.currentSidebarPath = window.location.pathname;
        };

        document.body.addEventListener('htmx:pushedIntoHistory', syncSidebarPath);
        document.body.addEventListener('htmx:historyRestore', syncSidebarPath);
        window.addEventListener('popstate', syncSidebarPath);
    },
    toggleSidebar() {
        this.sidebarUserToggled = true;
        this.setSidebarCollapsed(!this.sidebarCollapsed);
    },
    setSidebarCollapsed(shouldCollapse) {
        this.sidebarCollapsed = shouldCollapse;
    },
    isSidebarPathActive(targetPath, matchPrefix) {
        const normalizePath = (path) => path === '/' ? path : path.replace(/\/+$/, '');
        const currentPath = normalizePath(this.currentSidebarPath);
        const normalizedTargetPath = normalizePath(targetPath);

        if (!matchPrefix) {
            return currentPath === normalizedTargetPath;
        }

        return currentPath === normalizedTargetPath || currentPath.startsWith(`${normalizedTargetPath}/`);
    },
});
