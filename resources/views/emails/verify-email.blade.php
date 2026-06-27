<!DOCTYPE html>
<html>

<head>
    <title>Verify Your Email</title>
</head>

<body>
    <h1>Welcome {{ $user->first_name }}!</h1>
    <p>Please verify your email address using this code:</p>
    <h2 style="font-size: 24px; letter-spacing: 2px; padding: 10px; background: #f4f4f4; display: inline-block;">
        {{ $verificationCode }}
    </h2>
    <p>This code will expire in 60 minutes.</p>
    <p>If you didn't create an account, no further action is required.</p>
</body>

</html>
