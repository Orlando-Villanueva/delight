<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Delight - Find delight in your daily Bible reading')</title>
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
        }

        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            min-width: 100%;
            background-color: #f3f4f6;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #374151;
        }

        /* Container */
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Header */
        .header {
            background: #ffffff;
            padding: 40px 32px 24px 32px;
            text-align: center;
        }

        .logo {
            color: #111827;
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            text-decoration: none;
            letter-spacing: -0.05em;
            display: inline-block;
        }

        /* Content */
        .content {
            padding: 0 32px 40px 32px;
        }

        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 16px 0;
        }

        .message {
            font-size: 16px;
            line-height: 1.625;
            color: #4b5563;
            margin: 0 0 24px 0;
        }

        /* Button */
        .button-container {
            text-align: center;
            margin: 32px 0;
        }

        .button {
            display: inline-block;
            background: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            border: none;
        }

        .button:hover {
            background: #1d4ed8;
            box-shadow: 0 6px 8px -1px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }

        /* Alerts (New Standard) */
        .alert {
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
            border: 1px solid transparent;
        }

        .alert-info {
            background-color: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }

        .alert-success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-warning {
            background-color: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .alert-title {
            font-weight: 700;
            margin-bottom: 4px;
            display: block;
            font-size: 14px;
        }

        .alert-text {
            font-size: 14px;
            margin: 0;
        }

        /* Cards (New Standard) */
        .card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px 0;
            display: block;
        }

        .card-text {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
        }

        /* Footer */
        .footer {
            background-color: #f9fafb;
            padding: 32px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer-text {
            font-size: 12px;
            color: #9ca3af;
            margin: 0 0 8px 0;
            line-height: 1.5;
        }

        /* Utilities */
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 32px 0;
            border: none;
        }

        .text-center { text-align: center; }
        .text-sm { font-size: 13px; line-height: 1.5; }
        .text-muted { color: #6b7280; }

        .link {
            color: #6b7280;
            text-decoration: underline;
            transition: color 0.2s;
        }
        .link:hover {
            color: #374151;
        }

        .link-primary {
            color: #2563eb;
            text-decoration: none;
        }
        .link-primary:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
            
            .header {
                padding: 32px 20px 16px 20px !important;
            }
            
            .content {
                padding: 24px 20px 32px 20px !important;
            }
            
            .footer {
                padding: 24px 20px !important;
            }
            
            .button {
                width: 100%;
                box-sizing: border-box;
                text-align: center;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <a href="{{ url('/') }}" class="logo">Delight</a>
        </div>

        <!-- Content -->
        <div class="content">
            @yield('content')
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                &copy; {{ date('Y') }} Delight. All rights reserved.
            </p>
            <p class="footer-text">
                Have ideas to improve Delight?
                <a href="{{ url('/feedback') }}" class="link">Send us Feedback</a>
            </p>
            @yield('footer-extra')
        </div>
    </div>
</body>
</html>
