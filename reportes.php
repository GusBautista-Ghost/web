<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

// Verificar si el usuario es administrador
if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'administrador') {
    echo "<script>alert('Acceso denegado. Solo administradores pueden acceder a los reportes.'); window.location.href='principal.php';</script>";
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

// Estadísticas generales
$stats_ventas = $conn->query("SELECT 
    COUNT(*) as total_ventas,
    COALESCE(SUM(total), 0) as total_ingresos,
    COALESCE(AVG(total), 0) as ticket_promedio
    FROM ventas")->fetch_assoc();

$stats_productos = $conn->query("SELECT 
    COUNT(*) as total_productos,
    SUM(stock) as total_stock,
    SUM(CASE WHEN stock <= stock_minimo THEN 1 ELSE 0 END) as bajo_stock
    FROM productos")->fetch_assoc();

$stats_garantias = $conn->query("SELECT 
    COUNT(*) as total_garantias,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
    FROM garantias")->fetch_assoc();

$stats_devoluciones = $conn->query("SELECT 
    COUNT(*) as total_devoluciones,
    COALESCE(SUM(monto_devuelto), 0) as monto_total
    FROM devoluciones")->fetch_assoc();

// Ventas por día (últimos 30 días)
$ventas_mes = $conn->query("SELECT 
    DATE(fecha_venta) as fecha,
    COUNT(*) as cantidad,
    SUM(total) as total
    FROM ventas 
    WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(fecha_venta)
    ORDER BY fecha ASC");

// Productos más vendidos
$productos_top = $conn->query("SELECT 
    p.nombre,
    SUM(vd.cantidad) as total_vendido,
    SUM(vd.subtotal) as ingresos
    FROM ventas_detalle vd
    JOIN productos p ON vd.producto_id = p.id
    GROUP BY vd.producto_id
    ORDER BY total_vendido DESC
    LIMIT 10");

// Ventas por método de pago
$ventas_metodo = $conn->query("SELECT 
    metodo_pago,
    COUNT(*) as cantidad,
    SUM(total) as total
    FROM ventas
    GROUP BY metodo_pago");

// Ventas por categoría
$ventas_categoria = $conn->query("SELECT 
    c.nombre as categoria,
    COUNT(DISTINCT v.id) as num_ventas,
    SUM(vd.subtotal) as total
    FROM ventas_detalle vd
    JOIN ventas v ON vd.venta_id = v.id
    JOIN productos p ON vd.producto_id = p.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    GROUP BY c.id
    ORDER BY total DESC
    LIMIT 8");

// Preparar datos para gráficas
$ventas_fechas = [];
$ventas_totales = [];
while($vm = $ventas_mes->fetch_assoc()) {
    $ventas_fechas[] = date('d/m', strtotime($vm['fecha']));
    $ventas_totales[] = floatval($vm['total']);
}

$productos_nombres = [];
$productos_cantidades = [];
$productos_top->data_seek(0);
while($pt = $productos_top->fetch_assoc()) {
    $productos_nombres[] = $pt['nombre'];
    $productos_cantidades[] = intval($pt['total_vendido']);
}

$metodos_nombres = [];
$metodos_totales = [];
while($vm = $ventas_metodo->fetch_assoc()) {
    $metodos_nombres[] = ucfirst($vm['metodo_pago']);
    $metodos_totales[] = floatval($vm['total']);
}

$categorias_nombres = [];
$categorias_totales = [];
while($vc = $ventas_categoria->fetch_assoc()) {
    $categorias_nombres[] = $vc['categoria'] ?? 'Sin categoría';
    $categorias_totales[] = floatval($vc['total']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Gestión</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #30cfd0;
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #999;
            font-size: 13px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .chart-container.tall {
            height: 400px;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="principal.php" class="btn-back">← Volver</a>
            <h1>📊 Reportes</h1>
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
            <h2>Panel de Reportes y Análisis</h2>
            <p>Visualiza el rendimiento de tu negocio con gráficas interactivas</p>
        </div>

        <!-- Resumen General -->
        <h3 style="color: #333; margin-bottom: 20px; font-size: 24px;">📈 Resumen General</h3>
        <div class="stats-overview">
            <div class="stat-card">
                <h3>💰 Ventas Totales</h3>
                <div class="stat-value"><?php echo $stats_ventas['total_ventas']; ?></div>
                <div class="stat-label">Transacciones realizadas</div>
            </div>

            <div class="stat-card">
                <h3>💵 Ingresos Totales</h3>
                <div class="stat-value">$<?php echo number_format($stats_ventas['total_ingresos'], 2); ?></div>
                <div class="stat-label">Desde el inicio</div>
            </div>

            <div class="stat-card">
                <h3>📦 Inventario</h3>
                <div class="stat-value"><?php echo $stats_productos['total_productos']; ?></div>
                <div class="stat-label"><?php echo $stats_productos['total_stock']; ?> unidades en stock</div>
            </div>

            <div class="stat-card">
                <h3>💳 Ticket Promedio</h3>
                <div class="stat-value">$<?php echo number_format($stats_ventas['ticket_promedio'], 2); ?></div>
                <div class="stat-label">Por transacción</div>
            </div>
        </div>

        <!-- Gráficas -->
        <h3 style="color: #333; margin: 30px 0 20px 0; font-size: 24px;">📊 Análisis Visual</h3>
        <div class="charts-grid">
            <!-- Ventas por Día -->
            <div class="chart-card full-width">
                <h3>📅 Tendencia de Ventas (Últimos 30 Días)</h3>
                <div class="chart-container tall">
                    <canvas id="ventasChart"></canvas>
                </div>
            </div>

            <!-- Top Productos -->
            <div class="chart-card">
                <h3>🏆 Top 10 Productos Más Vendidos</h3>
                <div class="chart-container tall">
                    <canvas id="productosChart"></canvas>
                </div>
            </div>

            <!-- Ventas por Categoría -->
            <div class="chart-card">
                <h3>📦 Ventas por Categoría</h3>
                <div class="chart-container tall">
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>

            <!-- Métodos de Pago -->
            <div class="chart-card">
                <h3>💳 Distribución de Métodos de Pago</h3>
                <div class="chart-container">
                    <canvas id="metodosChart"></canvas>
                </div>
            </div>

            <!-- Comparativa -->
            <div class="chart-card">
                <h3>📊 Comparativa del Negocio</h3>
                <div class="chart-container">
                    <canvas id="comparativaChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuración global de Chart.js
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#666';

        // Gráfica de Ventas por Día (Línea)
        const ventasCtx = document.getElementById('ventasChart').getContext('2d');
        new Chart(ventasCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($ventas_fechas); ?>,
                datasets: [{
                    label: 'Ventas (MXN)',
                    data: <?php echo json_encode($ventas_totales); ?>,
                    borderColor: '#30cfd0',
                    backgroundColor: 'rgba(48, 207, 208, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#30cfd0',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function(context) {
                                return 'Total: $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Gráfica de Productos (Barra Horizontal)
        const productosCtx = document.getElementById('productosChart').getContext('2d');
        new Chart(productosCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($productos_nombres); ?>,
                datasets: [{
                    label: 'Unidades Vendidas',
                    data: <?php echo json_encode($productos_cantidades); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(245, 87, 108, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(0, 242, 254, 0.8)',
                        'rgba(250, 112, 154, 0.8)',
                        'rgba(254, 225, 64, 0.8)',
                        'rgba(48, 207, 208, 0.8)',
                        'rgba(51, 8, 103, 0.8)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Gráfica de Categorías (Dona)
        const categoriasCtx = document.getElementById('categoriasChart').getContext('2d');
        new Chart(categoriasCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categorias_nombres); ?>,
                datasets: [{
                    data: <?php echo json_encode($categorias_totales); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#764ba2',
                        '#f093fb',
                        '#f5576c',
                        '#4facfe',
                        '#00f2fe',
                        '#fa709a',
                        '#fee140'
                    ],
                    borderColor: '#fff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': $' + context.parsed.toFixed(2) + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Gráfica de Métodos de Pago (Pastel)
        const metodosCtx = document.getElementById('metodosChart').getContext('2d');
        new Chart(metodosCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($metodos_nombres); ?>,
                datasets: [{
                    data: <?php echo json_encode($metodos_totales); ?>,
                    backgroundColor: [
                        '#2ecc71',
                        '#3498db',
                        '#9b59b6'
                    ],
                    borderColor: '#fff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 13
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': $' + context.parsed.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        // Gráfica Comparativa (Barra)
        const comparativaCtx = document.getElementById('comparativaChart').getContext('2d');
        new Chart(comparativaCtx, {
            type: 'bar',
            data: {
                labels: ['Ventas', 'Garantías', 'Devoluciones', 'Stock Bajo'],
                datasets: [{
                    label: 'Cantidad',
                    data: [
                        <?php echo $stats_ventas['total_ventas']; ?>,
                        <?php echo $stats_garantias['total_garantias']; ?>,
                        <?php echo $stats_devoluciones['total_devoluciones']; ?>,
                        <?php echo $stats_productos['bajo_stock']; ?>
                    ],
                    backgroundColor: [
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                        'rgba(243, 156, 18, 0.8)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>