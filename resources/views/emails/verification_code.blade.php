<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #1d4ed8, #2563eb); padding: 30px 40px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; letter-spacing: 1px; }
        .header p { color: #bfdbfe; margin: 8px 0 0; font-size: 13px; }
        .body { padding: 40px; text-align: center; }
        .body p { color: #374151; font-size: 15px; margin-bottom: 24px; }
        .code-box { display: inline-block; background: #eff6ff; border: 2px dashed #3b82f6; border-radius: 10px; padding: 18px 40px; margin: 10px auto; }
        .code { font-size: 42px; font-weight: bold; letter-spacing: 10px; color: #1d4ed8; }
        .note { margin-top: 30px; color: #6b7280; font-size: 13px; }
        .footer { background: #f9fafb; padding: 20px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { color: #9ca3af; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Sistema CUP - UAGRM</h1>
            <p>Gestión de Admisiones</p>
        </div>
        <div class="body">
            <p>Hola, recibiste este correo porque solicitaste recuperar tu contraseña.<br>Usa el siguiente código de verificación:</p>
            <div class="code-box">
                <div class="code">{{ $code }}</div>
            </div>
            <p class="note">⏱ Este código expira en <strong>15 minutos</strong>.<br>Si no solicitaste esto, ignora este correo.</p>
        </div>
        <div class="footer">
            <p>Sistema de Admisión CUP &mdash; Universidad Autónoma Gabriel René Moreno</p>
        </div>
    </div>
</body>
</html>
