<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Registration OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #6366F1; margin-top: 0;">Newbook</h2>
        <p>Hi {{ $name }},</p>
        <p>Your OTP for registration is:</p>
        <div style="background: #f0f2f5; border-radius: 8px; padding: 16px; text-align: center; margin: 24px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #6366F1;">{{ $otp }}</span>
        </div>
        <p style="color: #65676b; font-size: 14px;">This OTP expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
        <p style="color: #65676b; font-size: 12px; margin-top: 24px;">If you didn't request this, please ignore this email.</p>
    </div>
</body>
</html>
