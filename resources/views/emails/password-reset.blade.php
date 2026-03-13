<!DOCTYPE html>
<html>
<head>
    <title>Password Reset</title>
</head>
<body>
    <h1>Hello {{ $user->firstname }}!</h1>
    <p>Your password reset code is: <strong>{{ $user->password_reset_code }}</strong></p>
    <p>This code will expire soon.</p>
</body>
</html>