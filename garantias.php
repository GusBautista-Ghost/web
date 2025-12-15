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

// Procesar nueva venta
if (isset($_POST['registrar_venta'])) {
    $productos_venta = json_decode($_POST['productos_json'], true);
    $subtotal = $_POST['subtotal'];
    $descuento = $_POST['descuento'] ?? 0;
    $total = $_POST['total'];
    $metodo_pago = $_POST['metodo_pago'];
    $cliente_nombre = trim($_POST['cliente_nombre']);
    $cliente_telefono = trim($_POST['cliente_telefono']);
    
    // Generar folio único
    $folio = 'V-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insertar venta
    $stmt = $conn->prepare("INSERT INTO ventas (usuario_id, folio, subtotal, descuento, total, metodo_pago, cliente_nombre, cliente_telefono) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddsss", $usuario_id, $folio, $subtotal, $descuento, $total, $metodo_pago, $cliente_nombre, $cliente_telefono);
    
    if ($stmt->execute()) {
        $venta_id = $conn->insert_id;
        
        // Insertar detalle de venta y actualizar stock
        foreach ($productos_venta as $prod) {
            $stmt_detalle = $conn->prepare("INSERT INTO ventas_detalle (venta_id, producto_id, producto_nombre, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_detalle->bind_param("iisidd", $venta_id, $prod['id'], $prod['nombre'], $prod['cantidad'], $prod['precio'], $prod['subtotal']);
            $stmt_detalle->execute();
            
            // Actualizar stock
            $stmt_stock = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt_stock->bind_param("ii", $prod['cantidad'], $prod['id']);
            $stmt_stock->execute();
        }
        
        echo "<script>alert('Venta registrada exitosamente\\nFolio: $folio'); window.location.href='ventas.php';</script>";
    }
}

// Obtener ventas recientes
$ventas = $conn->query("SELECT v.*, u.nombre as vendedor FROM ventas v 
    LEFT JOIN usuarios u ON v.usuario_id = u.id 
    ORDER BY v.fecha_venta DESC LIMIT 50");

// Obtener productos para venta
$productos = $conn->query("SELECT p.*, c.nombre as categoria_nombre FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.stock > 0 
    ORDER BY p.nombre");

// Estadísticas
$stats = $conn->query("SELECT 
    COUNT(*) as total_ventas,
    COALESCE(SUM(total), 0) as total_ingresos,
    COALESCE(AVG(total), 0) as ticket_promedio
    FROM ventas 
    WHERE DATE(fecha_venta) = CURDATE()")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Sistema de Gestión</title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            color: #f5576c;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .panel {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .panel h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .btn-nueva-venta {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .btn-nueva-venta:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.3);
        }

        .ventas-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .venta-item {
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .venta-item:hover {
            border-color: #f5576c;
            box-shadow: 0 2px 10px rgba(245, 87, 108, 0.1);
        }

        .venta-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .venta-folio {
            font-weight: 700;
            color: #333;
        }

        .venta-total {
            font-size: 18px;
            font-weight: 700;
            color: #2ecc71;
        }

        .venta-info {
            font-size: 13px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Modal de nueva venta */
        .modal-venta {
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

        .modal-venta.show {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-venta-content {
            background: white;
            border-radius: 15px;
            width: 95%;
            max-width: 900px;
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

        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }

        .producto-item {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .producto-item:hover {
            border-color: #f5576c;
            transform: translateY(-2px);
        }

        .producto-item.selected {
            border-color: #2ecc71;
            background: #f0fff4;
        }

        .carrito {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .carrito-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .cantidad-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cantidad-controls button {
            width: 30px;
            height: 30px;
            border: none;
            background: #f5576c;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        .total-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .total-final {
            font-weight: 700;
            font-size: 24px;
            color: #2ecc71;
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
        .form-group select {
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
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="principal.php" class="btn-back">← Volver</a>
            <h1>💰 Ventas</h1>
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
            <h2>Punto de Venta</h2>
            <p>Registra ventas y genera tickets de forma rápida</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_ventas']; ?></div>
                <div class="stat-label">Ventas Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['total_ingresos'], 2); ?></div>
                <div class="stat-label">Ingresos Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['ticket_promedio'], 2); ?></div>
                <div class="stat-label">Ticket Promedio</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="panel">
                <button class="btn-nueva-venta" onclick="openVentaModal()">+ Nueva Venta</button>
                <h3>Ventas Recientes</h3>
                <div class="ventas-list">
                    <?php if ($ventas->num_rows > 0): ?>
                        <?php while($venta = $ventas->fetch_assoc()): ?>
                            <div class="venta-item">
                                <div class="venta-header">
                                    <span class="venta-folio"><?php echo $venta['folio']; ?></span>
                                    <span class="venta-total">$<?php echo number_format($venta['total'], 2); ?></span>
                                </div>
                                <div class="venta-info">
                                    <?php if ($venta['cliente_nombre']): ?>
                                        <strong>Cliente:</strong> <?php echo htmlspecialchars($venta['cliente_nombre']); ?><br>
                                    <?php endif; ?>
                                    <strong>Vendedor:</strong> <?php echo htmlspecialchars($venta['vendedor']); ?><br>
                                    <strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?><br>
                                    <strong>Método:</strong> <?php echo ucfirst($venta['metodo_pago']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay ventas registradas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <h3>📊 Información</h3>
                <p style="color: #666; line-height: 1.6;">
                    Desde aquí puedes registrar nuevas ventas de forma rápida y eficiente. 
                    El sistema actualiza automáticamente el inventario y genera un folio único para cada venta.
                </p>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Venta -->
    <div id="ventaModal" class="modal-venta">
        <div class="modal-venta-content">
            <span class="close" onclick="closeVentaModal()">&times;</span>
            <h2>Nueva Venta</h2>
            
            <h3>Seleccionar Productos</h3>
            <div class="productos-grid">
                <?php while($prod = $productos->fetch_assoc()): ?>
                    <div class="producto-item" onclick='addProducto(<?php echo json_encode($prod); ?>)'>
                        <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                        <p style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($prod['categoria_nombre']); ?></p>
                        <p style="font-size: 16px; color: #2ecc71; font-weight: 700;">$<?php echo number_format($prod['precio_venta'], 2); ?></p>
                        <p style="font-size: 12px; color: #999;">Stock: <?php echo $prod['stock']; ?></p>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="carrito">
                <h3>Carrito de Venta</h3>
                <div id="carritoItems"></div>
                
                <div class="total-section">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="subtotalDisplay">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Descuento:</span>
                        <input type="number" id="descuentoInput" step="0.01" value="0" style="width: 100px; text-align: right;" oninput="calcularTotal()">
                    </div>
                    <div class="total-row total-final">
                        <span>TOTAL:</span>
                        <span id="totalDisplay">$0.00</span>
                    </div>
                </div>
            </div>

            <form method="POST" onsubmit="return finalizarVenta()">
                <input type="hidden" name="productos_json" id="productos_json">
                <input type="hidden" name="subtotal" id="subtotal">
                <input type="hidden" name="descuento" id="descuento">
                <input type="hidden" name="total" id="total">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                    <div class="form-group">
                        <label>Método de Pago *</label>
                        <select name="metodo_pago" required>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Teléfono Cliente</label>
                        <input type="tel" name="cliente_telefono">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Nombre Cliente</label>
                        <input type="text" name="cliente_nombre">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="registrar_venta" class="btn btn-primary" style="flex: 1;">Registrar Venta</button>
                    <button type="button" onclick="closeVentaModal()" class="btn btn-secondary" style="flex: 1;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let carrito = [];

    function openVentaModal() {
        document.getElementById('ventaModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeVentaModal() {
        document.getElementById('ventaModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        carrito = [];
        renderCarrito();
    }

    function addProducto(producto) {
        const existente = carrito.find(p => p.id === producto.id);
        if (existente) {
            if (existente.cantidad < producto.stock) {
                existente.cantidad++;
            } else {
                alert('No hay suficiente stock');
                return;
            }
        } else {
            carrito.push({
                id: producto.id,
                nombre: producto.nombre,
                precio: parseFloat(producto.precio_venta),
                cantidad: 1,
                stock: producto.stock
            });
        }
        renderCarrito();
    }

    function cambiarCantidad(id, delta) {
        const producto = carrito.find(p => p.id === id);
        if (producto) {
            producto.cantidad += delta;
            if (producto.cantidad <= 0) {
                carrito = carrito.filter(p => p.id !== id);
            } else if (producto.cantidad > producto.stock) {
                alert('No hay suficiente stock');
                producto.cantidad = producto.stock;
            }
            renderCarrito();
        }
    }

    function renderCarrito() {
        const container = document.getElementById('carritoItems');
        if (carrito.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #999;">Carrito vacío</p>';
        } else {
            container.innerHTML = carrito.map(p => `
                <div class="carrito-item">
                    <div>
                        <strong>${p.nombre}</strong><br>
                        <span style="color: #666;">$${p.precio.toFixed(2)} c/u</span>
                    </div>
                    <div class="cantidad-controls">
                        <button type="button" onclick="cambiarCantidad(${p.id}, -1)">-</button>
                        <span style="font-weight: 700;">${p.cantidad}</span>
                        <button type="button" onclick="cambiarCantidad(${p.id}, 1)">+</button>
                        <span style="margin-left: 10px; font-weight: 700; color: #2ecc71;">$${(p.precio * p.cantidad).toFixed(2)}</span>
                    </div>
                </div>
            `).join('');
        }
        calcularTotal();
    }

    function calcularTotal() {
        const subtotal = carrito.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);
        const descuento = parseFloat(document.getElementById('descuentoInput').value) || 0;
        const total = subtotal - descuento;
        
        document.getElementById('subtotalDisplay').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('totalDisplay').textContent = '$' + total.toFixed(2);
    }

    function finalizarVenta() {
        if (carrito.length === 0) {
            alert('Agrega productos al carrito');
            return false;
        }

        const subtotal = carrito.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);
        const descuento = parseFloat(document.getElementById('descuentoInput').value) || 0;
        const total = subtotal - descuento;

        const productosConSubtotal = carrito.map(p => ({
            ...p,
            subtotal: p.precio * p.cantidad
        }));

        document.getElementById('productos_json').value = JSON.stringify(productosConSubtotal);
        document.getElementById('subtotal').value = subtotal;
        document.getElementById('descuento').value = descuento;
        document.getElementById('total').value = total;

        return true;
    }
    </script>
</body>
</html>