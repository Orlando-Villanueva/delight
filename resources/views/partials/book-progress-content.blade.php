{{-- Remove @props since this is an include, not a component --}}
{{-- Variables: $testament, $processedBooks, $testamentProgress, $completedBooks, $inProgressBooks, $notStartedBooks --}}

<!-- Progress Section -->
<div class="space-y-3 mb-6">
    <!-- Testament Label and Percentage -->
    <div class="flex items-center justify-between">
        <span class="text-base font-medium text-gray-700 dark:text-gray-300 leading-[1.5]">
            {{ $testament }} Testament
        </span>
        <span class="text-lg lg:text-xl font-bold text-primary-500 leading-[1.5]">
            {{ $testamentProgress }}%
        </span>
    </div>
    
    <!-- Progress Bar -->
    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-3 overflow-hidden">
        <div
            class="bg-primary-500 h-3 rounded-full transition-[width] duration-500 ease-in-out"
            @isset($progressRef)
                x-ref="{{ $progressRef }}"
            @endisset
            style="width: 0%"
        ></div>
    </div>
    
    <!-- Stats Summary -->
    <div class="grid grid-cols-3 gap-2 text-center text-sm">
        <div class="bg-success-500/10 dark:bg-success-500/20 rounded-lg py-2 px-1">
            <div class="font-bold text-success-500 text-base lg:text-lg leading-[1.5]">
                {{ $completedBooks }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400 leading-tight">completed</div>
        </div>
        <div class="bg-primary-500/10 dark:bg-primary-500/20 rounded-lg py-2 px-1">
            <div class="font-bold text-primary-500 text-base lg:text-lg leading-[1.5]">
                {{ $inProgressBooks }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400 leading-tight">in progress</div>
        </div>
        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg py-2 px-1">
            <div class="font-bold text-gray-600 dark:text-gray-400 text-base lg:text-lg leading-[1.5]">
                {{ $notStartedBooks }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400 leading-tight">not started</div>
        </div>
    </div>
</div>

<!-- Books Grid -->
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
    @foreach($processedBooks as $book)
        @php
            $statusClasses = match($book['status']) {
                'completed' => 'bg-success-500 text-white border-success-500 dark:bg-success-600 dark:border-success-600',
                'in-progress' => 'bg-primary-500 text-white border-primary-500',
                default => 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-primary-500/30 dark:hover:border-primary-500/50'
            };
        @endphp
        
        <div class="relative p-3 rounded-lg border-2 text-center transition-all duration-200 shadow-sm hover:shadow-md cursor-pointer group {{ $statusClasses }}"
             title="{{ $book['name'] }}: {{ $book['chapters_read'] }}/{{ $book['chapter_count'] }} chapters ({{ $book['percentage'] }}%)">
            
            <!-- Book Name -->
            <div class="font-semibold text-sm mb-1 truncate leading-[1.5]">
                {{ $book['name'] }}
            </div>
            
            <!-- Progress Percentage -->
            <div class="text-sm opacity-90 mb-2 leading-[1.5]">
                {{ $book['percentage'] }}%
            </div>
            
            <!-- Mini Progress Bar for In-Progress Books -->
            @if($book['status'] === 'in-progress')
                <div class="w-full bg-white/30 rounded-full h-1 overflow-hidden">
                    <div class="bg-white h-1 transition-all duration-300" 
                         style="width: {{ $book['percentage'] }}%"></div>
                </div>
            @endif
            
            <!-- Completion Badge -->
            @if($book['status'] === 'completed')
                <div class="absolute -top-1 -right-1 w-4 h-4 bg-success-500 rounded-full flex items-center justify-center">
                    <div class="w-2 h-2 bg-white rounded-full"></div>
                </div>
            @endif
        </div>
    @endforeach
</div>

<!-- Legend -->
<div class="flex items-center justify-center space-x-6 mt-6 pt-4 border-t border-gray-300 dark:border-gray-600">
    <div class="flex items-center space-x-2">
        <div class="w-3 h-3 bg-success-500 rounded border-2 border-success-500"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">Completed</span>
    </div>
    <div class="flex items-center space-x-2">
        <div class="w-3 h-3 bg-primary-500 rounded border-2 border-primary-500"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">In Progress</span>
    </div>
    <div class="flex items-center space-x-2">
        <div class="w-3 h-3 bg-white dark:bg-gray-800 rounded border-2 border-gray-300 dark:border-gray-600"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">Not Started</span>
    </div>
</div> 