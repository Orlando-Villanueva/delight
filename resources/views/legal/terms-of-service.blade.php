@extends('layouts.app')

@section('title', 'Terms of Service - Delight')

@section('content')
    <div class="legal-page">
        <div class="container max-w-4xl mx-auto px-4 py-8">
            <header class="legal-header mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Terms of Service</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Last updated: {{ date('F j, Y') }}</p>
                <nav class="mt-4">
                    <a href="{{ route('landing') }}"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">←
                        Back to Home</a>
                </nav>
            </header>

            <div class="legal-content prose prose-lg max-w-none">
                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Agreement to Terms</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Welcome to Delight, a Bible reading habit tracking application. These Terms of Service ("Terms")
                        govern your use of our website and service operated by Delight ("we," "us," or "our").
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        By accessing or using our service, you agree to be bound by these Terms. If you disagree with any
                        part of these terms, then you may not access the service.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Description of Service</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Delight is a web application designed to help users track their Bible reading habits. Our service
                        provides:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Daily Bible reading logging functionality</li>
                        <li>Reading streak tracking and statistics</li>
                        <li>Visual progress indicators and completion grids</li>
                        <li>Personal reading history and analytics</li>
                        <li>Multilingual support (English and French)</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        Our service is currently provided free of charge to all users.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">User Accounts</h2>
                    <div class="mb-4">
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">Account Creation</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-2">To use our service, you must:</p>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-1 ml-4">
                            <li>Provide accurate and complete registration information</li>
                            <li>Have parental permission if you are under 13 years of age</li>
                            <li>Maintain the security of your account credentials</li>
                            <li>Notify us immediately of any unauthorized use of your account</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-gray-200">Account Responsibility</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                            You are responsible for all activities that occur under your account. You agree to keep your
                            login credentials secure and not share them with others.
                        </p>
                    </div>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Acceptable Use</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">You agree to use our service only for
                        lawful purposes and in accordance with these Terms. You agree not to:</p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Use the service for any illegal or unauthorized purpose</li>
                        <li>Attempt to gain unauthorized access to our systems or other users' accounts</li>
                        <li>Interfere with or disrupt the service or servers connected to the service</li>
                        <li>Use automated scripts or bots to access the service</li>
                        <li>Upload or transmit viruses, malware, or other harmful code</li>
                        <li>Harass, abuse, or harm other users</li>
                        <li>Violate any applicable laws or regulations</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">User Content</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Your reading logs and personal data remain your property. By using our service, you grant us a
                        limited license to store and process your data solely for the purpose of providing our Bible reading
                        tracking service.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        You are responsible for the accuracy of your reading logs and any other information you provide to
                        the service.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        We reserve the right to remove any content that violates these Terms or is otherwise objectionable,
                        though we have no obligation to monitor user content.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Service Availability</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We strive to provide reliable service, but we cannot guarantee:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Uninterrupted or error-free operation of the service</li>
                        <li>That the service will meet your specific requirements</li>
                        <li>That all data will be preserved indefinitely</li>
                        <li>Availability during maintenance periods or technical difficulties</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        We reserve the right to modify, suspend, or discontinue the service at any time with reasonable
                        notice to users.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Privacy</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        Your privacy is important to us. Please review our <a href="{{ route('privacy-policy') }}"
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline">Privacy
                            Policy</a>, which explains how we collect, use, and protect your information. By using our
                        service, you agree to our privacy practices as described in the Privacy Policy.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Intellectual Property</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        The service and its original content, features, and functionality are owned by Delight and are
                        protected by international copyright, trademark, patent, trade secret, and other intellectual
                        property laws.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        You may not reproduce, distribute, modify, create derivative works of, publicly display, publicly
                        perform, republish, download, store, or transmit any of the material on our service without our
                        prior written consent.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Disclaimer of Warranties</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        Our service is provided on an "AS IS" and "AS AVAILABLE" basis. We make no warranties, expressed or
                        implied, and hereby disclaim all other warranties including, without limitation, implied warranties
                        of merchantability, fitness for a particular purpose, or non-infringement.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        We do not warrant that the service will be uninterrupted, secure, or error-free, or that defects
                        will be corrected.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Limitation of Liability</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        In no event shall Delight, its directors, employees, partners, agents, suppliers, or affiliates be
                        liable for any indirect, incidental, special, consequential, or punitive damages, including without
                        limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from your
                        use of the service.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        Our total liability to you for all claims arising from or relating to the service shall not exceed
                        the amount you paid us for the service in the 12 months preceding the claim (which, for our free
                        service, would be $0).
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Indemnification</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        You agree to defend, indemnify, and hold harmless Delight and its affiliates from and against any
                        and all claims, damages, obligations, losses, liabilities, costs, or debt, and expenses (including
                        but not limited to attorney's fees) arising from your use of the service or violation of these
                        Terms.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Termination</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We may terminate or suspend your account and access to the service immediately, without prior notice
                        or liability, for any reason, including without limitation if you breach the Terms.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        You may terminate your account at any time by contacting us using the information provided below.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        Upon termination, your right to use the service will cease immediately, and we will delete your
                        account and associated data within 30 days.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Changes to Terms</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        We reserve the right to modify or replace these Terms at any time. If a revision is material, we
                        will:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 space-y-2 ml-4">
                        <li>Provide at least 30 days notice before any new terms take effect</li>
                        <li>Update the "Last updated" date at the top of these Terms</li>
                        <li>Notify users of material changes (we'll implement email notifications for this purpose)</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        Your continued use of the service after any changes constitutes acceptance of the new Terms.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Governing Law</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        These Terms shall be interpreted and governed by the laws of the jurisdiction in which our company
                        is registered, without regard to its conflict of law provisions. Any disputes arising from these
                        Terms or your use of the service will be resolved through binding arbitration or in the courts of
                        that jurisdiction.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Severability</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        If any provision of these Terms is held to be invalid or unenforceable, the remaining provisions
                        will remain in full force and effect. The invalid or unenforceable provision will be replaced with a
                        valid provision that most closely matches the intent of the original provision.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Contact Information</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                        If you have any questions about these Terms of Service, please contact us:
                    </p>
                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300"><strong
                                class="text-gray-900 dark:text-gray-100">Email:</strong> orlandovillanueva11@gmail.com</p>
                        <p class="text-gray-700 dark:text-gray-300 mt-2">
                            We will respond to your inquiry within 30 days of receiving your request.
                        </p>
                    </div>
                </section>

                <section class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Entire Agreement</h2>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        These Terms of Service, together with our Privacy Policy, constitute the entire agreement between
                        you and Delight regarding the use of our service and supersede all prior agreements and
                        understandings, whether written or oral.
                    </p>
                </section>
            </div>
        </div>
    </div>
@endsection
