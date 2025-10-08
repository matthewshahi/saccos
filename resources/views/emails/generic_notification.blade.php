<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $companyName ?? 'iSACCO Technologies' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:#f5f7fa; font-family:'Helvetica Neue', Arial, sans-serif;">

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f7fa; padding:20px 0;">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
          
          <!-- Header -->
          <tr>
            <td align="center" style="background-color:#003366; padding:25px 20px;">
              <h1 style="margin:0; font-size:20px; color:#ffffff; letter-spacing:0.5px;">
                {{ $companyName ?? 'iSACCO Technologies' }}
              </h1>
              <p style="color:#cfd8e3; margin:6px 0 0 0; font-size:12px;">
                Secure Financial Notifications
              </p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:30px 25px; color:#333333; font-size:15px; line-height:1.7;">
              <p style="margin-top:0;">Dear {{ $name }},</p>

              <p style="white-space:pre-line; margin-bottom:25px;">{!! nl2br(e($messageBody)) !!}</p>

              <div style="margin-top:20px; padding:14px 18px; background-color:#e8f1fd; border-left:4px solid #1a73e8; font-size:13px; color:#333; border-radius:4px;">
                <strong style="display:block; margin-bottom:4px;">🔐 Security Notice:</strong>
                Please do not share your SACCO account details, passwords, or personal PINs with anyone. 
                {{ $companyName ?? 'iSACCO Technologies' }} will never request this information via email or phone.
              </div>

              <p style="margin-top:25px;">Thank you for being part of our SACCO community.</p>

              <p style="margin-bottom:0;">Warm regards,<br>
              <strong>{{ $companyName ?? 'iSACCO Technologies' }}</strong></p>
            </td>
          </tr>

          <!-- Disclaimer -->
          <tr>
            <td style="padding:15px 25px; background-color:#fafafa; font-size:12px; color:#666; line-height:1.6;">
              <p style="margin:0;">
                <strong>Disclaimer:</strong> This message and any attachments are intended solely for the addressed recipient(s). 
                If you received this email in error, please delete it immediately and notify the system administrator. 
                Financial data is confidential and may contain sensitive information. Do not disclose or copy its contents.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
  <td align="center" style="background-color:#003366; padding:20px; color:#ffffff; font-size:12px;">
    <p style="margin:0; line-height:1.6;">
      <strong>iSACCO</strong> is a Sacco Banking Platform developed and powered by 
      <a href="https://shahi.co.ke" style="color:#66b2ff; text-decoration:none; font-weight:600;">Shahi Services Limited</a>.<br>
      iSACCO is a brand of Shahi Services Ltd — creators of digital ERP systems for cooperative societies and community finance institutions across Africa.<br>
      Contact: <a href="tel:+254722400737" style="color:#cfd8e3; text-decoration:none;">+254&nbsp;722&nbsp;400&nbsp;737</a><br>
      <a href="https://shahi.co.ke" style="color:#cfd8e3; text-decoration:none;">shahi.co.ke</a>
    </p>
  </td>
</tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>