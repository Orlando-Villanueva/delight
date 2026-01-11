@extends('layouts.app')

@section('title', 'Privacy Policy - Delight')

@section('content')
    <div class="legal-page">
        <div class="container max-w-4xl mx-auto px-4 py-8">
            <header class="legal-header mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Privacy Policy</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Last updated: {{ date('F j, Y') }}</p>
                <nav class="mt-4">
                    <a href="{{ route('landing') }}"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">←
                        Back to Home</a>
                </nav>
            </header>

            <div class="legal-content prose prose-lg max-w-none">
                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Introduction</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Welcome to Delight, a Bible reading habit tracking application. We are committed to protecting your
                        privacy and being transparent about how we collect, use, and protect your information. This Privacy
                        Policy explains our practices regarding your personal data when you use our service.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        By using Delight, you agree to the collection and use of information in accordance with this policy.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Information We Collect</h2>
                    <div class="mb-4">
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">Account Information</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-2">When you create an account, we
                            collect only the essential information needed:</p>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-1 ml-4">
                            <li>Your name (for display purposes)</li>
                            <li>Your email address (for account creation and password recovery)</li>
                            <li>Your password (encrypted and stored securely)</li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">Reading Data</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-2">To provide our Bible reading
                            tracking service, we store:</p>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-1 ml-4">
                            <li>Which Bible books and chapters you've read</li>
                            <li>The dates when you logged your reading</li>
                            <li>Optional notes or passage text you choose to save</li>
                            <li>Calculated statistics (streaks, totals) based on your reading logs</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">What We Don't Collect</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-2">We do <strong
                                class="text-gray-900 dark:text-gray-100">not</strong> collect or track:</p>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-1 ml-4">
                            <li>IP addresses or location information</li>
                            <li>Browser type, device information, or technical details</li>
                            <li>Usage analytics or behavioral tracking</li>
                            <li>Third-party cookies or tracking pixels</li>
                            <li>Any personal information beyond what's listed above</li>
                        </ul>
                    </div>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">How We Use Your Information</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">We use your information only for the
                        essential functions of our Bible reading tracking service:</p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li><strong class="text-gray-900 dark:text-gray-100">Account Management:</strong> Your name and
                            email are used to maintain your account and enable login</li>
                        <li><strong class="text-gray-900 dark:text-gray-100">Reading Tracking:</strong> Your reading logs
                            are stored to display your progress, streaks, and statistics</li>
                        <li><strong class="text-gray-900 dark:text-gray-100">Password Recovery:</strong> Your email is used
                            only for password reset requests when you initiate them</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        <strong class="text-gray-900 dark:text-gray-100">That's it.</strong> We don't use your data for
                        analytics, marketing, advertising, or any other purposes. We don't sell, share, or analyze your data
                        beyond providing the core reading tracking functionality.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Data Storage and Security</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Your data is stored securely using industry-standard practices:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>All passwords are encrypted using secure hashing algorithms</li>
                        <li>Data is stored on secure servers with regular backups</li>
                        <li>We use HTTPS encryption for all data transmission</li>
                        <li>Access to your data is limited to essential personnel only</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        While we implement strong security measures, no method of transmission over the internet is 100%
                        secure. We cannot guarantee absolute security but are committed to protecting your information.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Data Retention</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We keep your data simple and only as long as needed:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Your account information (name, email) is kept while your account is active</li>
                        <li>Your reading logs are preserved to maintain your progress history and streaks</li>
                        <li>If you wish to delete your account and data, please contact us using the information below</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        We don't keep unnecessary logs or copies of your data. When you request account deletion, we will
                        permanently remove all your information from our database within 30 days.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Your Rights</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">You have the following rights regarding
                        your personal data:</p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li><strong class="text-gray-900 dark:text-gray-100">Access:</strong> You can view all your data
                            within the app, or request a copy by contacting us</li>
                        <li><strong class="text-gray-900 dark:text-gray-100">Correction:</strong> Contact us to correct any
                            inaccurate account information</li>
                        <li><strong class="text-gray-900 dark:text-gray-100">Deletion:</strong> Contact us to request
                            deletion of your account and all associated data</li>
                        <li><strong class="text-gray-900 dark:text-gray-100">Data Export:</strong> Contact us to receive
                            your reading data in a portable format</li>
                        <li><strong class="text-gray-900 dark:text-gray-100">Stop Using Service:</strong> You can simply
                            stop using the app at any time</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        Since we're a simple app without automated self-service features yet, please contact us directly for
                        any data requests. We'll respond promptly to help you exercise your rights.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Cookies and Tracking</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We use only the essential cookies required for the app to function:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li><strong class="text-gray-900 dark:text-gray-100">Session Cookie:</strong> To keep you logged in
                            while using the app</li>
                        <li><strong class="text-gray-900 dark:text-gray-100">CSRF Token:</strong> For security to prevent
                            unauthorized requests</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        We do <strong class="text-gray-900 dark:text-gray-100">not</strong> use analytics cookies,
                        advertising cookies, tracking pixels, or any third-party tracking technologies. No data about your
                        browsing behavior is collected or shared.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Third-Party Services</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Our app is self-contained and doesn't integrate with third-party services for analytics,
                        advertising, or data processing. Your data stays within our system.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        The only third-party involvement is our hosting provider, who stores the data on secure servers but
                        has no access to or use of your personal information.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Children and Family Use</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We welcome families using our Bible reading tracker together. Our service is appropriate for users
                        of all ages who want to track their Bible reading progress.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        <strong class="text-gray-900 dark:text-gray-100">For parents:</strong> If your child under 13 wants
                        to use our service, we recommend creating the account yourself and supervising their use. This
                        ensures you maintain control over any personal information shared.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        We collect the same minimal information from all users regardless of age: name, email, and reading
                        logs. If you have any concerns about your child's account or data, please contact us and we'll be
                        happy to help.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Changes to This Privacy Policy
                    </h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We may update this Privacy Policy from time to time. When we do, we will:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Update the "Last updated" date at the top of this policy</li>
                        <li>Post the updated policy on our website</li>
                        <li>Notify users of significant changes (we'll implement email notifications for this purpose)</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        Your continued use of our service after any changes constitutes acceptance of the updated policy.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Contact Us</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        If you have any questions about this Privacy Policy or our data practices, please contact us:
                    </p>
                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300"><strong
                                class="text-gray-900 dark:text-gray-100">Email:</strong> orlandovillanueva11@gmail.com</p>
                        <p class="text-gray-700 dark:text-gray-300 mt-2">
                            We will respond to your inquiry within 30 days of receiving your request.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
