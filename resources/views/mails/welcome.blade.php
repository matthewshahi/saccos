<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Joining Our SACCO</title>
    <style>
        /* General Reset */
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
        }
        /* Remove Spacing */
        img, table {
            border: 0;
            outline: none;
            text-decoration: none;
        }
        /* Responsive Design */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
        }
        h1, h2, h3, p {
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #007bff;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .content {
            padding: 20px;
            color: #333333;
        }
        .footer {
            background-color: #f4f4f4;
            color: #777777;
            text-align: center;
            font-size: 12px;
            padding: 15px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 20px 0;
            background-color: #28a745;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        @media screen and (max-width: 600px) {
            .content, .header, .footer {
                padding: 15px;
            }
            h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <table align="center" class="email-container">
        <!-- Header Section -->
        <tr>
            <td class="header">
                <h1>Welcome to Our SACCO!</h1>
            </td>
        </tr>
        <!-- Content Section -->
        <tr>
            <td class="content">
                <h2>Hello {{ $name }},</h2>
                <p>Thank you for showing interest in joining our SACCO. We are thrilled to have you as part of our growing family!</p>
                <p>To confirm your registration, kindly remember that your membership is predicated on the successful payment of the joining fee.</p>
                <p>
                    For your convenience, you can make the payment using the provided details on registration form.
                </p>
                
                <p>
                    Example: If your National ID is <strong>12345678</strong>, use <strong>REG12345678</strong> as the account number.
                </p>
                <p>We look forward to welcoming you onboard officially!</p>
                <p>
                    If you have any questions, feel free to reach out to us anytime.
                </p>
            </td>
        </tr>
        <!-- Footer Section -->
        <tr>
            <td class="footer">
                <p>Thank you for choosing us!</p>
                <p><strong>Your SACCO Team</strong></p>
                 
            </td>
        </tr>
    </table>
</body>
</html>