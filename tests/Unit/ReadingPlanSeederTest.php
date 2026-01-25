<?php

use Database\Seeders\ReadingPlanSeeder;

/**
 * Helper to call private methods on the seeder.
 */
function callPrivateMethod(ReadingPlanSeeder $seeder, string $method, array $args = []): mixed
{
    $reflection = new ReflectionClass($seeder);
    $method = $reflection->getMethod($method);

    return $method->invokeArgs($seeder, $args);
}

beforeEach(function () {
    $this->seeder = new ReadingPlanSeeder;
});

describe('parseSegment', function () {
    it('handles book with chapter number', function () {
        $result = callPrivateMethod($this->seeder, 'parseSegment', ['Gen 1-3']);

        expect($result)->toHaveCount(3)
            ->and($result[0]['book_id'])->toBe(1)
            ->and($result[0]['book_name'])->toBe('Genesis')
            ->and($result[0]['chapter'])->toBe(1);
    });

    it('handles single-chapter book Obadiah without chapter number', function () {
        $result = callPrivateMethod($this->seeder, 'parseSegment', ['Oba']);

        expect($result)->toHaveCount(1)
            ->and($result[0]['book_id'])->toBe(31)
            ->and($result[0]['book_name'])->toBe('Obadiah')
            ->and($result[0]['chapter'])->toBe(1);
    });

    it('handles single-chapter book Jude without chapter number', function () {
        $result = callPrivateMethod($this->seeder, 'parseSegment', ['Jude']);

        expect($result)->toHaveCount(1)
            ->and($result[0]['book_id'])->toBe(65)
            ->and($result[0]['book_name'])->toBe('Jude')
            ->and($result[0]['chapter'])->toBe(1);
    });

    it('handles single-chapter book 2 John without chapter number', function () {
        $result = callPrivateMethod($this->seeder, 'parseSegment', ['2 Jn']);

        expect($result)->toHaveCount(1)
            ->and($result[0]['book_id'])->toBe(63)
            ->and($result[0]['book_name'])->toBe('2 John')
            ->and($result[0]['chapter'])->toBe(1);
    });

    it('handles single-chapter book 3 John without chapter number', function () {
        $result = callPrivateMethod($this->seeder, 'parseSegment', ['3 Jn']);

        expect($result)->toHaveCount(1)
            ->and($result[0]['book_id'])->toBe(64)
            ->and($result[0]['book_name'])->toBe('3 John')
            ->and($result[0]['chapter'])->toBe(1);
    });

    it('handles single-chapter book Philemon without chapter number', function () {
        $result = callPrivateMethod($this->seeder, 'parseSegment', ['Phlm']);

        expect($result)->toHaveCount(1)
            ->and($result[0]['book_id'])->toBe(57)
            ->and($result[0]['book_name'])->toBe('Philemon')
            ->and($result[0]['chapter'])->toBe(1);
    });
});

describe('parsePassage', function () {
    it('includes single-chapter books in mixed passage', function () {
        // Actual passage from chronological.csv line 361
        $result = callPrivateMethod($this->seeder, 'parsePassage', ['2 Jn; 3 Jn; Jude; Rev 1-2']);

        expect($result)->toHaveCount(5); // 2 John + 3 John + Jude + Rev 1 + Rev 2

        $bookIds = array_column($result, 'book_id');
        expect($bookIds)->toContain(63)  // 2 John
            ->toContain(64)  // 3 John
            ->toContain(65)  // Jude
            ->toContain(66); // Revelation
    });

    it('includes Obadiah in mixed passage', function () {
        // Actual passage from standard-canonical.csv line 274
        $result = callPrivateMethod($this->seeder, 'parsePassage', ['Amo 8-9; Oba; Jon 1']);

        expect($result)->toHaveCount(4); // Amos 8 + Amos 9 + Obadiah 1 + Jonah 1

        $bookIds = array_column($result, 'book_id');
        expect($bookIds)->toContain(30)  // Amos
            ->toContain(31)  // Obadiah
            ->toContain(32); // Jonah
    });
});
