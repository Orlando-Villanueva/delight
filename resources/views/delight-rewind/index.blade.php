<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Delight Rewind {{ $stats['year'] }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,600,700,900" rel="stylesheet" />

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <!-- html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- Styles -->
    @vite(['resources/css/app.css'])

    <style>
        [x-cloak] { display: none !important; }
        .slide-content {
            background: linear-gradient(135deg, #111827 0%, #000000 100%);
        }
    </style>
</head>
<body class="bg-black text-white h-screen w-screen overflow-hidden font-sans antialiased" x-data="rewind()">

    <!-- Progress Bar -->
    <div class="fixed top-0 left-0 w-full h-1 flex z-50">
        <template x-for="i in totalSlides">
            <div class="h-full flex-1 bg-gray-800 mx-0.5 rounded-full overflow-hidden">
                <div class="h-full bg-white transition-all duration-300 ease-out"
                     :style="`width: ${i - 1 < currentSlide ? '100%' : (i - 1 === currentSlide ? '100%' : '0%')}`"></div>
            </div>
        </template>
    </div>

    <!-- Close Button -->
    <a href="{{ route('dashboard') }}" class="fixed top-4 right-4 z-50 text-gray-400 hover:text-white transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </a>

    <!-- Slides Container -->
    <div class="relative w-full h-full touch-none"
         @click="handleTap($event)"
         @touchstart="handleTouchStart($event)"
         @touchend="handleTouchEnd($event)">

        <!-- Slide 1: Intro -->
        <div x-show="currentSlide === 0"
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-105"
             class="absolute inset-0 flex flex-col items-center justify-center p-8 slide-content text-center pb-32"
             id="slide-0">
            <div class="text-xl font-medium text-purple-400 mb-4 animate-pulse">Delight Rewind</div>
            <h1 class="text-5xl md:text-7xl font-black mb-6 tracking-tight">Your {{ $stats['year'] }}<br>in Scripture</h1>
            <p class="text-xl text-gray-300">Ready to see your journey?</p>
            <div class="mt-12 text-sm text-gray-500 uppercase tracking-widest">Tap to continue</div>
        </div>

        <!-- Slide 2: Total Chapters -->
        <div x-show="currentSlide === 1" x-cloak
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 translate-y-10"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-10"
             class="absolute inset-0 flex flex-col items-center justify-center p-8 slide-content text-center pb-32"
             id="slide-1">
            <h2 class="text-3xl font-bold mb-8 text-blue-400">You immersed yourself in</h2>
            <div class="text-8xl font-black mb-4 text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500">
                {{ number_format($stats['total_chapters']) }}
            </div>
            <div class="text-2xl font-bold">Chapters</div>
            <div class="mt-12 text-xl text-gray-300">
                Across <span class="text-white font-bold">{{ $stats['total_books_read'] }}</span> different books.
            </div>
        </div>

        <!-- Slide 3: Completion -->
        <div x-show="currentSlide === 2" x-cloak
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             class="absolute inset-0 flex flex-col items-center justify-center p-8 slide-content text-center pb-32"
             id="slide-2">
            <h2 class="text-2xl font-bold mb-12 text-green-400">That's equivalent to</h2>

            <div class="relative w-64 h-64 mb-8 flex items-center justify-center">
                <svg class="w-full h-full transform -rotate-90">
                    <circle cx="128" cy="128" r="120" stroke="#374151" stroke-width="12" fill="none" />
                    <circle cx="128" cy="128" r="120" stroke="#10B981" stroke-width="12" fill="none"
                            stroke-dasharray="{{ 2 * pi() * 120 }}"
                            stroke-dashoffset="{{ 2 * pi() * 120 * (1 - $stats['completion_percentage'] / 100) }}"
                            class="transition-all duration-1000 ease-out" />
                </svg>
                <div class="absolute text-5xl font-black">
                    {{ $stats['completion_percentage'] }}%
                </div>
            </div>

            <div class="text-xl font-bold">of the entire Bible</div>
        </div>

        <!-- Slide 4: Streak -->
        <div x-show="currentSlide === 3" x-cloak
             x-transition:enter="transition ease-out duration-500"
             class="absolute inset-0 flex flex-col items-center justify-center p-8 slide-content text-center pb-32"
             id="slide-3">
            <div class="mb-8 text-6xl">🔥</div>
            <h2 class="text-2xl font-bold mb-4 text-orange-400">Your Longest Streak</h2>
            <div class="text-8xl font-black mb-6">{{ $stats['longest_streak'] }}</div>
            <div class="text-2xl font-bold text-gray-200">Days in a row</div>
            <p class="mt-8 text-gray-400 max-w-md">
                Consistency builds character. Keep the fire burning!
            </p>
        </div>

        <!-- Slide 5: Favorites -->
        <div x-show="currentSlide === 4" x-cloak
             x-transition:enter="transition ease-out duration-500"
             class="absolute inset-0 flex flex-col items-center justify-center p-8 slide-content text-center pb-32"
             id="slide-4">
            <h2 class="text-3xl font-bold mb-8 text-pink-400">Your Favorites</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full max-w-2xl text-left">
                @if($stats['most_read_book'])
                <div class="bg-gray-800 bg-opacity-50 p-6 rounded-2xl border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Most Read Book</div>
                    <div class="text-2xl font-bold text-white">{{ $stats['most_read_book']['name'] }}</div>
                </div>
                @endif

                @if($stats['most_read_testament'])
                <div class="bg-gray-800 bg-opacity-50 p-6 rounded-2xl border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Top Testament</div>
                    <div class="text-2xl font-bold text-white">{{ $stats['most_read_testament']['name'] }}</div>
                </div>
                @endif

                @if($stats['most_read_genre'])
                <div class="bg-gray-800 bg-opacity-50 p-6 rounded-2xl border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Top Genre</div>
                    <div class="text-2xl font-bold text-white">{{ $stats['most_read_genre'] }}</div>
                </div>
                @endif

                @if($stats['most_active_day'])
                <div class="bg-gray-800 bg-opacity-50 p-6 rounded-2xl border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Most Active Day</div>
                    <div class="text-2xl font-bold text-white">{{ $stats['most_active_day'] }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Slide 6: Summary -->
        <div x-show="currentSlide === 5" x-cloak
             x-transition:enter="transition ease-out duration-500"
             class="absolute inset-0 flex flex-col items-center justify-center p-8 slide-content text-center pb-32"
             id="slide-5">
            <div class="bg-gray-900 border border-gray-700 p-8 rounded-3xl shadow-2xl max-w-md w-full relative overflow-hidden">
                <!-- Watermark -->
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 20 20"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/></svg>
                </div>

                <h2 class="text-2xl font-bold mb-6 text-left">My {{ $stats['year'] }} in Delight</h2>

                <div class="space-y-4 text-left">
                    <div class="flex justify-between items-center border-b border-gray-800 pb-2">
                        <span class="text-gray-400">Chapters</span>
                        <span class="font-bold text-xl">{{ number_format($stats['total_chapters']) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-800 pb-2">
                        <span class="text-gray-400">Completion</span>
                        <span class="font-bold text-xl">{{ $stats['completion_percentage'] }}%</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-800 pb-2">
                        <span class="text-gray-400">Top Book</span>
                        <span class="font-bold text-xl">{{ $stats['most_read_book'] ? $stats['most_read_book']['name'] : 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-2">
                        <span class="text-gray-400">Longest Streak</span>
                        <span class="font-bold text-xl">{{ $stats['longest_streak'] }} days</span>
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t border-gray-800 text-center text-xs text-gray-500">
                    delight.app
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="showToast" x-transition.opacity.duration.300ms
         class="fixed bottom-24 left-1/2 transform -translate-x-1/2 z-50 bg-white text-black px-6 py-2 rounded-full shadow-lg font-bold flex items-center gap-2"
         x-cloak>
        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Copied to clipboard!
    </div>

    <!-- Floating Footer Controls -->
    <div class="fixed bottom-10 inset-x-0 flex justify-center gap-4 z-50">
        <button @click="share" class="px-6 py-3 bg-white text-black font-bold rounded-full hover:bg-gray-200 transition flex items-center gap-2 shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            Share
        </button>
        <button @click="download" class="px-6 py-3 bg-gray-800 text-white font-bold rounded-full hover:bg-gray-700 transition flex items-center gap-2 shadow-lg border border-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Save
        </button>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('rewind', () => ({
                currentSlide: 0,
                totalSlides: 6,
                showToast: false,

                next() {
                    if (this.currentSlide < this.totalSlides - 1) {
                        this.currentSlide++;
                    } else {
                        // Loop or end?
                    }
                },
                prev() {
                    if (this.currentSlide > 0) {
                        this.currentSlide--;
                    }
                },
                handleTap(e) {
                    const width = window.innerWidth;
                    const x = e.clientX || (e.changedTouches ? e.changedTouches[0].clientX : 0);

                    // Don't trigger if clicking buttons
                    if (e.target.closest('button') || e.target.closest('a')) return;

                    if (x > width / 3) {
                        this.next();
                    } else {
                        this.prev();
                    }
                },
                handleTouchStart(e) {
                    // Simple logic for now, using click/tap mostly
                },
                handleTouchEnd(e) {
                    // Can implement swipe logic here later
                },

                async share() {
                    const slideElement = document.getElementById('slide-' + this.currentSlide);

                    try {
                        const canvas = await html2canvas(slideElement, {
                            backgroundColor: null, // Capture background gradient
                            scale: 2,
                            ignoreElements: (element) => {
                                return element.tagName === 'BUTTON' || element.tagName === 'A'; // Don't capture buttons
                            }
                        });

                        canvas.toBlob(async (blob) => {
                            if (!blob) {
                                console.error('Canvas to Blob failed');
                                return;
                            }

                            // 1. Try Native Share (Mobile)
                            if (navigator.share && navigator.canShare && navigator.canShare({ files: [new File([blob], 'test.png', { type: 'image/png' })] })) {
                                const file = new File([blob], 'delight-rewind.png', { type: 'image/png' });
                                await navigator.share({
                                    title: 'My Delight Rewind',
                                    text: 'Check out my Bible reading journey this year!',
                                    files: [file]
                                });
                            }
                            // 2. Try Clipboard (Desktop)
                            else if (typeof ClipboardItem !== 'undefined' && navigator.clipboard && navigator.clipboard.write) {
                                try {
                                    await navigator.clipboard.write([
                                        new ClipboardItem({ 'image/png': blob })
                                    ]);
                                    this.showToast = true;
                                    setTimeout(() => this.showToast = false, 3000);
                                } catch (clipboardErr) {
                                    console.warn('Clipboard write failed, falling back to download', clipboardErr);
                                    this.download();
                                }
                            }
                            // 3. Fallback to Download
                            else {
                                this.download();
                            }
                        });
                    } catch (err) {
                        console.error('Share generation failed:', err);
                    }
                },

                async download() {
                    const slideElement = document.getElementById('slide-' + this.currentSlide);
                    try {
                        const canvas = await html2canvas(slideElement, {
                            backgroundColor: null, // Transparent to capture gradient
                            scale: 2,
                            ignoreElements: (element) => {
                                return element.tagName === 'BUTTON' || element.tagName === 'A'; // Don't capture buttons
                            }
                        });
                        const link = document.createElement('a');
                        link.download = 'delight-rewind-' + this.currentSlide + '.png';
                        link.href = canvas.toDataURL();
                        link.click();
                    } catch (err) {
                        console.error('Download failed:', err);
                        alert('Could not generate image. Please try taking a screenshot.');
                    }
                }
            }))
        })
    </script>
</body>
</html>
