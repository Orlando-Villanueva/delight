import './bootstrap';

const applyFlowbiteBackdropPatch = () => {
    if (typeof window === 'undefined' || !window.Modal || window.Modal.__backdropPatched) {
        return;
    }

    const originalCreateBackdrop = window.Modal.prototype._createBackdrop;

    if (typeof originalCreateBackdrop !== 'function') {
        return;
    }

    window.Modal.prototype._createBackdrop = function patchedCreateBackdrop() {
        if (this._options && typeof this._options.backdropClasses === 'string') {
            const classes = this._options.backdropClasses
                .split(' ')
                .filter((cls) => cls.length > 0 && !/^z-/.test(cls));

            classes.push('z-stack-backdrop');
            this._options.backdropClasses = classes.join(' ');
        }

        originalCreateBackdrop.call(this);

        if (this._backdropEl) {
            this._backdropEl.classList.remove('z-40');
            this._backdropEl.classList.add('z-stack-backdrop');
        }
    };

    window.Modal.__backdropPatched = true;
};

const initFlowbiteWithPatches = () => {
    applyFlowbiteBackdropPatch();

    if (typeof window.initFlowbite === 'function') {
        window.initFlowbite();
    }
};

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initFlowbiteWithPatches();

        document.body.addEventListener('htmx:afterSwap', initFlowbiteWithPatches);
    });
}
