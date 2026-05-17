<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Mega Rider</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid #4CAF50;
        }
        .header h1 {
            color: #4CAF50;
            margin: 0;
        }
        .content {
            padding: 30px 20px;
        }
        .otp-box {
            background-color: #f0f0f0;
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #333;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Email Verification</h1>
        </div>
        
        <div class="content">
            <h2>Dear {{ $firstName }} {{ $lastName }},</h2>
            
            <p>Thank you for registering as a rider with Mega Rider. Please use the verification code below to verify your email address.</p>
            
            <div class="otp-box">
                {{ $otp }}
            </div>
            
            <p>This code will expire in <strong>15 minutes</strong>.</p>
            
            <div class="warning">
                <p><strong>⚠️ Important:</strong></p>
                <ul>
                    <li>Do not share this code with anyone</li>
                    <li>This code is required to complete your registration</li>
                    <li>After verification, please wait for admin approval</li>
                    <li>Once approved, you will receive another email to set your password</li>
                </ul>
            </div>
            
            <p>If you did not request this verification, please ignore this email.</p>
            
            <p>Best regards,<br>
            <strong>Mega Rider Team</strong></p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Mega Rider. All rights reserved.</p>
        </div>
    </div>
</body>
</html>