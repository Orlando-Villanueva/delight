@props([
    'name' => 'book_id',
    'label' => 'Bible Book',
    'placeholder' => 'Select a book...',
    'value' => '',
    'required' => true,
    'disabled' => false,
    'error' => null,
    'help' => null,
    'id' => null,
    'books' => [],
    'showTestamentGroups' => true
])

@php
    $selectId = $id ?? $name ?? 'book_selector_' . uniqid();
    $hasError = !empty($error);
    
    $selectClasses = 'form-input';
    if ($hasError) {
        $selectClasses .= ' border-destructive focus:ring-destructive';
    }
    
    // Group books by testament
    $oldTestament = collect($books)->where('testament', 'old')->values();
    $newTestament = collect($books)->where('testament', 'new')->values();
    $deuterocanonicalBooks = collect($books)->where('testament', 'deuterocanonical')->values();
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @if($label)
        <label for="{{ $selectId }}" class="form-label {{ $required ? 'after:content-[\'*\'] after:ml-0.5 after:text-destructive' : '' }}">
            📚 {{ $label }}
        </label>
    @endif
    
    <select 
        id="{{ $selectId }}"
        name="{{ $name }}"
        class="{{ $selectClasses }}"
        {{ $required ? 'required' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        @if($hasError) aria-invalid="true" aria-describedby="{{ $selectId }}-error" @endif
        @if($help && !$hasError) aria-describedby="{{ $selectId }}-help" @endif
        {{ $attributes->except(['class']) }}
    >
        @if($placeholder)
            <option value="" {{ old($name, $value) === '' ? 'selected' : '' }}>{{ $placeholder }}</option>
        @endif
        
        @if($showTestamentGroups && $oldTestament->isNotEmpty() && $newTestament->isNotEmpty())
            {{-- Old Testament Group --}}
            <optgroup label="📜 Old Testament ({{ $oldTestament->count() }} books)">
                @foreach($oldTestament as $book)
                    <option value="{{ $book['id'] }}" 
                            data-chapters="{{ $book['chapters'] }}"
                            data-testament="old"
                            {{ old($name, $value) == $book['id'] ? 'selected' : '' }}>
                        {{ $book['name'] }} ({{ $book['chapters'] }} chapters)
                    </option>
                @endforeach
            </optgroup>

            @if($deuterocanonicalBooks->isNotEmpty())
                <optgroup label="📚 Deuterocanonical ({{ $deuterocanonicalBooks->count() }} books)">
                    @foreach($deuterocanonicalBooks as $book)
                        <option value="{{ $book['id'] }}"
                                data-chapters="{{ $book['chapters'] }}"
                                data-testament="deuterocanonical"
                                {{ old($name, $value) == $book['id'] ? 'selected' : '' }}>
                            {{ $book['name'] }} ({{ $book['chapters'] }} chapters)
                        </option>
                    @endforeach
                </optgroup>
            @endif
            
            {{-- New Testament Group --}}
            <optgroup label="✝️ New Testament ({{ $newTestament->count() }} books)">
                @foreach($newTestament as $book)
                    <option value="{{ $book['id'] }}" 
                            data-chapters="{{ $book['chapters'] }}"
                            data-testament="new"
                            {{ old($name, $value) == $book['id'] ? 'selected' : '' }}>
                        {{ $book['name'] }} ({{ $book['chapters'] }} chapters)
                    </option>
                @endforeach
            </optgroup>
        @else
            {{-- Fallback: All books without grouping --}}
            @foreach($books as $book)
                <option value="{{ $book['id'] }}" 
                        data-chapters="{{ $book['chapters'] }}"
                        data-testament="{{ $book['testament'] ?? '' }}"
                        {{ old($name, $value) == $book['id'] ? 'selected' : '' }}>
                    {{ $book['name'] }} ({{ $book['chapters'] }} chapters)
                </option>
            @endforeach
        @endif
    </select>
    
    @if($hasError)
        <p id="{{ $selectId }}-error" class="form-error" role="alert">
            {{ $error }}
        </p>
    @elseif($help)
        <p id="{{ $selectId }}-help" class="text-sm text-muted-foreground mt-1">
            {{ $help }}
        </p>
    @else
        <p class="text-xs text-gray-500 mt-1">
            💡 Books are organized by testament
        </p>
    @endif
</div>
