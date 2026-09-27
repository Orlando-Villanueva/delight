<?php

namespace App\View\Components\Bible;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\View\Component;

class BookCompletionGrid extends Component
{
    /**
     * @param  array<string, mixed>  $progress
     */
    public function __construct(public array $progress, public string $testament = 'Old') {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.bible.book-completion-grid', [
            'oldData' => $this->progress['old_testament'],
            'newData' => $this->progress['new_testament'],
            'deuterocanonicalData' => $this->progress['deuterocanonical'],
            'overallData' => Arr::only($this->progress, [
                'bible_completions',
                'first_coverage_percent',
                'next_completion_progress_percent',
            ]),
        ]);
    }
}
