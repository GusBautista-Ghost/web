<?php
session_start();
require_once 'conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';
$error = '';

// Procesar actualización de perfil
if (isset($_POST['actualizar_perfil'])) {
    $nuevo_nombre = trim($_POST['nombre']);
    
    if (empty($nuevo_nombre)) {
        $error = "El nombre no puede estar vacío";
    } else {
        // Manejar la subida de imagen
        $imagen_perfil = null;
        
        if (isset($_FILES['imagen_perfil']) && $_FILES['imagen_perfil']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['imagen_perfil']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (in_array(strtolower($filetype), $allowed)) {
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = $usuario_id . '_' . time() . '.' . $filetype;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['imagen_perfil']['tmp_name'], $destination)) {
                    $stmt = $conn->prepare("SELECT imagen_perfil FROM usuarios WHERE id = ?");
                    $stmt->bind_param("i", $usuario_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $old_data = $result->fetch_assoc();
                    
                    if ($old_data['imagen_perfil'] && file_exists($old_data['imagen_perfil'])) {
                        unlink($old_data['imagen_perfil']);
                    }
                    
                    $imagen_perfil = $destination;
                }
            } else {
                $error = "Solo se permiten imágenes JPG, JPEG, PNG o GIF";
            }
        }
        
        if (empty($error)) {
            if ($imagen_perfil) {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, imagen_perfil = ? WHERE id = ?");
                $stmt->bind_param("ssi", $nuevo_nombre, $imagen_perfil, $usuario_id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?");
                $stmt->bind_param("si", $nuevo_nombre, $usuario_id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['usuario_nombre'] = $nuevo_nombre;
                $mensaje = "Perfil actualizado correctamente";
            } else {
                $error = "Error al actualizar el perfil";
            }
            $stmt->close();
        }
    }
}

// Obtener datos actuales del usuario
$stmt = $conn->prepare("SELECT nombre, imagen_perfil, rol FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

$nombre_usuario = $usuario['nombre'];
$imagen_perfil = $usuario['imagen_perfil'] ?? null;
$rol_usuario = $usuario['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }

        .navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar h1 {
            color: #667eea;
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 10px;
            transition: background 0.3s ease;
        }

        .user-profile:hover {
            background: #f5f5f5;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }

        .user-avatar-default {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
            border: 3px solid #667eea;
        }

        .user-name {
            color: #333;
            font-weight: 600;
            font-size: 15px;
        }

        .btn-logout {
            padding: 10px 20px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: #ff3838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .welcome-section h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-section p {
            font-size: 16px;
            opacity: 0.9;
        }

        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        .section-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }

        .icon-inventario {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .icon-ventas {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .icon-garantias {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .icon-devoluciones {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .icon-reportes {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }

        .section-card h3 {
            color: #333;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .section-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .section-content {
            display: none;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .section-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            color: #333;
            font-size: 26px;
        }

        .btn-close {
            background: #ff4757;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-close:hover {
            background: #ff3838;
            transform: translateY(-2px);
        }

        .placeholder-content {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .placeholder-content h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .placeholder-content p {
            font-size: 16px;
        }

        /* Modal de perfil */
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

        .modal-content h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 28px;
            text-align: center;
        }

        .profile-preview {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-preview-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
            margin-bottom: 15px;
        }

        .profile-preview-default {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 50px;
            font-weight: bold;
            border: 4px solid #667eea;
            margin-bottom: 15px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .input-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .input-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            color: #667eea;
            font-weight: 500;
        }

        .file-input-label:hover {
            background: #e7e9fc;
            border-color: #764ba2;
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

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
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

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .sections-grid {
                grid-template-columns: 1fr;
            }

            .welcome-section {
                padding: 25px;
            }

            .welcome-section h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>📊 Sistema de Gestión</h1>
        <div class="user-info">
            <div class="user-profile" onclick="openModal()">
                <?php if ($imagen_perfil && file_exists($imagen_perfil)): ?>
                    <img src="<?php echo htmlspecialchars($imagen_perfil); ?>" alt="Perfil" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar-default">
                        <?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
            </div>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-section">
            <h2>¡Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>!</h2>
            <p>Gestiona tu negocio de forma eficiente con nuestro sistema integral</p>
            <p style="font-size: 14px; margin-top: 10px; opacity: 0.9;">
                <?php echo $rol_usuario === 'administrador' ? '👑 Administrador - Acceso completo' : '👤 Empleado - Acceso limitado'; ?>
            </p>
        </div>

        <div class="sections-grid">
            <?php if ($rol_usuario === 'administrador'): ?>
            <div class="section-card" onclick="window.location.href='inventario.php'">
                <div class="section-icon icon-inventario">📦</div>
                <h3>Inventario</h3>
                <p>Administra tus productos, stock y categorías. Mantén un control preciso de tu mercancía.</p>
            </div>
            <?php endif; ?>

            <div class="section-card" onclick="window.location.href='ventas.php'">
                <div class="section-icon icon-ventas">💰</div>
                <h3>Ventas</h3>
                <p>Registra y gestiona todas tus ventas. Genera tickets y controla tus ingresos diarios.</p>
            </div>

            <div class="section-card" onclick="window.location.href='garantias.php'">
                <div class="section-icon icon-garantias">✅</div>
                <h3>Garantías</h3>
                <p>Administra las garantías de productos. Seguimiento de solicitudes y resoluciones.</p>
            </div>

            <div class="section-card" onclick="window.location.href='devoluciones.php'">
                <div class="section-icon icon-devoluciones">↩️</div>
                <h3>Devoluciones</h3>
                <p>Gestiona devoluciones de productos. Control de motivos y reembolsos.</p>
            </div>

            <?php if ($rol_usuario === 'administrador'): ?>
            <div class="section-card" onclick="window.location.href='reportes.php'">
                <div class="section-icon icon-reportes">📊</div>
                <h3>Reportes</h3>
                <p>Visualiza estadísticas y reportes detallados. Analiza el rendimiento de tu negocio.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de edición de perfil -->
    <div id="perfilModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            
            <h2>Editar Perfil</h2>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="profile-preview">
                <?php if ($imagen_perfil && file_exists($imagen_perfil)): ?>
                    <img src="<?php echo htmlspecialchars($imagen_perfil); ?>" alt="Perfil" class="profile-preview-img" id="previewImg">
                <?php else: ?>
                    <div class="profile-preview-default" id="previewDefault">
                        <?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="input-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_usuario); ?>" required>
                </div>
                
                <div class="input-group">
                    <label>Imagen de perfil</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="imagen_perfil" name="imagen_perfil" accept="image/*" onchange="previewImage(this)">
                        <label for="imagen_perfil" class="file-input-label">
                            📷 Seleccionar imagen
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="actualizar_perfil" class="btn">Guardar Cambios</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('perfilModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('perfilModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('perfilModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const previewImg = document.getElementById('previewImg');
                    const previewDefault = document.getElementById('previewDefault');
                    
                    if (previewImg) {
                        previewImg.src = e.target.result;
                    } else if (previewDefault) {
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.className = 'profile-preview-img';
                        newImg.id = 'previewImg';
                        previewDefault.parentNode.replaceChild(newImg, previewDefault);
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        <?php if ($mensaje || $error): ?>
            openModal();
        <?php endif; ?>
    </script>
</body>
</html>