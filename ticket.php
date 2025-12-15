<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

// Obtener datos del ticket
$ticket_data = json_decode(urldecode($_GET['data']), true);

if (!$ticket_data) {
    header("Location: ventas.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Venta - <?php echo $ticket_data['folio']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #f5f6fa;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .actions {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .ticket {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: 0 auto;
        }

        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .ticket-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .ticket-subtitle {
            font-size: 14px;
            color: #666;
        }

        .ticket-info {
            margin-bottom: 15px;
            font-size: 13px;
            line-height: 1.6;
        }

        .ticket-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .label {
            font-weight: bold;
        }

        .products-section {
            border-top: 2px dashed #333;
            border-bottom: 2px dashed #333;
            padding: 15px 0;
            margin: 15px 0;
        }

        .product-item {
            margin-bottom: 10px;
            font-size: 13px;
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .product-details {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 12px;
        }

        .totals-section {
            margin: 15px 0;
            font-size: 13px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .total-final {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }

        .payment-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 13px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .payment-row.highlight {
            font-size: 16px;
            font-weight: bold;
            color: #2ecc71;
        }

        .ticket-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px dashed #333;
            font-size: 12px;
            color: #666;
        }

        .thank-you {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .actions {
                display: none;
            }

            .ticket {
                box-shadow: none;
                border: none;
                max-width: 80mm;
                padding: 10px;
            }

            .ticket-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="actions">
            <button onclick="window.print()" class="btn btn-print">
                🖨️ Imprimir Ticket
            </button>
            <a href="ventas.php" class="btn btn-back">
                ← Volver a Ventas
            </a>
        </div>

        <div class="ticket" id="ticket">
            <div class="ticket-header">
                <div class="ticket-title">TICKET DE VENTA</div>
                <div class="ticket-subtitle">Accesorios para Celulares</div>
            </div>

            <div class="ticket-info">
                <div class="ticket-info-row">
                    <span class="label">Folio:</span>
                    <span><?php echo $ticket_data['folio']; ?></span>
                </div>
                <div class="ticket-info-row">
                    <span class="label">Fecha:</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($ticket_data['fecha'])); ?></span>
                </div>
                <div class="ticket-info-row">
                    <span class="label">Vendedor:</span>
                    <span><?php echo htmlspecialchars($ticket_data['vendedor']); ?></span>
                </div>
                <?php if (!empty($ticket_data['cliente_nombre'])): ?>
                <div class="ticket-info-row">
                    <span class="label">Cliente:</span>
                    <span><?php echo htmlspecialchars($ticket_data['cliente_nombre']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($ticket_data['cliente_telefono'])): ?>
                <div class="ticket-info-row">
                    <span class="label">Teléfono:</span>
                    <span><?php echo htmlspecialchars($ticket_data['cliente_telefono']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="products-section">
                <div class="product-header" style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                    <span>PRODUCTO</span>
                    <span>TOTAL</span>
                </div>
                <?php foreach ($ticket_data['productos'] as $producto): ?>
                <div class="product-item">
                    <div class="product-header">
                        <span><?php echo htmlspecialchars($producto['nombre']); ?></span>
                        <span>$<?php echo number_format($producto['subtotal'], 2); ?></span>
                    </div>
                    <div class="product-details">
                        <span><?php echo $producto['cantidad']; ?> x $<?php echo number_format($producto['precio'], 2); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="totals-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>$<?php echo number_format($ticket_data['subtotal'], 2); ?></span>
                </div>
                <?php if ($ticket_data['descuento'] > 0): ?>
                <div class="total-row" style="color: #e74c3c;">
                    <span>Descuento:</span>
                    <span>-$<?php echo number_format($ticket_data['descuento'], 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row total-final">
                    <span>TOTAL:</span>
                    <span>$<?php echo number_format($ticket_data['total'], 2); ?></span>
                </div>
            </div>

            <div class="payment-section">
                <div class="payment-row">
                    <span class="label">Método de Pago:</span>
                    <span><?php echo ucfirst($ticket_data['metodo_pago']); ?></span>
                </div>
                
                <?php if ($ticket_data['metodo_pago'] === 'efectivo' && $ticket_data['pago_con'] > 0): ?>
                <div class="payment-row">
                    <span>Pago con:</span>
                    <span>$<?php echo number_format($ticket_data['pago_con'], 2); ?></span>
                </div>
                <div class="payment-row highlight">
                    <span>Su cambio:</span>
                    <span>$<?php echo number_format($ticket_data['cambio'], 2); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="ticket-footer">
                <div class="thank-you">¡GRACIAS POR SU COMPRA!</div>
                <p>Este ticket es su comprobante de compra</p>
                <p>Conserve este ticket para cambios y garantías</p>
                <p style="margin-top: 10px; font-size: 11px;">
                    <?php echo date('d/m/Y H:i:s'); ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-imprimir al cargar (opcional, puedes comentar esta línea)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>