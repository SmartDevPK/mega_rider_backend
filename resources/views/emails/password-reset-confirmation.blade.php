<!DOCTYPE html>
<html>

<head>
    <title>Password Changed Successfully</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #FF6B00;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 30px 20px;
            background-color: #f9f9f9;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
        }

        .button {
            display: inline-block;
            padding: 12px 25px;
            background-color: #FF6B00;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Password Changed Successfully! 🔐</h1>
        </div>

        <div class="content">
            <h2>Hello {{ $name ?? ($user->first_name ?? 'User') }},</h2>

            <p>Your password has been successfully changed.</p>

            <p>If you made this change, you can now log in with your new password.</p>

            <p>If you did not request this change, please contact our support team immediately.</p>

            <p>
                <a href="{{ url('/login') }}" class="button">Login to Your Account</a>
            </p>

            <p>For security reasons, we recommend that you:</p>
            <ul>
                <li>Never share your password with anyone</li>
                <li>Use a strong, unique password</li>
                <li>Enable two-factor authentication if available</li>
            </ul>

            <p>Thank you for using Mega Dispatch!</p>

            <p>Best regards,<br>
                <strong>The Mega Dispatch Team</strong> 🚚
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Mega Dispatch. All rights reserved.</p>
            <p>You received this email because your password was changed.</p>
        </div>
    </div>
</body>

</html>
