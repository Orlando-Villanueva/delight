import assert from 'node:assert/strict';
import test from 'node:test';
import { readerCopy } from '../resources/js/reader-copy.ts';

test('reader copy normalizes French regional locales and uses French count forms', () => {
    const copy = readerCopy('fr-CA');

    assert.equal(copy.readingHistory, 'Historique de lecture');
    assert.equal(copy.chapters(0), '0 chapitre');
    assert.equal(copy.chapters(1), '1 chapitre');
    assert.equal(copy.chapters(2), '2 chapitres');
});

test('reader copy falls back to English for unsupported locales', () => {
    const copy = readerCopy('en-US');

    assert.equal(copy.readingHistory, 'Reading History');
    assert.equal(copy.days(1), '1 day');
    assert.equal(copy.days(3), '3 days');
});


test('streak unit sits beside the existing prominent count without repeating it', () => {
    assert.equal(readerCopy('en').inARow(1), 'day in a row');
    assert.equal(readerCopy('en').inARow(7), 'days in a row');
    assert.equal(readerCopy('fr').inARow(1), 'jour d’affilée');
    assert.equal(readerCopy('fr').inARow(7), 'jours d’affilée');
});
