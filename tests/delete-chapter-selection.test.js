import test from 'node:test';
import assert from 'node:assert/strict';
import { deleteChapterSelectionState } from '../resources/js/components/delete-chapter-selection.js';

test('failed removal preserves the selection and provides recovery guidance', () => {
    const state = deleteChapterSelectionState(['101', '102', '103']);
    state.selectedChapters = ['102'];
    state.afterRequest({ detail: { successful: false, xhr: { status: 409 } } });
    assert.deepEqual(state.selectedChapters, ['102']);
    assert.match(state.error, /Refresh History and open it again/);
    assert.equal(state.buttonLabel(), 'Delete 1 Chapter');

    state.afterRequest({ detail: { successful: false, xhr: { status: 422 } } });
    assert.deepEqual(state.selectedChapters, ['102']);
    assert.match(state.error, /Select valid chapters/);

    state.afterRequest({ detail: { successful: false, xhr: { status: 0 } } });
    assert.deepEqual(state.selectedChapters, ['102']);
    assert.equal(state.error, 'The chapters could not be removed. Try again.');
});

test('successful removal clears selection and error state', () => {
    const state = deleteChapterSelectionState(['101', '102']);
    state.selectAll();
    assert.equal(state.buttonLabel(), 'Delete 2 Chapters');
    state.afterRequest({ detail: { successful: false, xhr: { status: 500 } } });
    assert.ok(state.error);
    state.afterRequest({ detail: { successful: true } });
    assert.deepEqual(state.selectedChapters, []);
    assert.equal(state.error, null);
    assert.equal(state.buttonLabel(), 'Delete Selected');
});
