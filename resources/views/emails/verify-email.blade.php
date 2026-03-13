<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email</title>
</head>
<body>
    <h1>Hello {{ $user->firstname }}!</h1>
    <p>Your verification code is: <strong>{{ $user->email_verification_code }}</strong></p>
    <p>This code will expire in 10 minutes.</p>
</body>
</html>