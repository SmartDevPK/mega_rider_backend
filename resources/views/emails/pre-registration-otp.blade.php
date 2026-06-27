<!DOCTYPE html>
<html>

<head>
    <title>Email Verification OTP</title>
</head>

<body>
    <h1>Verify Your Email Address</h1>
    <p>Your OTP verification code is:</p>
    <h2 style="font-size: 24px; letter-spacing: 2px; padding: 10px; background: #f4f4f4; display: inline-block;">
        {{ $otp }}
    </h2>
    <p>This code will expire in 10 minutes.</p>
    <p>If you did not request this verification, please ignore this email.</p>
</body>

</html>
