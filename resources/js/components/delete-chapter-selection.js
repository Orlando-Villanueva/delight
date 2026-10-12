export function deleteChapterSelectionState(allChapterIds) {
    return {
        allChapterIds,
        selectedChapters: [],
        error: null,
        selectAll() {
            this.selectedChapters = [...this.allChapterIds];
        },
        deselectAll() {
            this.selectedChapters = [];
            this.error = null;
        },
        buttonLabel() {
            if (this.selectedChapters.length === 0) {
                return 'Delete Selected';
            }
            const suffix = this.selectedChapters.length === 1 ? 'Chapter' : 'Chapters';
            return `Delete ${this.selectedChapters.length} ${suffix}`;
        },
        afterRequest(event) {
            if (event.detail.successful) {
                this.deselectAll();
                return;
            }
            const status = event.detail.xhr?.status;
            this.error = 'The chapters could not be removed. Try again.';
            if (status === 404 || status === 409) {
                this.error = 'This reading has changed. Refresh History and open it again.';
            } else if (status === 401 || status === 419) {
                this.error = 'Your session has expired. Refresh the page and sign in again.';
            } else if (status === 403) {
                this.error = 'You cannot remove chapters from this reading.';
            } else if (status === 422) {
                this.error = 'Select valid chapters from this reading, or refresh History and open it again.';
            }
        },
    };
}
