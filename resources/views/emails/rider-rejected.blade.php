<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Application Status Update</title>
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
            border-bottom: 2px solid #f44336;
        }
        .header h1 {
            color: #f44336;
            margin: 0;
        }
        .content {
            padding: 30px 20px;
        }
        .rejection-box {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #1976D2;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
        }
        .info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Application Status Update</h1>
        </div>
        
        <div class="content">
            <h2>Dear {{ $rider->first_name }} {{ $rider->last_name }},</h2>
            
            <p>Thank you for your interest in becoming a rider with Mega Rider.</p>
            
            <div class="rejection-box">
                <p><strong>Application Status: ❌ Not Approved</strong></p>
                <p><strong>Reason for rejection:</strong></p>
                <p>{{ $rejectionReason }}</p>
            </div>
            
            <div class="info">
                <p><strong>What this means:</strong></p>
                <ul>
                    <li>You cannot proceed with the registration process at this time</li>
                    <li>You will not be able to create a password or access the rider dashboard</li>
                    <li>Your application has been declined based on the reason stated above</li>
                </ul>
            </div>
            
            <p><strong>What you can do:</strong></p>
            <ul>
                <li>Review the rejection reason carefully</li>
                <li>Address the issues mentioned</li>
                <li>Submit a new application with corrected information</li>
                <li>Contact our support team if you need clarification</li>
            </ul>
            
            <div style="text-align: center;">
                <a href="{{ url('/rider/register') }}" class="button">Submit New Application</a>
            </div>
            
            <p>If you believe this decision was made in error or need further clarification, please contact our support team at support@megarider.com</p>
            
            <p>We appreciate your understanding.</p>
            
            <p>Best regards,<br>
            <strong>Mega Rider Team</strong></p>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Mega Rider. All rights reserved.</p>
        </div>
    </div>
</body>
</html>