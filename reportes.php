<?php
// reportes.php
// Genera un reporte de entrada y salida de productos en formato PDF.

require_once 'includes/db.php';
require_once 'includes/header.php';

$mensaje = '';
// Obtener todas las categorías para el selector del formulario
try {
    $stmt = $pdo->query("SELECT nombre_categoria FROM categorias");
    $categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>Error al cargar las categorías: " . $e->getMessage() . "</p>";
    $categorias = [];
}

// Lógica de procesamiento cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_reporte'])) {
    $categoria_seleccionada = $_POST['categoria'];
    $movimiento_seleccionado = $_POST['movimiento'];

    if (empty($categoria_seleccionada) || empty($movimiento_seleccionado)) {
        $mensaje = "<p class='btn-danger'>Por favor, seleccione una categoría y un tipo de movimiento.</p>";
    } else {
        try {
            // Construir la consulta SQL para la tabla 'movimientos'
            $sql = "SELECT codigo_producto, cantidad, fecha_movimiento, tipo_movimiento FROM `movimientos` WHERE categoria = ?";
            $params = [$categoria_seleccionada];

            if ($movimiento_seleccionado !== 'todos') {
                $sql .= " AND tipo_movimiento = ?";
                $params[] = $movimiento_seleccionado;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Crear el PDF usando FPDF
            class PDF extends FPDF {
                function Header() {
                    $this->SetFont('Arial', 'B', 15);
                    $this->Cell(0, 10, 'Reporte de Movimientos de Inventario', 0, 1, 'C');
                    $this->Ln(10);
                }

                function Footer() {
                    $this->SetY(-15);
                    $this->SetFont('Arial', 'I', 8);
                    $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
                }

                function reporteTable($header, $data) {
                    // Cabecera de la tabla
                    $this->SetFont('Arial', 'B', 10);
                    $w = array(40, 40, 40, 40);
                    for ($i = 0; $i < count($header); $i++) {
                        $this->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
                    }
                    $this->Ln();

                    // Datos de la tabla
                    $this->SetFont('Arial', '', 10);
                    foreach ($data as $row) {
                        $this->Cell($w[0], 6, utf8_decode($row['codigo_producto']), 1);
                        $this->Cell($w[1], 6, $row['cantidad'], 1, 0, 'R');
                        $this->Cell($w[2], 6, utf8_decode($row['tipo_movimiento']), 1);
                        $this->Cell($w[3], 6, $row['fecha_movimiento'], 1);
                        $this->Ln();
                    }
                }
            }

            $pdf = new PDF();
            $pdf->AliasNbPages();
            $pdf->AddPage();
            
            // Título del reporte
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Categoría: ' . utf8_decode($categoria_seleccionada), 0, 1);
            $pdf->Cell(0, 10, 'Movimiento: ' . utf8_decode($movimiento_seleccionado), 0, 1);
            $pdf->Ln(5);

            // Generar tabla
            $header = array('Código Producto', 'Cantidad', 'Tipo Movimiento', 'Fecha');
            $pdf->reporteTable($header, $movimientos);
            $pdf->Ln(20);

            // Espacio para firmas
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(90, 10, utf8_decode('Entregado por: ____________________________'), 0, 0);
            $pdf->Cell(0, 10, utf8_decode('Recibido por: ____________________________'), 0, 1);
            $pdf->Ln(5);
            $pdf->Cell(90, 10, utf8_decode('Firma y Fecha'), 0, 0, 'C');
            $pdf->Cell(0, 10, utf8_decode('Firma y Fecha'), 0, 1, 'C');

            // Salida del PDF
            $pdf->Output('D', 'reporte_inventario_' . $categoria_seleccionada . '.pdf');
            exit;

        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>Error al generar el reporte: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<div class="container mt-5">
    <h2>Generar Reporte de Movimientos</h2>
    <?php echo $mensaje; ?>
    <form action="reportes.php" method="POST">
        <div class="form-group">
            <label for="categoria">Seleccionar Categoría:</label>
            <select class="form-control" id="categoria" name="categoria" required>
                <option value="">Seleccione una Categoria</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo htmlspecialchars(ucfirst($cat)); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="movimiento">Tipo de Movimiento:</label>
            <select class="form-control" id="movimiento" name="movimiento" required>
                <option value="todos">Todos</option>
                <option value="entrada">Entrada</option>
                <option value="salida">Salida</option>
            </select>
        </div>
        <button type="submit" name="generar_reporte" class="btn btn-primary">Generar y Descargar Reporte</button>
    </form>
</div>
