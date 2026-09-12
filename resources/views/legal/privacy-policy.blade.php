@extends('layouts.app')

@section('title', 'Privacy Policy - Delight')

@section('content')
    <div class="legal-page">
        <div class="container max-w-4xl mx-auto px-4 py-8">
            <header class="legal-header mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Privacy Policy</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Last updated: September 12, 2026</p>
                <nav class="mt-4" aria-label="Privacy policy navigation">
                    <a href="{{ route('landing') }}"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                        ← Back to Home
                    </a>
                </nav>
            </header>

            <div class="legal-content prose prose-lg max-w-none">
                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">About This Policy</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Delight is a Bible reading habit tracker operated by Orlando Labs. This policy explains what
                        information Delight collects, why we use it, when service providers process it, and the choices
                        available to you.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        This policy applies to Delight's website, installable web app, and Android app.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Information We Collect</h2>

                    <div class="mb-5">
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">
                            Account and authentication information
                        </h3>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-1 ml-4">
                            <li>Your name and email address.</li>
                            <li>
                                A securely hashed password when you use password sign-in. We do not store your password in
                                plain text.
                            </li>
                            <li>
                                If you choose Google sign-in, the Google account identifier, name, email address, and profile
                                image that Google provides to Delight.
                            </li>
                            <li>Account verification, sign-in, password-reset, and authentication-token records.</li>
                        </ul>
                    </div>

                    <div class="mb-5">
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">
                            Reading and progress information
                        </h3>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-1 ml-4">
                            <li>Bible books, chapters, passages, and dates you record as read.</li>
                            <li>Optional reading notes that you choose to save.</li>
                            <li>
                                Reading-plan participation, progress, streaks, achievements, recaps, and other results
                                calculated from your reading activity.
                            </li>
                        </ul>
                    </div>

                    <div class="mb-5">
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">
                            Preferences and communications
                        </h3>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-1 ml-4">
                            <li>Settings such as Bible canon, notification choices, and notification timezone.</li>
                            <li>
                                Onboarding and announcement status, email preferences, and records needed to deliver or
                                avoid repeating service and product messages.
                            </li>
                            <li>Feedback, support messages, and deletion requests that you choose to submit.</li>
                            <li>
                                If you enable browser notifications, the browser-issued push subscription and delivery
                                information needed to send and troubleshoot them.
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">
                            Technical and usage information
                        </h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                            Delight may process session identifiers, IP addresses, browser or device information, request
                            timing, sign-in activity, and diagnostic records to operate and secure the service. We also use
                            first-party, account-linked or aggregated measurements—such as onboarding progress, reading
                            activity, and message delivery—to understand whether Delight is working and improve it.
                        </p>
                    </div>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">How We Use Information</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We use the information described above to:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Create, authenticate, maintain, and support your account.</li>
                        <li>Save your reading history and provide progress, plans, streaks, achievements, and recaps.</li>
                        <li>Apply your settings and send messages or reminders you are eligible to receive.</li>
                        <li>Respond to feedback, support inquiries, privacy requests, and account-deletion requests.</li>
                        <li>Protect Delight from abuse, diagnose failures, measure service performance, and improve features.</li>
                        <li>Meet legal, security, fraud-prevention, and record-keeping obligations.</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        We do not sell your personal information or use it for third-party advertising. Delight does not
                        include a third-party behavioral advertising or analytics SDK.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">
                        Service Providers and Disclosure
                    </h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We use service providers to operate Delight. They process information only as needed to provide
                        their services, subject to their own terms and privacy practices. These providers include:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Hosting, database, and infrastructure providers, including Laravel Cloud.</li>
                        <li>Email delivery providers, including Mailgun.</li>
                        <li>Google, when you choose Google sign-in.</li>
                        <li>Browser push services, when you enable browser notifications.</li>
                        <li>Content-delivery and font providers used to load website assets.</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        We may also disclose information when required by law, to protect people or the service, or as part
                        of a business transfer. We do not share personal information for third-party advertising.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">
                        Cookies and Mobile Authentication
                    </h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        The website uses essential session, remember-me, and security cookies to keep you signed in,
                        preserve your session, and protect requests. We do not use advertising cookies.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        The Android app stores its Delight authentication token in secure device storage. Your device and
                        operating system may apply their own security and backup behavior to that storage.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">
                        Retention and Account Deletion
                    </h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We generally keep account information, reading history, progress, and preferences while your
                        account is active. Operational, security, communication, and diagnostic records are kept only as
                        long as reasonably needed for the purposes described in this policy.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        You can <a href="{{ route('account-deletion.create') }}"
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline">
                            request deletion of your Delight account and associated reading data</a>. We verify control of
                        the account email before sending the request to Delight support for manual review and fulfillment.
                        Verified requests are normally completed within 30 days.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        We may retain limited information when reasonably necessary for legal, security, fraud-prevention,
                        dispute-resolution, or record-keeping obligations. If information is present in a backup, a
                        residual copy may remain until that backup is overwritten through its normal cycle.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Security</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Delight uses safeguards designed to protect information, including password hashing, encrypted
                        network connections, access controls, and service monitoring. Access is limited to people and
                        providers who need it to operate or support the service.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        No method of transmission or storage is completely secure, so we cannot guarantee absolute
                        security.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Your Choices and Rights</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Depending on where you live, you may have rights to request access to, correction of, export of, or
                        deletion of your personal information, and to challenge how it is handled. You can also change
                        available settings, disable notifications, opt out of optional marketing email, or stop using
                        Delight.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        Contact us to make a privacy request. We may need to verify your identity before fulfilling it.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Age Eligibility</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Delight is intended for people aged 13 and older. We do not deliberately direct Delight to or seek
                        personal information from children under 13.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        If you believe a child under 13 has provided personal information to Delight, contact us so we can
                        review and remove it as appropriate.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Changes to This Policy</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        We may update this policy as Delight changes. We will post the revised policy here and update the
                        date above. We will provide additional notice when a change materially affects how we handle
                        personal information and notice is appropriate.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Contact Us</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Contact Delight by Orlando Labs with questions, concerns, or privacy requests:
                    </p>
                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300">
                            <strong class="text-gray-900 dark:text-gray-100">Email:</strong>
                            <a href="mailto:{{ config('mail.support_address') }}"
                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline">
                                {{ config('mail.support_address') }}
                            </a>
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
