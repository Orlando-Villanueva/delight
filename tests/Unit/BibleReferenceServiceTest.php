<?php

use App\Services\BibleReferenceService;

beforeEach(function () {
    $this->service = app(BibleReferenceService::class);
});

describe('BibleReferenceService', function () {

    describe('getBibleBook()', function () {
        it('can get a book by ID', function () {
            $book = $this->service->getBibleBook(1);

            expect($book)->not->toBeNull()
                ->and($book['id'])->toBe(1)
                ->and($book['name'])->toBe('Genesis')
                ->and($book['chapters'])->toBe(50)
                ->and($book['testament'])->toBe('old');
        });

        it('can get a book by English name', function () {
            $book = $this->service->getBibleBook('Genesis');

            expect($book)->not->toBeNull()
                ->and($book['id'])->toBe(1)
                ->and($book['name'])->toBe('Genesis');
        });

        it('can get a book by French name', function () {
            $book = $this->service->getBibleBook('Genèse', 'fr');

            expect($book)->not->toBeNull()
                ->and($book['id'])->toBe(1)
                ->and($book['name'])->toBe('Genèse');
        });

        it('returns null for invalid book ID', function () {
            expect($this->service->getBibleBook(99))->toBeNull();
        });

        it('returns null for invalid book name', function () {
            expect($this->service->getBibleBook('InvalidBook'))->toBeNull();
        });
    });

    describe('validateBibleReference()', function () {
        it('validates correct Bible references', function () {
            expect($this->service->validateBibleReference(1, 1))->toBeTrue() // Genesis 1
                ->and($this->service->validateBibleReference(1, 50))->toBeTrue() // Genesis 50
                ->and($this->service->validateBibleReference(66, 22))->toBeTrue(); // Revelation 22
        });

        it('invalidates incorrect Bible references', function () {
            expect($this->service->validateBibleReference(1, 51))->toBeFalse() // Genesis 51 (doesn't exist)
                ->and($this->service->validateBibleReference(99, 1))->toBeFalse() // Invalid book
                ->and($this->service->validateBibleReference(1, 0))->toBeFalse(); // Chapter 0
        });
    });

    describe('formatBibleReference()', function () {
        it('formats references in English', function () {
            $formatted = $this->service->formatBibleReference(1, 1);
            expect($formatted)->toBe('Genesis 1');
        });

        it('formats references in French', function () {
            $formatted = $this->service->formatBibleReference(1, 1, 'fr');
            expect($formatted)->toBe('Genèse 1');
        });

        it('throws exception for invalid book ID', function () {
            expect(fn () => $this->service->formatBibleReference(99, 1))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('getBookChapterCount()', function () {
        it('returns correct chapter counts', function () {
            expect($this->service->getBookChapterCount(1))->toBe(50) // Genesis
                ->and($this->service->getBookChapterCount(19))->toBe(150) // Psalms
                ->and($this->service->getBookChapterCount(66))->toBe(22); // Revelation
        });

        it('throws exception for invalid book ID', function () {
            expect(fn () => $this->service->getBookChapterCount(99))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('listBibleBooks()', function () {
        it('returns all 66 books by default', function () {
            $books = $this->service->listBibleBooks();
            expect($books)->toHaveCount(66);
        });

        it('can include the Catholic deuterocanonical books when requested', function () {
            $books = $this->service->listBibleBooks(includeDeuterocanonical: true);

            expect($books)->toHaveCount(73)
                ->and(collect($books)->pluck('name'))->toContain('Tobit', 'Judith', 'Wisdom', 'Sirach', 'Baruch', '1 Maccabees', '2 Maccabees');
        });

        it('filters deuterocanonical books into their own group', function () {
            $books = $this->service->listBibleBooks('deuterocanonical', includeDeuterocanonical: true);

            expect($books)->toHaveCount(7)
                ->and($books[0]['name'])->toBe('Tobit')
                ->and($books[0]['testament'])->toBe('deuterocanonical');
        });

        it('filters by Old Testament', function () {
            $books = $this->service->listBibleBooks('old');
            expect($books)->toHaveCount(39);
        });

        it('filters by New Testament', function () {
            $books = $this->service->listBibleBooks('new');
            expect($books)->toHaveCount(27);
        });

        it('returns books in correct locale', function () {
            $books = $this->service->listBibleBooks(null, 'fr');
            expect($books[0]['name'])->toBe('Genèse');
        });
    });

    describe('validateBookId()', function () {
        it('validates correct book IDs', function () {
            expect($this->service->validateBookId(1))->toBeTrue()
                ->and($this->service->validateBookId(33))->toBeTrue()
                ->and($this->service->validateBookId(66))->toBeTrue();
        });

        it('invalidates incorrect book IDs', function () {
            expect($this->service->validateBookId(0))->toBeFalse()
                ->and($this->service->validateBookId(67))->toBeFalse()
                ->and($this->service->validateBookId(-1))->toBeFalse();
        });

        it('validates deuterocanonical book IDs only when requested', function () {
            expect($this->service->validateBookId(67))->toBeFalse()
                ->and($this->service->validateBookId(67, includeDeuterocanonical: true))->toBeTrue()
                ->and($this->service->validateBookId(73, includeDeuterocanonical: true))->toBeTrue()
                ->and($this->service->validateBookId(74, includeDeuterocanonical: true))->toBeFalse();
        });
    });

    describe('validateChapterNumber()', function () {
        it('validates correct chapter numbers', function () {
            expect($this->service->validateChapterNumber(1, 1))->toBeTrue()
                ->and($this->service->validateChapterNumber(1, 50))->toBeTrue()
                ->and($this->service->validateChapterNumber(19, 150))->toBeTrue(); // Psalms
        });

        it('invalidates incorrect chapter numbers', function () {
            expect($this->service->validateChapterNumber(1, 0))->toBeFalse()
                ->and($this->service->validateChapterNumber(1, 51))->toBeFalse()
                ->and($this->service->validateChapterNumber(99, 1))->toBeFalse(); // Invalid book
        });

        it('uses Catholic-integrated chapter counts for Esther and Daniel only when requested', function () {
            expect($this->service->validateChapterNumber(17, 11))->toBeFalse()
                ->and($this->service->validateChapterNumber(17, 16, includeDeuterocanonical: true))->toBeTrue()
                ->and($this->service->validateChapterNumber(17, 17, includeDeuterocanonical: true))->toBeFalse()
                ->and($this->service->validateChapterNumber(27, 13))->toBeFalse()
                ->and($this->service->validateChapterNumber(27, 14, includeDeuterocanonical: true))->toBeTrue()
                ->and($this->service->validateChapterNumber(27, 15, includeDeuterocanonical: true))->toBeFalse();
        });

        it('does not expose Daniel additions as extra standalone chapters', function () {
            expect($this->service->getBookChapterCount(27))->toBe(12)
                ->and($this->service->getBookChapterCount(27, includeDeuterocanonical: true))->toBe(14);
        });

        it('keeps Esther at ten chapters by default and sixteen in Catholic numbering', function () {
            expect($this->service->getBookChapterCount(17))->toBe(10)
                ->and($this->service->getBookChapterCount(17, includeDeuterocanonical: true))->toBe(16);
        });
    });

    describe('getLocalizedBookName()', function () {
        it('returns English names by default', function () {
            expect($this->service->getLocalizedBookName(1))->toBe('Genesis')
                ->and($this->service->getLocalizedBookName(43))->toBe('John');
        });

        it('returns French names when requested', function () {
            expect($this->service->getLocalizedBookName(1, 'fr'))->toBe('Genèse')
                ->and($this->service->getLocalizedBookName(43, 'fr'))->toBe('Jean');
        });

        it('falls back to default locale for unsupported locales', function () {
            expect($this->service->getLocalizedBookName(1, 'es'))->toBe('Genesis');
        });
    });

    describe('parseBibleReference()', function () {
        it('parses valid Bible references', function () {
            $parsed = $this->service->parseBibleReference('Genesis 1');

            expect($parsed)->not->toBeNull()
                ->and($parsed['book_id'])->toBe(1)
                ->and($parsed['chapter'])->toBe(1)
                ->and($parsed['verse'])->toBeNull()
                ->and($parsed['formatted'])->toBe('Genesis 1');
        });

        it('parses references with verses', function () {
            $parsed = $this->service->parseBibleReference('John 3:16');

            expect($parsed)->not->toBeNull()
                ->and($parsed['book_id'])->toBe(43)
                ->and($parsed['chapter'])->toBe(3)
                ->and($parsed['verse'])->toBe(16);
        });

        it('handles French book names', function () {
            $parsed = $this->service->parseBibleReference('Jean 3', 'fr');

            expect($parsed)->not->toBeNull()
                ->and($parsed['book_id'])->toBe(43)
                ->and($parsed['book_name'])->toBe('Jean');
        });

        it('returns null for invalid references', function () {
            expect($this->service->parseBibleReference('InvalidBook 1'))->toBeNull()
                ->and($this->service->parseBibleReference('Genesis 99'))->toBeNull()
                ->and($this->service->parseBibleReference('Invalid format'))->toBeNull();
        });
    });

    describe('testament operations', function () {
        it('gets testament information', function () {
            $oldTestament = $this->service->getTestament('old');

            expect($oldTestament)->not->toBeNull()
                ->and($oldTestament['name'])->toBe('Old Testament')
                ->and($oldTestament['range'])->toBe([1, 39])
                ->and($oldTestament['books_count'])->toBe(39);

            $newTestament = $this->service->getTestament('new');

            expect($newTestament)->not->toBeNull()
                ->and($newTestament['name'])->toBe('New Testament')
                ->and($newTestament['range'])->toBe([40, 66])
                ->and($newTestament['books_count'])->toBe(27);
        });

        it('lists all testaments', function () {
            $testaments = $this->service->listTestaments();

            expect($testaments)->toHaveCount(2)
                ->and($testaments)->toHaveKeys(['old', 'new']);
        });

        it('lists the deuterocanonical testament only when requested', function () {
            expect($this->service->listTestaments())->toHaveCount(2)
                ->and($this->service->listTestaments(includeDeuterocanonical: true))->toHaveCount(3)
                ->and($this->service->listTestaments(includeDeuterocanonical: true))->toHaveKeys(['old', 'new', 'deuterocanonical']);
        });

        it('gets books in testament', function () {
            $oldTestamentBooks = $this->service->getBooksInTestament('old');
            $newTestamentBooks = $this->service->getBooksInTestament('new');

            expect($oldTestamentBooks)->toHaveCount(39)
                ->and($newTestamentBooks)->toHaveCount(27);
        });
    });

    describe('utility methods', function () {
        it('returns supported locales', function () {
            $locales = $this->service->getSupportedLocales();
            expect($locales)->toContain('en', 'fr');
        });

        it('checks locale support', function () {
            expect($this->service->isLocaleSupported('en'))->toBeTrue()
                ->and($this->service->isLocaleSupported('fr'))->toBeTrue()
                ->and($this->service->isLocaleSupported('es'))->toBeFalse();
        });

        it('gets random books', function () {
            $randomBook = $this->service->getRandomBook();
            expect($randomBook)->toHaveKeys(['id', 'name', 'chapters', 'testament']);

            $randomOldBook = $this->service->getRandomBook('old');
            expect($randomOldBook['testament'])->toBe('old');

            $randomNewBook = $this->service->getRandomBook('new');
            expect($randomNewBook['testament'])->toBe('new');

            $randomDeuterocanonicalBook = $this->service->getRandomBook('deuterocanonical', includeDeuterocanonical: true);
            expect($randomDeuterocanonicalBook['testament'])->toBe('deuterocanonical');
        });

        it('gets adjacent books', function () {
            $nextBook = $this->service->getAdjacentBook(1, 'next');
            expect($nextBook['id'])->toBe(2);

            $prevBook = $this->service->getAdjacentBook(2, 'previous');
            expect($prevBook['id'])->toBe(1);

            // Edge cases
            expect($this->service->getAdjacentBook(66, 'next'))->toBeNull();
            expect($this->service->getAdjacentBook(66, 'next', includeDeuterocanonical: true)['id'])->toBe(67);
            expect($this->service->getAdjacentBook(67, 'previous', includeDeuterocanonical: true)['id'])->toBe(66);
            expect($this->service->getAdjacentBook(1, 'previous'))->toBeNull();
        });
    });
});
