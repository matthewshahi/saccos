<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Our SACCO</title>
</head>
<body>
    <h1>Hello {{ $name }},</h1>
    <p>We are excited to have you join our SACCO! To complete your registration, we need a few additional details from you.</p>
    <p>Please click the link below to provide the required information:</p>
    <p>
        <a href="{{ $uniqueLink }}" style="display: inline-block; padding: 10px 15px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;">
            Complete Your Registration
        </a>
    </p>
    <p>If you have any questions, feel free to contact us. We are here to help!</p>
    <p>Welcome aboard!</p>
    <p>Best regards,<br>Your SACCO Team</p>
</body>
</html>