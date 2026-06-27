<!DOCTYPE html>
<html>

<head>
    <title>Password Reset Code</title>
</head>

<body>
    <h2>Hello {{ $name }}!</h2>
    <p>You requested to reset your password. Use the code below:</p>
    <h1 style="font-size: 32px; letter-spacing: 5px;">{{ $code }}</h1>
    <p>This code will expire in {{ $expires_in }} minutes.</p>
    <p>If you didn't request this, please ignore this email.</p>
</body>

</html>
