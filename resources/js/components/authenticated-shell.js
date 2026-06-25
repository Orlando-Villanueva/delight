export const authenticatedShell = () => ({
    sidebarCollapsed: false,
    sidebarUserToggled: false,
    compactSidebarQuery: null,
    currentSidebarPath: globalThis.location.pathname,
    init() {
        this.compactSidebarQuery = globalThis.matchMedia('(min-width: 1024px) and (max-width: 1279.98px)');

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
            this.currentSidebarPath = globalThis.location.pathname;
        };

        document.body.addEventListener('htmx:pushedIntoHistory', syncSidebarPath);
        document.body.addEventListener('htmx:historyRestore', syncSidebarPath);
        globalThis.addEventListener('popstate', syncSidebarPath);
    },
    toggleSidebar() {
        this.sidebarUserToggled = true;
        this.setSidebarCollapsed(!this.sidebarCollapsed);
    },
    setSidebarCollapsed(shouldCollapse) {
        this.sidebarCollapsed = shouldCollapse;
    },
    isSidebarPathActive(targetPath, matchPrefix) {
        const normalizePath = (path) => {
            if (path === '/') {
                return path;
            }

            let normalizedPath = path;

            while (normalizedPath.endsWith('/')) {
                normalizedPath = normalizedPath.slice(0, -1);
            }

            return normalizedPath;
        };

        const currentPath = normalizePath(this.currentSidebarPath);
        const normalizedTargetPath = normalizePath(targetPath);

        if (!matchPrefix || normalizedTargetPath === '/') {
            return currentPath === normalizedTargetPath;
        }

        return currentPath === normalizedTargetPath || currentPath.startsWith(`${normalizedTargetPath}/`);
    },
});
