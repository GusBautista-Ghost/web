<?php
session_start();
require_once 'conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

// Verificar si el usuario es administrador
if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'administrador') {
    echo "<script>alert('Acceso denegado. Solo administradores pueden acceder al inventario.'); window.location.href='principal.php';</script>";
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

// Procesar eliminación de producto
if (isset($_POST['eliminar_producto'])) {
    $producto_id = $_POST['producto_id'];
    
    // Obtener imagen para eliminarla
    $stmt = $conn->prepare("SELECT imagen FROM productos WHERE id = ?");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    
    if ($producto && $producto['imagen'] && file_exists($producto['imagen'])) {
        unlink($producto['imagen']);
    }
    
    // Eliminar producto
    $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->bind_param("i", $producto_id);
    if ($stmt->execute()) {
        echo "<script>alert('Producto eliminado correctamente'); window.location.href='inventario.php';</script>";
    }
    $stmt->close();
}

// Procesar agregar/editar producto
if (isset($_POST['guardar_producto'])) {
    $producto_id = $_POST['producto_id'] ?? null;
    $nombre = trim($_POST['nombre_producto']);
    $descripcion = trim($_POST['descripcion_producto']);
    $categoria_id = $_POST['categoria_id'];
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo_compatible']);
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];
    $codigo_barras = trim($_POST['codigo_barras']);
    
    // Manejar imagen
    $imagen = null;
    if (isset($_FILES['imagen_producto']) && $_FILES['imagen_producto']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['imagen_producto']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $upload_dir = 'uploads/productos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $filetype;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['imagen_producto']['tmp_name'], $destination)) {
                // Si es edición, eliminar imagen anterior
                if ($producto_id) {
                    $stmt = $conn->prepare("SELECT imagen FROM productos WHERE id = ?");
                    $stmt->bind_param("i", $producto_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $old_data = $result->fetch_assoc();
                    
                    if ($old_data['imagen'] && file_exists($old_data['imagen'])) {
                        unlink($old_data['imagen']);
                    }
                }
                $imagen = $destination;
            }
        }
    }
    
    if ($producto_id) {
        // Actualizar producto existente
        if ($imagen) {
            $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, categoria_id=?, marca=?, modelo_compatible=?, precio_compra=?, precio_venta=?, stock=?, stock_minimo=?, imagen=?, codigo_barras=? WHERE id=?");
            $stmt->bind_param("ssissddiissi", $nombre, $descripcion, $categoria_id, $marca, $modelo, $precio_compra, $precio_venta, $stock, $stock_minimo, $imagen, $codigo_barras, $producto_id);
        } else {
            $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, categoria_id=?, marca=?, modelo_compatible=?, precio_compra=?, precio_venta=?, stock=?, stock_minimo=?, codigo_barras=? WHERE id=?");
            $stmt->bind_param("ssissddiisi", $nombre, $descripcion, $categoria_id, $marca, $modelo, $precio_compra, $precio_venta, $stock, $stock_minimo, $codigo_barras, $producto_id);
        }
    } else {
        // Insertar nuevo producto
        $stmt = $conn->prepare("INSERT INTO productos (nombre, descripcion, categoria_id, marca, modelo_compatible, precio_compra, precio_venta, stock, stock_minimo, imagen, codigo_barras) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissddiiss", $nombre, $descripcion, $categoria_id, $marca, $modelo, $precio_compra, $precio_venta, $stock, $stock_minimo, $imagen, $codigo_barras);
    }
    
    if ($stmt->execute()) {
        echo "<script>alert('" . ($producto_id ? "Producto actualizado" : "Producto agregado") . " correctamente'); window.location.href='inventario.php';</script>";
    }
    $stmt->close();
}

// Obtener productos
$busqueda = $_GET['buscar'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';

$sql = "SELECT p.*, c.nombre as categoria_nombre FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id WHERE 1=1";
$params = [];
$types = "";

if ($busqueda) {
    $sql .= " AND (p.nombre LIKE ? OR p.marca LIKE ? OR p.modelo_compatible LIKE ? OR p.codigo_barras LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params = [$busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param];
    $types = "ssss";
}

if ($categoria_filtro) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $categoria_filtro;
    $types .= "i";
}

$sql .= " ORDER BY p.fecha_registro DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$productos = $stmt->get_result();

// Obtener categorías
$categorias = $conn->query("SELECT * FROM categorias ORDER BY nombre");

// Obtener estadísticas
$stats = $conn->query("SELECT 
    COUNT(*) as total_productos,
    SUM(stock) as total_stock,
    SUM(CASE WHEN stock <= stock_minimo THEN 1 ELSE 0 END) as productos_bajo_stock,
    SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as productos_agotados
    FROM productos")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Sistema de Gestión</title>
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
            transform: translateY(-2px);
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
            border: 2px solid #667eea;
        }

        .user-name {
            color: #333;
            font-weight: 600;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
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
        }

        .toolbar-content {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-add {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: white;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .product-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
        }

        .product-info {
            padding: 15px;
        }

        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .product-category {
            font-size: 12px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .product-details {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        .product-price {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .price-label {
            font-size: 12px;
            color: #999;
        }

        .price-value {
            font-size: 18px;
            font-weight: 700;
            color: #2ecc71;
        }

        .product-stock {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .stock-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-ok {
            background: #d4edda;
            color: #155724;
        }

        .stock-bajo {
            background: #fff3cd;
            color: #856404;
        }

        .stock-agotado {
            background: #f8d7da;
            color: #721c24;
        }

        .product-actions {
            display: flex;
            gap: 8px;
        }

        .btn-edit, .btn-delete {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 12px;
            grid-column: 1/-1;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        /* Modal de producto */
        .modal-producto {
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

        .modal-producto.show {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-producto-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            position: relative;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .toolbar-content {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }

            .navbar {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="principal.php" class="btn-back">← Volver</a>
            <h1>📦 Inventario</h1>
        </div>
        <div class="user-info">
            <?php if ($imagen_perfil && file_exists($imagen_perfil)): ?>
                <img src="<?php echo htmlspecialchars($imagen_perfil); ?>" alt="Perfil" class="user-avatar">
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
            <h2>Gestión de Inventario</h2>
            <p>Administra todos los accesorios para celulares de tu tienda</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_productos']; ?></div>
                <div class="stat-label">Total Productos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_stock']; ?></div>
                <div class="stat-label">Unidades en Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #f39c12;"><?php echo $stats['productos_bajo_stock']; ?></div>
                <div class="stat-label">Stock Bajo</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #e74c3c;"><?php echo $stats['productos_agotados']; ?></div>
                <div class="stat-label">Agotados</div>
            </div>
        </div>

        <div class="toolbar">
            <div class="toolbar-content">
                <div class="search-box">
                    <form method="GET" style="margin: 0;">
                        <input type="text" name="buscar" placeholder="🔍 Buscar por nombre, marca, modelo o código..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </form>
                </div>
                
                <form method="GET" style="margin: 0;">
                    <select name="categoria" class="filter-select" onchange="this.form.submit()">
                        <option value="">Todas las categorías</option>
                        <?php while($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_filtro == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
                
                <button class="btn-add" onclick="openProductModal()">+ Agregar Producto</button>
            </div>
        </div>

        <div class="products-grid">
            <?php if ($productos->num_rows > 0): ?>
                <?php while($prod = $productos->fetch_assoc()): ?>
                    <div class="product-card">
                        <?php if ($prod['imagen'] && file_exists($prod['imagen'])): ?>
                            <img src="<?php echo htmlspecialchars($prod['imagen']); ?>" alt="<?php echo htmlspecialchars($prod['nombre']); ?>" class="product-image">
                        <?php else: ?>
                            <div class="product-image-placeholder">📱</div>
                        <?php endif; ?>
                        
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                            <div class="product-category"><?php echo htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría'); ?></div>
                            
                            <?php if ($prod['marca'] || $prod['modelo_compatible']): ?>
                                <div class="product-details">
                                    <?php if ($prod['marca']): ?>
                                        <strong>Marca:</strong> <?php echo htmlspecialchars($prod['marca']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($prod['modelo_compatible']): ?>
                                        <strong>Compatible:</strong> <?php echo htmlspecialchars($prod['modelo_compatible']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-price">
                                <div>
                                    <div class="price-label">Precio Venta</div>
                                    <div class="price-value">$<?php echo number_format($prod['precio_venta'], 2); ?></div>
                                </div>
                            </div>
                            
                            <div class="product-stock">
                                <?php 
                                $stock_class = 'stock-ok';
                                $stock_text = 'Stock: ' . $prod['stock'];
                                if ($prod['stock'] == 0) {
                                    $stock_class = 'stock-agotado';
                                    $stock_text = 'Agotado';
                                } elseif ($prod['stock'] <= $prod['stock_minimo']) {
                                    $stock_class = 'stock-bajo';
                                    $stock_text = 'Stock bajo: ' . $prod['stock'];
                                }
                                ?>
                                <span class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_text; ?></span>
                            </div>
                            
                            <div class="product-actions">
                                <button class="btn-edit" onclick='editProduct(<?php echo json_encode($prod); ?>)'>✏️ Editar</button>
                                <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('¿Estás seguro de eliminar este producto?');">
                                    <input type="hidden" name="producto_id" value="<?php echo $prod['id']; ?>">
                                    <button type="submit" name="eliminar_producto" class="btn-delete">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay productos</h3>
                    <p>Agrega tu primer producto al inventario</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Producto -->
    <div id="productoModal" class="modal-producto">
        <div class="modal-producto-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 id="modalTitle" style="margin: 0;">Agregar Producto</h2>
                <span class="close" onclick="closeProductModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="producto_id" id="producto_id">
                
                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Nombre del Producto *</label>
                        <input type="text" name="nombre_producto" id="nombre_producto" required>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Descripción</label>
                        <textarea name="descripcion_producto" id="descripcion_producto" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Categoría *</label>
                        <select name="categoria_id" id="categoria_id" required>
                            <option value="">Seleccionar...</option>
                            <?php 
                            $categorias->data_seek(0);
                            while($cat = $categorias->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Marca</label>
                        <input type="text" name="marca" id="marca">
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Modelo Compatible</label>
                        <input type="text" name="modelo_compatible" id="modelo_compatible" placeholder="Ej: iPhone 13, Samsung Galaxy S21">
                    </div>
                    
                    <div class="form-group">
                        <label>Precio Compra *</label>
                        <input type="number" step="0.01" name="precio_compra" id="precio_compra" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Precio Venta *</label>
                        <input type="number" step="0.01" name="precio_venta" id="precio_venta" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Stock *</label>
                        <input type="number" name="stock" id="stock" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Stock Mínimo *</label>
                        <input type="number" name="stock_minimo" id="stock_minimo" value="5" required>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Código de Barras</label>
                        <input type="text" name="codigo_barras" id="codigo_barras">
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Imagen del Producto</label>
                        <input type="file" name="imagen_producto" id="imagen_producto" accept="image/*">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="guardar_producto" class="btn" style="flex: 1;">Guardar Producto</button>
                    <button type="button" onclick="closeProductModal()" class="btn btn-secondary" style="flex: 1;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openProductModal() {
        document.getElementById('modalTitle').textContent = 'Agregar Producto';
        document.querySelector('form').reset();
        document.getElementById('producto_id').value = '';
        document.getElementById('productoModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeProductModal() {
        document.getElementById('productoModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function editProduct(product) {
        document.getElementById('modalTitle').textContent = 'Editar Producto';
        document.getElementById('producto_id').value = product.id;
        document.getElementById('nombre_producto').value = product.nombre;
        document.getElementById('descripcion_producto').value = product.descripcion || '';
        document.getElementById('categoria_id').value = product.categoria_id || '';
        document.getElementById('marca').value = product.marca || '';
        document.getElementById('modelo_compatible').value = product.modelo_compatible || '';
        document.getElementById('precio_compra').value = product.precio_compra;
        document.getElementById('precio_venta').value = product.precio_venta;
        document.getElementById('stock').value = product.stock;
        document.getElementById('stock_minimo').value = product.stock_minimo;
        document.getElementById('codigo_barras').value = product.codigo_barras || '';
        
        openProductModal();
    }

    window.onclick = function(event) {
        const modal = document.getElementById('productoModal');
        if (event.target === modal) {
            closeProductModal();
        }
    }
    </script>
</body>
</html>