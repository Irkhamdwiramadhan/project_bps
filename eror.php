<?php
$message = $_GET['message'] ?? 'Terjadi kesalahan tidak dikenal.';
$message_display = str_replace('_', ' ', $message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .container { max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
        h1 { color: #d9534f; }
        p { font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Oops! Terjadi Kesalahan.</h1>
        <p><?php echo htmlspecialchars($message_display); ?></p>
        <a href="javascript:history.back()">Kembali</a>
    </div>
</body>
</html>