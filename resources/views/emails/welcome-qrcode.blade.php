<!-- resources/views/emails/welcome-qr.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .card {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code img {
            max-width: 250px;
            height: auto;
        }
        .welcome-text {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="welcome-text">
            <h1>Bienvenue {{ $user->first_name }} !</h1>
            <p>Merci de vous être inscrit. Voici votre QR code personnel :</p>
        </div>
        
        <div class="qr-code">
            <img src="{{ $user->qr_code_url }}" alt="Votre QR Code personnel">
        </div>
        
        <div class="instructions">
            <p>Ce QR code est unique et vous permettra d'accéder rapidement à votre compte.</p>
            <p>Pour l'utiliser :</p>
            <ul>
                <li>Enregistrez-le sur votre téléphone</li>
                <li>Montrez-le lors de vos transactions</li>
                <li>Gardez-le en sécurité</li>
            </ul>
        </div>
    </div>
</body>
</html>