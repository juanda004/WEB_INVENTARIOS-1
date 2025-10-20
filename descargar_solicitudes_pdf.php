<?php
//descargar_solicitudes_pdf.php
//Genera un archivo PDF con la lista de solicitudes

session_start();
require_once 'includes/db.php';
require_once 'includes/db_users.php';
require_once 'includes/fdpf/fpdf.php'; //Conexión a la base de datos y librería FPDF

//Redirigir si el usuario no es administrador
$user_role = $_SESSION['user_role'] ?? 'admin';

if ($user_role !== 'admin') {
    header("Location: index.php");
    exit();
}

$solicitud_id = $_GET ['id'] ?? 0;
if (!$solicitud_id) {
    die ("ID de solicitud no proporcionado.");
}

//Obtener datos de la solicitud
try {
    //Consultar para obtener la solicitud específica
    $stmt = $pdo->prepare("
    SELECT s.*, u.email
    FROM inventario_db.solicitudes s
    JOIN db_users.users u ON s.user_id = u.id
    WHERE s.id = ?
    ");
    $stmt ->execute([$solicitud_id]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die ("Error al obtener la solicitud: " . $e->getMessage());
}

if (!$solicitud) {
    die ("Solicitud no encontrada.");
}

//Extracción y Mapeo de DATOS
$productos_solicitados = json_decode($solicitud['productos_solicitados'],true);
//Extraer solo la fecha
$fecha_solicitud = date('d/m/Y', strtotime($solicitud['fecha_solicitud']));
$area_solicitante = htmlspecialchars($solicitud['area_solicitante']);
$email_solicitante = htmlspecialchars($solicitud['email']);
$productos_list = is_array($productos_solicitados) ? $productos_solicitados : [];

class PDF extends FPDF {
    //El formato de la plantilla es manual, no necesitamos Header ni Footer estándar
}

//Configuración Inicial del PDf
$pdf = new PDF();
$pdf->SetMargins(10,10,10);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

//Diseño del PDF Y Carga Datos

// -- CABECERA SUPERIOR (Logo Y Nombre de la Empresa)
$y_start = 10;
$pdf->SetY($y_start);

// Celda Logo (30mm ancho)
$pdf->Rect(10, $y_start, 30, 15); 
$pdf->Cell(30, 15, '', 1, 0, 'C'); // Espacio para logo NCS

// Celda Nombre Empresa (170mm ancho)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(170, 7.5, 'SEMILLEROS PARA EL FUTURO S.A.S', 1, 1, 'C');
$pdf->SetX(40); // Mover X después de la celda del logo
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(170, 7.5, utf8_decode('SOLICITUD DE SUMINISTROS'), 1, 1, 'C');

// -- INFO SUPERIOR (Fecha, Área, Solicitante)
$pdf->SetY($y_start + 15); 
$pdf->SetFont('Arial', 'B', 8);

$pdf->Cell(20, 5, 'FECHA:', 'TL', 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(30, 5, $fecha_solicitud, 'TR', 0, 'L'); // CARGA SOLO FECHA

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(20, 5, utf8_decode('ÁREA:'), 'TL', 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(60, 5, $area_solicitante, 'TR', 0, 'L'); // CARGA ÁREA

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(20, 5, 'SOLICITANTE:', 'TL', 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(50, 5, $email_solicitante, 'TR', 1, 'L'); // CARGA USUARIO

// -- CABECERA DE LA TABLA DE ITEMS
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(15, 7, 'ITEM', 1, 0, 'C', true); // Columna ITEM
$pdf->Cell(115, 7, utf8_decode('DESCRIPCIÓN'), 1, 0, 'C', true); // Columna DESCRIPCION
$pdf->Cell(30, 7, 'CANTIDAD', 1, 0, 'C', true); // Columna CANTIDAD
$pdf->Cell(40, 7, 'OBSERVACIONES', 1, 1, 'C', true);

// 5. LISTAR LOS PRODUCTOS SOLICITADOS (Máximo 15 filas)
$pdf->SetFont('Arial', '', 8);
$altura_fila = 5; 
$max_filas = 15;
$item_num = 1;

for ($i = 0; $i < $max_filas; $i++) {
    
    $producto = $productos_a_listar[$i] ?? null;

    // Columna ITEM (Número de ítem)
    $pdf->Cell(15, $altura_fila, $producto ? $item_num : '', 1, 0, 'C');

    // Columna DESCRIPCION (Nombre + Codigo)
    $descripcion_texto = '';
    if ($producto) {
        // Mapea la DESCRIPCION con Nombre y Código
        $descripcion_texto = utf8_decode(htmlspecialchars($producto['nombre']) . " (" . htmlspecialchars($producto['codigo']) . ")");
    }
    $pdf->Cell(115, $altura_fila, $descripcion_texto, 1, 0, 'L');

    // Columna CANTIDAD
    $cantidad_texto = $producto ? htmlspecialchars($producto['cantidad']) : ''; // Mapea la CANTIDAD
    $pdf->Cell(30, $altura_fila, $cantidad_texto, 1, 0, 'C');

    // Columna OBSERVACIONES (vacía en el reporte)
    $pdf->Cell(40, $altura_fila, '', 1, 1, 'L'); 
    
    if ($producto) {
        $item_num++;
    }
}


// -- SECCIÓN DE OBSERVACIONES GENERALES
$pdf->Ln(2); 
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, 'OBSERVACIONES:', 'TLR', 1, 'L', true);
$pdf->SetFont('Arial', '', 8);
// Espacio para observaciones (15mm de altura)
$pdf->Cell(0, 15, '', 'BLR', 1, 'L'); 


// -- SECCIÓN DE FIRMAS Y DATOS FINALES
$pdf->Ln(2);

// Fila de Encabezados
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(66.6, 5, 'Elaborado por:', 1, 0, 'C', true);
$pdf->Cell(66.6, 5, 'ENTREGA:', 1, 0, 'C', true);
$pdf->Cell(66.8, 5, 'RECIBE:', 1, 1, 'C', true);

// Fila NOMBRE:
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(15, 5, 'NOMBRE:', 'L', 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(51.6, 5, utf8_decode('Elaborado por (Admin)'), 'R', 0, 'L'); 

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(15, 5, 'NOMBRE:', 'L', 0, 'L');
$pdf->Cell(51.6, 5, '', 'R', 0, 'L'); 

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(15, 5, 'NOMBRE:', 'L', 0, 'L');
$pdf->Cell(51.8, 5, '', 'R', 1, 'L'); 

// Fila CARGO / FECHA DE ENTREGA / FIRMA
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(15, 5, 'CARGO:', 'LB', 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(51.6, 5, utf8_decode('Jefe de Mantenimiento'), 'RB', 0, 'L'); // Cargo fijo

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 5, 'FECHA DE ENTREGA:', 'LB', 0, 'L');
$pdf->Cell(41.6, 5, '', 'RB', 0, 'L'); // Espacio para la fecha de entrega

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(15, 5, 'FIRMA:', 'LB', 0, 'L');
$pdf->Cell(51.8, 5, '', 'RB', 1, 'L'); // Espacio para firma


// SALIDA DEL PDF
$pdf->Output('D', 'Solicitud_Suministros_' . $solicitud_id . '_' . date('Ymd') . '.pdf');
exit;
?>