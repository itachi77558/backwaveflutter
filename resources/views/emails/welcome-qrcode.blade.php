<!-- resources/views/emails/welcome-qrcode.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .qr-code {
            max-width: 300px;
            margin: 20px auto;
        }
        .header {
            background: #4F46E5;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Bienvenue {{ $user->first_name }}!</h1>
        </div>
        
        <div class="content">
            <p>Merci d'avoir créé votre compte. Voici votre QR code personnel :</p>
            
            <div class="qr-code">
                <img src="{{ $qrCodeUrl }}" alt="Votre QR Code personnel" style="width: 100%; height: auto;">
            </div>
            
            <p>Vous pouvez utiliser ce QR code pour partager rapidement votre profil avec d'autres utilisateurs.</p>
        </div>
        
        <div class="footer">
            <p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>
        </div>
    </div>
</body>
</html>