@extends('layouts.reader')

@section('title', 'Download Delight for Android')
@section('content-width', 'max-w-5xl')

@section('content')
    @php
        $downloadUrl = config('android_release.download_url');
        $downloadAvailable = filled($downloadUrl);
    @endphp

    <div class="w-full">
        <section
            class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary-50 via-white to-orange-50 p-6 sm:p-10 dark:from-primary-950/50 dark:via-gray-800 dark:to-orange-950/30"
            aria-labelledby="android-download-heading">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div>
                    <div class="grid grid-cols-[3.5rem_minmax(0,1fr)] items-center gap-x-4 gap-y-3 sm:grid-cols-[4.5rem_minmax(0,1fr)] sm:gap-y-1">
                        <span class="col-start-1 row-start-2 flex h-14 w-14 shrink-0 overflow-hidden rounded-[22%] sm:row-span-2 sm:row-start-1 sm:h-18 sm:w-18">
                            <img src="{{ asset('images/app-icon-v2-192.png') }}?v={{ config('app.asset_version') }}"
                                alt="" width="72" height="72" class="h-full w-full scale-[1.06] object-cover">
                        </span>
                        <div class="col-span-2 row-start-1 flex flex-wrap items-center gap-2 sm:col-span-1 sm:col-start-2">
                            <p class="text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                Delight: Bible Tracker
                            </p>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                Early access
                            </span>
                        </div>
                        <h1 id="android-download-heading" class="col-start-2 row-start-2 text-2xl font-bold leading-tight text-gray-950 sm:text-4xl dark:text-white">
                            Delight for Android™
                        </h1>
                    </div>

                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-gray-700 dark:text-gray-300">
                        Log Bible readings, protect your streak, and keep your reading history synchronized with Delight on the web.
                    </p>
                </div>

                <div class="lg:min-w-64">
                    @if ($downloadAvailable)
                        <a href="{{ $downloadUrl }}"
                            class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-primary-500 px-6 py-3 text-base font-semibold text-white transition hover:bg-primary-600 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800">
                            Download APK · {{ config('android_release.file_size') }}
                        </a>
                        <div class="mt-3 flex items-center justify-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <img src="{{ asset('images/android-robot-head.svg') }}" alt="" width="28" height="17" class="h-auto w-6">
                            <span>Android™ 7.0+ · Version {{ config('android_release.version') }} ({{ config('android_release.version_code') }})</span>
                        </div>
                    @else
                        <div role="status"
                            class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                            <p class="font-semibold">The direct download is being prepared.</p>
                            <p class="mt-1 text-sm leading-relaxed">
                                The app has passed device validation, but the public download has not been enabled yet.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="py-10 sm:py-12" aria-labelledby="install-heading">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">A quick, one-time setup</p>
                <h2 id="install-heading" class="mt-2 text-2xl font-bold text-gray-950 sm:text-3xl dark:text-white">Install Delight safely</h2>
                <p class="mt-3 leading-relaxed text-gray-600 dark:text-gray-400">
                    Because this release comes directly from Delight instead of the Play Store, Android will ask you to approve the installation source.
                </p>
            </div>

            <ol class="mt-8 grid gap-x-10 gap-y-7 sm:grid-cols-2">
                @foreach ([
                    ['Download the app', 'Open this page on your Android phone and tap the download button.'],
                    ['Allow the source', 'If Android asks, allow your browser to install apps from this source.'],
                    ['Review and install', 'Open the downloaded file, review the Android prompt, and choose Install.'],
                    ['Sign in to Delight', 'Open Delight and sign in with your existing account or create a new one.'],
                ] as [$title, $description])
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-900 dark:text-primary-200">
                            {{ $loop->iteration }}
                        </span>
                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ $description }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <p class="mt-8 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                After installation, you can turn off your browser's “install unknown apps” permission. Delight is signed by Google Play App Signing.
            </p>
        </section>

        <section class="border-y border-gray-200 py-8 dark:border-gray-700" aria-labelledby="release-details-heading">
            <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                <div>
                    <h2 id="release-details-heading" class="text-xl font-semibold text-gray-950 dark:text-white">Current Android release</h2>
                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex gap-2">
                            <dt class="font-medium text-gray-800 dark:text-gray-200">Version</dt>
                            <dd>{{ config('android_release.version') }} ({{ config('android_release.version_code') }})</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="font-medium text-gray-800 dark:text-gray-200">Released</dt>
                            <dd>{{ config('android_release.release_date') }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="font-medium text-gray-800 dark:text-gray-200">File</dt>
                            <dd>{{ config('android_release.file_size') }}</dd>
                        </div>
                    </dl>
                </div>

                <a href="{{ config('android_release.release_url') }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                        <path d="M12 .7a11.5 11.5 0 0 0-3.64 22.41c.58.11.79-.25.79-.56v-2.23c-3.22.7-3.9-1.37-3.9-1.37-.52-1.34-1.28-1.7-1.28-1.7-1.05-.72.08-.7.08-.7 1.16.08 1.77 1.19 1.77 1.19 1.03 1.77 2.7 1.26 3.36.96.1-.75.4-1.26.73-1.55-2.57-.29-5.27-1.29-5.27-5.69 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.16 1.18a10.9 10.9 0 0 1 5.76 0c2.19-1.49 3.15-1.18 3.15-1.18.63 1.59.23 2.76.12 3.05.74.81 1.18 1.83 1.18 3.09 0 4.41-2.7 5.39-5.28 5.68.42.36.79 1.06.79 2.14v3.17c0 .31.21.68.8.56A11.5 11.5 0 0 0 12 .7Z" />
                    </svg>
                    View this release on GitHub
                </a>
            </div>

            <details class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                <summary class="cursor-pointer font-medium text-gray-800 hover:text-primary-600 dark:text-gray-200 dark:hover:text-primary-400">
                    Technical download details
                </summary>
                <div class="mt-4 grid gap-4 rounded-xl bg-gray-100 p-4 dark:bg-gray-800">
                    <div>
                        <p class="font-medium text-gray-800 dark:text-gray-200">Package</p>
                        <code class="mt-1 block break-all text-xs">{{ config('android_release.package') }}</code>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-gray-200">SHA-256 checksum</p>
                        <code class="mt-1 block break-all text-xs">{{ config('android_release.sha256') }}</code>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-gray-200">Signing-certificate fingerprint</p>
                        <code class="mt-1 block break-all text-xs">{{ config('android_release.signing_certificate_sha256') }}</code>
                    </div>
                </div>
            </details>
        </section>

        <section class="grid gap-8 py-10 sm:py-12 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.75fr)]" aria-labelledby="updates-heading">
            <div>
                <h2 id="updates-heading" class="text-xl font-semibold text-gray-950 dark:text-white">Updates and support</h2>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                    This direct-download version does not update automatically yet. Return to this page for newer releases. Google Play is the intended long-term update path once production access is available.
                </p>
                <div class="mt-5 flex flex-wrap gap-x-5 gap-y-3 text-sm font-medium">
                    <a href="{{ route('privacy-policy') }}" class="text-primary-600 hover:underline dark:text-primary-400">Privacy policy</a>
                    <a href="mailto:{{ config('mail.support_address') }}?subject=Delight%20Android%20support" class="text-primary-600 hover:underline dark:text-primary-400">Contact support</a>
                </div>
            </div>

            <aside class="rounded-xl bg-primary-50 p-5 dark:bg-primary-950/40" aria-labelledby="closed-test-heading">
                <h2 id="closed-test-heading" class="font-semibold text-gray-950 dark:text-white">Prefer installing through Google Play?</h2>
                <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    Delight is recruiting Android volunteers for its private Google Play closed test. Testers need an invitation before they can install.
                </p>
                <a href="mailto:{{ config('mail.support_address') }}?subject=Delight%20Google%20Play%20closed%20test"
                    class="mt-4 inline-flex font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    Request a test invitation
                </a>
            </aside>
        </section>

        <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
            Android is a trademark of Google LLC. The Android robot is reproduced from work created and shared by Google and used according to terms described in the
            <a href="https://creativecommons.org/licenses/by/3.0/" target="_blank" rel="noopener noreferrer"
                class="underline hover:text-gray-700 dark:hover:text-gray-200">Creative Commons 3.0 Attribution License</a>.
        </p>
    </div>
@endsection
