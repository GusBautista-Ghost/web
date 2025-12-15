<?php
session_start();
require_once 'conexion.php';

// Si ya está logueado, redirigir a principal
if (isset($_SESSION['usuario_id'])) {
    header("Location: principal.php");
    exit();
}

$error = '';
$success = '';

// Procesar LOGIN
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Por favor completa todos los campos";
    } else {
        $stmt = $conn->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $usuario = $result->fetch_assoc();
            if (password_verify($password, $usuario['password'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_rol'] = $usuario['rol'];
                header("Location: principal.php");
                exit();
            } else {
                $error = "Credenciales incorrectas";
            }
        } else {
            $error = "Credenciales incorrectas";
        }
        $stmt->close();
    }
}

// Procesar REGISTRO
if (isset($_POST['registro'])) {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email_registro']);
    $password = $_POST['password_registro'];
    $password_confirm = $_POST['password_confirm'];
    $rol = $_POST['rol'] ?? 'empleado';
    
    if (empty($nombre) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = "Por favor completa todos los campos";
    } elseif ($password !== $password_confirm) {
        $error = "Las contraseñas no coinciden";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } else {
        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Este email ya está registrado";
        } else {
            // Registrar nuevo usuario
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $email, $password_hash, $rol);
            
            if ($stmt->execute()) {
                $success = "Cuenta creada exitosamente. ¡Ahora puedes iniciar sesión!";
            } else {
                $error = "Error al crear la cuenta";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            padding: 50px 40px;
        }

        h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 600;
            text-align: center;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
            text-align: center;
        }

        .input-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .toggle-form {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }

        .toggle-form a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .toggle-form a:hover {
            text-decoration: underline;
        }

        /* Estilos para el modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 40px;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: #999;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
            line-height: 1;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            color: #333;
            background: #f0f0f0;
        }

        .password-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 25px;
            }
            
            .modal-content {
                padding: 30px 25px;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <!-- FORMULARIO DE LOGIN -->
    <div class="container">
        <h2>Bienvenido</h2>
        <p class="subtitle">Inicia sesión en tu cuenta</p>
        
        <?php if ($error && isset($_POST['login'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="input-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" name="login" class="btn">Iniciar Sesión</button>
        </form>
        
        <div class="toggle-form">
            ¿No tienes cuenta? <a onclick="openModal()">Crear cuenta</a>
        </div>
    </div>

    <!-- MODAL DE REGISTRO -->
    <div id="registroModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            
            <h2>Crear Cuenta</h2>
            <p class="subtitle">Regístrate para comenzar</p>
            
            <?php if ($error && isset($_POST['registro'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="input-group">
                    <label for="email_registro">Correo electrónico</label>
                    <input type="email" id="email_registro" name="email_registro" required>
                </div>
                
                <div class="input-group">
                    <label for="rol">Rol de Usuario</label>
                    <select id="rol" name="rol" required style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                        <option value="empleado">Empleado</option>
                        <option value="administrador">Administrador</option>
                    </select>
                </div>
                
                <div class="input-group">
                    <label for="password_registro">Contraseña</label>
                    <input type="password" id="password_registro" name="password_registro" required>
                    <p class="password-hint">Mínimo 6 caracteres</p>
                </div>
                
                <div class="input-group">
                    <label for="password_confirm">Confirmar contraseña</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                
                <button type="submit" name="registro" class="btn">Crear Cuenta</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="margin-top: 10px;">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('registroModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('registroModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('registroModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Abrir modal automáticamente si hay error en registro
        <?php if ($error && isset($_POST['registro'])): ?>
            openModal();
        <?php endif; ?>
    </script>
</body>
</html>