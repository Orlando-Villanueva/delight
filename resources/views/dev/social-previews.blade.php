<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Social Preview Generator</title>
    @vite(['resources/css/app.css'])
    <script src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.min.js"></script>
</head>

<body class="bg-gray-100 min-h-screen p-8">

    <div class="max-w-7xl mx-auto">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Social Preview Generator</h1>
                <p class="text-gray-500 mt-1">Generate OG images for announcements pages</p>
            </div>
            <button onclick="downloadAll()"
                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-medium">
                Download All
            </button>
        </div>

        <div class="space-y-12">

            <!-- Updates Page Preview -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="font-semibold text-gray-900">Product Updates Page</h2>
                        <p class="text-sm text-gray-500">social-updates.png (1200×630)</p>
                    </div>
                    <button onclick="download('preview-updates', 'social-updates')"
                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                        Download
                    </button>
                </div>

                <div id="preview-updates"
                    class="w-[1200px] h-[630px] relative overflow-hidden border border-gray-200 rounded-lg"
                    style="transform: scale(0.5); transform-origin: top left; margin-bottom: -315px;">
                    <!-- Gradient background matching brand -->
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-indigo-50"></div>

                    <!-- Subtle radial glow -->
                    <div
                        class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent">
                    </div>

                    <!-- Content -->
                    <div class="relative z-10 h-full flex flex-col items-center justify-center">
                        <img src="{{ asset('images/logo-512.png') }}" class="w-28 h-28 mb-8" alt="Delight Logo">

                        <h1 class="text-7xl font-bold text-gray-900 mb-4">
                            What's New in Delight
                        </h1>
                        <p class="text-2xl text-gray-500 font-medium tracking-wide">
                            Latest Features & Improvements
                        </p>
                    </div>
                </div>
            </div>

            <!-- Article Preview -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="font-semibold text-gray-900">Individual Article</h2>
                        <p class="text-sm text-gray-500">social-article.png (1200×630)</p>
                    </div>
                    <button onclick="download('preview-article', 'social-article')"
                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                        Download
                    </button>
                </div>

                <div id="preview-article"
                    class="w-[1200px] h-[630px] relative overflow-hidden border border-gray-200 rounded-lg"
                    style="transform: scale(0.5); transform-origin: top left; margin-bottom: -315px;">
                    <!-- Gradient background matching brand -->
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-blue-50"></div>

                    <!-- Subtle radial glow -->
                    <div
                        class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-indigo-100/40 via-transparent to-transparent">
                    </div>

                    <!-- Content -->
                    <div class="relative z-10 h-full flex flex-col items-center justify-center">
                        <img src="{{ asset('images/logo-512.png') }}" class="w-24 h-24 mb-8" alt="Delight Logo">

                        <h1 class="text-7xl font-bold text-gray-900">
                            New from Delight
                        </h1>
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-8 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-amber-800 text-sm">
                <strong>Note:</strong> After downloading, move the files to <code
                    class="bg-amber-100 px-1 rounded">public/images/</code> to replace the existing social preview
                images.
            </p>
        </div>
    </div>

    <script>
        async function download(id, filename) {
            const element = document.getElementById(id);

            // Reset transform for accurate capture
            const originalTransform = element.style.transform;
            const originalMargin = element.style.marginBottom;
            element.style.transform = 'none';
            element.style.marginBottom = '0';

            try {
                const dataUrl = await htmlToImage.toPng(element, {
                    width: 1200,
                    height: 630,
                    pixelRatio: 1,
                    backgroundColor: '#ffffff'
                });

                const link = document.createElement('a');
                link.download = filename + '.png';
                link.href = dataUrl;
                link.click();
            } catch (error) {
                console.error('Error generating image:', error);
                alert('Error generating image. Check console for details.');
            } finally {
                // Restore transform
                element.style.transform = originalTransform;
                element.style.marginBottom = originalMargin;
            }
        }

        async function downloadAll() {
            await download('preview-updates', 'social-updates');
            await download('preview-article', 'social-article');
        }
    </script>
</body>

</html>
