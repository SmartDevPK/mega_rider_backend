<!DOCTYPE html>
<html>

<head>
    <title>Welcome to Mega Dispatch</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
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
            border-top: 1px solid #ddd;
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
            <h1>Welcome to Mega Dispatch! 🚚</h1>
        </div>

        <div class="content">
            <h2>Hi {{ $name }},</h2>

            <p>Welcome to Mega Dispatch.</p>

            <p>We're excited to have you on board.</p>

            <p>Your account has been successfully created, and you're now ready to send packages, track deliveries in
                real time, and enjoy a faster, simpler delivery experience.</p>

            <p>Whether it's a personal package or a business delivery, Mega Dispatch connects you with trusted riders
                and reliable service whenever you need it.</p>

            <p>Tap into a smarter way to move things.</p>

            <p>Thank you for choosing Mega Dispatch.</p>

            <p>
                <a href="{{ $dashboardUrl }}" class="button">Get Started</a>
            </p>

            <p>See you on the road,<br>
                <strong>The Mega Dispatch Team</strong> 🚚
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Mega Dispatch. All rights reserved.</p>
            <p>You received this email because you created an account with Mega Dispatch.</p>
        </div>
    </div>
</body>

</html>
