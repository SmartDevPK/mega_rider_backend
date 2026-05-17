<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Approved - Mega Rider</title>
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
        .info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .step {
            margin: 15px 0;
            padding: 10px;
            background-color: #f0f7ff;
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
            <h1>Application Approved! 🎉</h1>
        </div>
        
        <div class="content">
            <h2>Dear {{ $rider->first_name }} {{ $rider->last_name }},</h2>
            
            <p>We are pleased to inform you that your application to become a rider has been <strong>approved</strong>!</p>
            
            <div class="info">
                <p><strong>Next Steps to Complete Your Registration:</strong></p>
                
                <div class="step">
                    <strong>Step 1: Verify Your Email</strong>
                    <p>Check your email for a verification code (8-digit OTP). You need to verify your email address before proceeding.</p>
                </div>
                
                <div class="step">
                    <strong>Step 2: Set Your Password</strong>
                    <p>After email verification, you can set your password to activate your account.</p>
                </div>
                
                <div class="step">
                    <strong>Step 3: Login to Dashboard</strong>
                    <p>Once password is set, you can log in and start your journey with us.</p>
                </div>
            </div>
            
            <p><strong>Important Notes:</strong></p>
            <ul>
                <li>You must verify your email within 24 hours</li>
                <li>The verification code expires in 15 minutes</li>
                <li>You can request a new code if needed</li>
                <li>Contact support if you need any assistance</li>
            </ul>
            
            <p>Welcome to the team! We look forward to working with you.</p>
            
            <p>Best regards,<br>
            <strong>Mega Rider Team</strong></p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Mega Rider. All rights reserved.</p>
        </div>
    </div>
</body>
</html>