<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Code</title>
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
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
            background-color: #f9f9f9;
        }
        .token-code {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            background-color: #fff;
            border: 2px dashed #4CAF50;
            margin: 20px 0;
            letter-spacing: 5px;
            font-family: monospace;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Password Reset Request</h2>
        </div>
        
        <div class="content">
            <p>Hello <strong>{{ $firstName }} {{ $lastName }}</strong>,</p>
            
            <p>We received a request to reset your password. Use the verification code below:</p>
            
            <div class="token-code">
                {{ $token }}
            </div>
            
            <p>This code will expire in <strong>{{ $expires_in_minutes }} minutes</strong>.</p>
            
            <p>If you didn't request this, please ignore this email.</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>