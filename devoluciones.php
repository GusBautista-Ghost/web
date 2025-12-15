<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener datos del usuario
$stmt = $conn->prepare("SELECT nombre, imagen_perfil FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

$nombre_usuario = $usuario['nombre'];
$imagen_perfil = $usuario['imagen_perfil'] ?? null;

// Procesar nueva devolución
if (isset($_POST['registrar_devolucion'])) {
    $producto_id = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $motivo = trim($_POST['motivo']);
    $tipo = $_POST['tipo_devolucion'];
    $monto = $_POST['monto_devuelto'] ?? 0;
    
    $folio = 'D-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("INSERT INTO devoluciones (producto_id, cantidad, folio_devolucion, motivo, tipo_devolucion, monto_devuelto) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssd", $producto_id, $cantidad, $folio, $motivo, $tipo, $monto);
    
    if ($stmt->execute()) {
        echo "<script>alert('Devolución registrada\\nFolio: $folio'); window.location.href='devoluciones.php';</script>";
    }
}

// Actualizar estado de devolución
if (isset($_POST['actualizar_devolucion'])) {
    $devolucion_id = $_POST['devolucion_id'];
    $estado = $_POST['estado'];
    $observaciones = trim($_POST['observaciones']);
    
    if ($estado == 'completada') {
        // Devolver al stock si es completada
        $dev_info = $conn->query("SELECT producto_id, cantidad FROM devoluciones WHERE id = $devolucion_id")->fetch_assoc();
        $conn->query("UPDATE productos SET stock = stock + {$dev_info['cantidad']} WHERE id = {$dev_info['producto_id']}");
        
        $stmt = $conn->prepare("UPDATE devoluciones SET estado = ?, observaciones = ?, fecha_resolucion = NOW() WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE devoluciones SET estado = ?, observaciones = ? WHERE id = ?");
    }
    $stmt->bind_param("ssi", $estado, $observaciones, $devolucion_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Devolución actualizada'); window.location.href='devoluciones.php';</script>";
    }
}

// Obtener devoluciones
$filtro = $_GET['filtro'] ?? 'todas';
$sql = "SELECT d.*, p.nombre as producto_nombre, p.precio_venta FROM devoluciones d 
        LEFT JOIN productos p ON d.producto_id = p.id";

if ($filtro != 'todas') {
    $sql .= " WHERE d.estado = ?";
}

$sql .= " ORDER BY d.fecha_registro DESC";

$stmt = $conn->prepare($sql);
if ($filtro != 'todas') {
    $stmt->bind_param("s", $filtro);
}
$stmt->execute();
$devoluciones = $stmt->get_result();

// Obtener productos
$productos = $conn->query("SELECT id, nombre, precio_venta FROM productos ORDER BY nombre");

// Estadísticas
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
    COALESCE(SUM(monto_devuelto), 0) as total_devuelto
    FROM devoluciones")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devoluciones - Sistema de Gestión</title>
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

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .navbar h1 {
            color: #667eea;
            font-size: 24px;
        }

        .btn-back {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }

        .user-avatar-default {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #fa709a;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex: 1;
        }

        .btn-filter {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }

        .btn-filter.active {
            border-color: #fa709a;
            background: #fa709a;
            color: white;
        }

        .btn-nueva {
            padding: 12px 25px;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .devoluciones-grid {
            display: grid;
            gap: 20px;
        }

        .devolucion-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #fa709a;
        }

        .devolucion-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: center;
        }

        .devolucion-folio {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .devolucion-estado {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-aprobada { background: #d4edda; color: #155724; }
        .estado-rechazada { background: #f8d7da; color: #721c24; }
        .estado-completada { background: #d1ecf1; color: #0c5460; }

        .tipo-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .tipo-reembolso { background: #ffeaa7; color: #d63031; }
        .tipo-cambio { background: #dfe6e9; color: #2d3436; }

        .devolucion-info {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .devolucion-actions {
            display: flex;
            gap: 10px;
        }

        .btn-gestionar {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
            background: white;
            border-radius: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="principal.php" class="btn-back">← Volver</a>
            <h1>↩️ Devoluciones</h1>
        </div>
        <div class="user-info">
            <?php if ($imagen_perfil && file_exists($imagen_perfil)): ?>
                <img src="<?php echo htmlspecialchars($imagen_perfil); ?>" class="user-avatar">
            <?php else: ?>
                <div class="user-avatar-default">
                    <?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?>
                </div>
            <?php endif; ?>
            <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Gestión de Devoluciones</h2>
            <p>Administra devoluciones y reembolsos de productos</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Devoluciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #f39c12;"><?php echo $stats['pendientes']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #2ecc71;"><?php echo $stats['completadas']; ?></div>
                <div class="stat-label">Completadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #e74c3c;">$<?php echo number_format($stats['total_devuelto'], 2); ?></div>
                <div class="stat-label">Total Devuelto</div>
            </div>
        </div>

        <div class="toolbar">
            <div class="filter-buttons">
                <a href="?filtro=todas" class="btn-filter <?php echo $filtro == 'todas' ? 'active' : ''; ?>">Todas</a>
                <a href="?filtro=pendiente" class="btn-filter <?php echo $filtro == 'pendiente' ? 'active' : ''; ?>">Pendientes</a>
                <a href="?filtro=aprobada" class="btn-filter <?php echo $filtro == 'aprobada' ? 'active' : ''; ?>">Aprobadas</a>
                <a href="?filtro=completada" class="btn-filter <?php echo $filtro == 'completada' ? 'active' : ''; ?>">Completadas</a>
            </div>
            <button class="btn-nueva" onclick="openDevolucionModal()">+ Nueva Devolución</button>
        </div>

        <div class="devoluciones-grid">
            <?php if ($devoluciones->num_rows > 0): ?>
                <?php while($d = $devoluciones->fetch_assoc()): ?>
                    <div class="devolucion-card">
                        <div class="devolucion-header">
                            <div>
                                <span class="devolucion-folio"><?php echo $d['folio_devolucion']; ?></span>
                                <span class="tipo-badge tipo-<?php echo $d['tipo_devolucion']; ?>">
                                    <?php echo ucfirst($d['tipo_devolucion']); ?>
                                </span>
                            </div>
                            <div class="devolucion-estado estado-<?php echo $d['estado']; ?>">
                                <?php echo ucfirst($d['estado']); ?>
                            </div>
                        </div>
                        <div class="devolucion-info">
                            <strong>Producto:</strong> <?php echo htmlspecialchars($d['producto_nombre']); ?><br>
                            <strong>Cantidad:</strong> <?php echo $d['cantidad']; ?> unidades<br>
                            <strong>Motivo:</strong> <?php echo htmlspecialchars($d['motivo']); ?><br>
                            <?php if ($d['monto_devuelto'] > 0): ?>
                                <strong>Monto Devuelto:</strong> $<?php echo number_format($d['monto_devuelto'], 2); ?><br>
                            <?php endif; ?>
                            <?php if ($d['observaciones']): ?>
                                <strong>Observaciones:</strong> <?php echo htmlspecialchars($d['observaciones']); ?><br>
                            <?php endif; ?>
                            <strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($d['fecha_registro'])); ?>
                        </div>
                        <div class="devolucion-actions">
                            <button class="btn-gestionar" onclick='gestionarDevolucion(<?php echo json_encode($d); ?>)'>Gestionar</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay devoluciones</h3>
                    <p>No se encontraron devoluciones con los filtros seleccionados</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Nueva Devolución -->
    <div id="devolucionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDevolucionModal()">&times;</span>
            <h2>Registrar Devolución</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>Producto *</label>
                    <select name="producto_id" id="producto_select" required onchange="calcularMonto()">
                        <option value="">Seleccionar...</option>
                        <?php while($prod = $productos->fetch_assoc()): ?>
                            <option value="<?php echo $prod['id']; ?>" data-precio="<?php echo $prod['precio_venta']; ?>">
                                <?php echo htmlspecialchars($prod['nombre']); ?> - $<?php echo number_format($prod['precio_venta'], 2); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Cantidad *</label>
                    <input type="number" name="cantidad" id="cantidad_input" min="1" value="1" required onchange="calcularMonto()">
                </div>
                
                <div class="form-group">
                    <label>Tipo de Devolución *</label>
                    <select name="tipo_devolucion" id="tipo_select" required onchange="toggleMonto()">
                        <option value="reembolso">Reembolso</option>
                        <option value="cambio">Cambio</option>
                    </select>
                </div>
                
                <div class="form-group" id="monto_group">
                    <label>Monto a Devolver *</label>
                    <input type="number" step="0.01" name="monto_devuelto" id="monto_input" readonly>
                    <div class="info-box">
                        El monto se calcula automáticamente según el precio y cantidad
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Motivo de la Devolución *</label>
                    <textarea name="motivo" rows="3" required placeholder="Describe el motivo de la devolución..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="registrar_devolucion" class="btn btn-primary" style="flex: 1;">Registrar</button>
                    <button type="button" onclick="closeDevolucionModal()" class="btn btn-secondary" style="flex: 1;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gestionar Devolución -->
    <div id="gestionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeGestionModal()">&times;</span>
            <h2>Gestionar Devolución</h2>
            
            <form method="POST">
                <input type="hidden" name="devolucion_id" id="devolucion_id">
                
                <div class="form-group">
                    <label>Estado *</label>
                    <select name="estado" id="estado" required>
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobada">Aprobada</option>
                        <option value="rechazada">Rechazada</option>
                        <option value="completada">Completada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="4" placeholder="Agrega observaciones sobre esta devolución..."></textarea>
                </div>
                
                <div class="info-box">
                    <strong>Nota:</strong> Al marcar como "Completada", el producto se devolverá automáticamente al inventario.
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="actualizar_devolucion" class="btn btn-primary" style="flex: 1;">Actualizar</button>
                    <button type="button" onclick="closeGestionModal()" class="btn btn-secondary" style="flex: 1;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openDevolucionModal() {
        document.getElementById('devolucionModal').classList.add('show');
    }

    function closeDevolucionModal() {
        document.getElementById('devolucionModal').classList.remove('show');
    }

    function gestionarDevolucion(devolucion) {
        document.getElementById('devolucion_id').value = devolucion.id;
        document.getElementById('estado').value = devolucion.estado;
        document.getElementById('observaciones').value = devolucion.observaciones || '';
        document.getElementById('gestionModal').classList.add('show');
    }

    function closeGestionModal() {
        document.getElementById('gestionModal').classList.remove('show');
    }

    function calcularMonto() {
        const select = document.getElementById('producto_select');
        const cantidad = parseFloat(document.getElementById('cantidad_input').value) || 1;
        const option = select.options[select.selectedIndex];
        const precio = parseFloat(option.getAttribute('data-precio')) || 0;
        const monto = precio * cantidad;
        document.getElementById('monto_input').value = monto.toFixed(2);
    }

    function toggleMonto() {
        const tipo = document.getElementById('tipo_select').value;
        const montoGroup = document.getElementById('monto_group');
        if (tipo === 'reembolso') {
            montoGroup.style.display = 'block';
        } else {
            montoGroup.style.display = 'none';
        }
    }

    // Inicializar
    toggleMonto();
    </script>
</body>
</html>