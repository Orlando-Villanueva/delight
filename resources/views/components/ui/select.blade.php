@props([
    'name' => '',
    'label' => null,
    'placeholder' => null,
    'value' => '',
    'required' => false,
    'disabled' => false,
    'error' => null,
    'help' => null,
    'id' => null,
    'options' => []
])

@php
    $selectId = $id ?? $name ?? 'select_' . uniqid();
    $hasError = !empty($error);
    
    $selectClasses = 'form-input';
    if ($hasError) {
        $selectClasses .= ' border-destructive focus:ring-destructive';
    }
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if($label)
        <label for="{{ $selectId }}" class="form-label {{ $required ? 'after:content-[\'*\'] after:ml-0.5 after:text-destructive' : '' }}">
            {{ $label }}
        </label>
    @endif
    
    <div class="relative" x-data="{ isOpen: false }">
        <select 
            id="{{ $selectId }}"
            name="{{ $name }}"
            class="{{ $selectClasses }} pr-12 appearance-none shadow-none"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            @if($hasError) aria-invalid="true" aria-describedby="{{ $selectId }}-error" @endif
            @if($help && !$hasError) aria-describedby="{{ $selectId }}-help" @endif
            @click="isOpen = !isOpen"
            @blur="isOpen = false"
        >
        @if($placeholder)
            <option value="" {{ old($name, $value) === '' ? 'selected' : '' }}>{{ $placeholder }}</option>
        @endif
        
        @if(!empty($options))
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" {{ old($name, $value) == $optionValue ? 'selected' : '' }}>
                    {{ $optionLabel }}
                </option>
            @endforeach
        @else
            {{ $slot }}
        @endif
        </select>
        
        <!-- Custom dropdown arrow -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" 
                 :class="{ 'rotate-180': isOpen }"
                 fill="none" 
                 stroke="currentColor" 
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" 
                      stroke-linejoin="round" 
                      stroke-width="2" 
                      d="M19 9l-7 7-7-7">
                </path>
            </svg>
        </div>
    </div>
    
    @if($hasError)
        <p id="{{ $selectId }}-error" class="form-error" role="alert">
            {{ $error }}
        </p>
    @elseif($help)
        <p id="{{ $selectId }}-help" class="text-sm text-muted-foreground mt-1">
            {{ $help }}
        </p>
    @endif
</div>
