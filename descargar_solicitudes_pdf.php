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

//COFIGURACION CRITICA PARA EL FORMATO HORIZONTAL
//Se pasa 'L' (Landscape) al constructor de FPDF
$pdf = new PDF('L','mm','A4');
$pdf->SetMargins(10,10,10);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

//Constantes de diseño para el nuevo formato horizontal
$ancho_util = 277; //Ancho util en mm para A4 horizontal con margenes de 10mm
$margin_x = 10;
$pdf->SetX($margin_x);

// ===========================================
// == CABERCERA SUPERIOR (LOGO Y TITULO) ==
// ===========================================
$y_start = 10;
$pdf->SetY($y_start);

$logo_url = 'https://i.ibb.co/5fT8GF9/LOGO-NCS.jpg';

//DIMENSIONES AJUSTADS PARA LANDSCAPE
$w_logo = 17;
$h_logo_max = 15; //Altura máxima permitida
$w_nombre_empresa = $ancho_util - $w_logo;

//Celda Logo (30mm ancho)
$pdf->Rect($margin_x, $y_start, $w_logo, $h_logo_max);

$pdf->Image($logo_url, $margin_x + 0.5, $y_start + 1, $w_logo - 1, 13);

//Celda vacía para mover el cursor X (usa la altura 'h' = 15)
$pdf->Cell($w_logo,15,'',0,0,'C');

//Celda Nombre Empresa (247mm ancho)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell($w_nombre_empresa, 7.5, 'SEMILLEROS PARA EL FUTURO S.A.S', 1, 1, 'C');
$pdf->SetX($margin_x + $w_logo);
$pdf->SetFont('Arial', 'B', 10);    
$pdf->Cell($w_nombre_empresa, 7.5, utf8_decode('SOLICITUD DE SUMINISTROS'),1,1, 'C');

// --------------------------------------------------------
// -- INFO SUPERIOR (Fecha, Área, Solicitante) AJUSTADO --
// --------------------------------------------------------
$pdf-> SetY($y_start + 15);
$pdf->SetX($margin_x);
$pdf->SetFont('Arial', 'B', 8);

// Distribución proporcional para ocupar los 277mm
$w_label = 20;
$w_value_fecha = 72;
$w_value_area = 72;
$w_value_solicitante = 73;

$pdf->Cell($w_label, 5, 'FECHA:', 'TL', 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell($w_value_fecha, 5,$fecha_solicitud, 'TR', 0, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($w_label, 5, utf8_decode('AREA:'), 'TL', 0,'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell($w_value_area, 5, $area_solicitante, 'TR', 0, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($w_label, 5, 'SOLICITANTE:', 'TL', 0,'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell($w_value_solicitante, 5, $email_solicitante, 'TR', 1, 'L');

// ---------------------------------------------
// -- CABECERA DE LA TABLA DE ITEMS AJUSTADA --
// ---------------------------------------------

$pdf->SetX($margin_x);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230,230,230);

// Anchos de Columna ajustadas para 277mm total
$w_codigo = 15; //N° Item
$w_descripcion_item = 192; //Producto
$w_cantidad = 30; //Cantidad
$w_observaciones = 40; //Observaciones
// Total: 15 +192 + 30 + 40 = 277mm

$pdf->Cell($w_codigo, 7, 'CODIGO', 1, 0, 'C', true);
$pdf->Cell($w_descripcion_item, 7, 'PRODUCTO', 1, 0, 'C', true);
$pdf->Cell($w_cantidad, 7, 'CANTIDAD',1,0, 'C', true);
$pdf->Cell($w_observaciones, 7, 'OBSERVACIONES', 1,1,'C', true);

// ----------------------------------------------
// 5. LISTAR LOS PRODUCTOS SOLICITADOS (Máximo 15 filas)
// ----------------------------------------------
$pdf->SetFont('Arial', '', 8);
$altura_fila = 5;
$max_filas = 15; //Maximo Filas
$item_num = 1;

for ($i = 0; $i < $max_filas; $i++) {
    $pdf->SetX($margin_x);

    //USAR LA VARIABLE CORRECTA
    $producto = $productos_list[$i] ?? null;

    //Columna N° ITEM (número de ítem)
    $codigo_producto = $producto ? htmlspecialchars($producto['codigo']) : '';
    $pdf->Cell($w_codigo, $altura_fila, $codigo_producto, 1, 0, 'C');
    

    //Columna PRODUCTO (Nombre + Codigo)
    $descripcion_texto = '';
    if ($producto) {
        $descripcion_texto = htmlspecialchars($producto['nombre']); 
    }
    $pdf->Cell($w_descripcion_item, $altura_fila, $descripcion_texto, 1,0,'L');

    //Columa CANTIDAD
    $cantidad_texto = $producto ? htmlspecialchars($producto['cantidad']) : '';
    $pdf->Cell($w_cantidad, $altura_fila, $cantidad_texto, 1, 0, 'C');

    //Columna OBSERVACIONES (vacía en el reporte)
    $pdf->Cell($w_observaciones, $altura_fila, '', 1, 1, 'L');

    if ($producto) {
        $item_num++;
    }
}

// ----------------------------------------------
// -- SECCIÓN DE OBSERVACIONES GENERALES AJUSTADA --
// ----------------------------------------------
    /* $pdf->Ln(2); 
    $pdf->SetX($margin_x);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($ancho_util, 5, 'OBSERVACIONES', 'TLR', 1, 'L', true);
    $pdf->SetX($margin_x);
    $pdf->SetFont('Arial', '', 8) //
    // Espacio para observaciones (15mm de altura)
    $pdf->Cell($ancho_util, 15, '', 'BLR', 1, 'L')*/

// ------------------------------------------
// -- SECCIÓN DE FIRMAS Y DATOS FINALES AJUSTADA --
// ------------------------------------------
$pdf->Ln(2);

//Anchos de cada columna de firma: 277 / 3 = 92.33
$w_col_firma_l = 92.3; //Left
$w_col_firma_c = 92.3; //Center
$w_col_firma_r = 92.4; //Rigth

// Fila de Encabazados
$pdf->SetX($margin_x);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_col_firma_l, 5, 'Elaborado por:', 1, 0, 'C', true);
$pdf->Cell($w_col_firma_c, 5, 'ENTREGA:', 1, 0, 'C', true);
$pdf->Cell($w_col_firma_c, 5, 'RECIBE:', 1, 1, 'C', true);

// Anchos internos de las celdas de firma
$w_label_firma = 15;
$w_value_firma_l = $w_col_firma_l - $w_label_firma; //77.3mm
$w_value_firma_c = $w_col_firma_c - 25; // FECHA DE ENTRAGA (25mm)
$w_value_firma_r = $w_col_firma_r - $w_label_firma;

// Fila NOMBRE:
$pdf-> SetX($margin_x);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_label_firma, 5, 'NOMBRE:', 'L', 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell($w_value_firma_l, 5, 'Elaborado por Elcy Duarte', 'R', 0, 'L');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_label_firma, 5, 'NOMBRE:', 'L', 0, 'L');
$pdf->Cell($w_value_firma_l, 5, '', 'R', 0, 'L');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_label_firma, 5, 'NOMBRE:', 'L', 0, 'L');
$pdf->Cell($w_value_firma_l, 5, '', 'R', 1, 'L');

// Fila CARGO / FECHADE ENTREGA / FRIMA
$pdf->SetX($margin_x);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_label_firma, 5, 'CARGO:', 'LB', 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell($w_value_firma_l, 5, 'Jefe De Mantenimiento', 'RB', 0, 'L'); //Cargo Fijo

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25,5, 'FECHA DE ENTREGA:', 'LB', 0, 'L'); //25mm e ancho
$pdf->Cell($w_value_firma_c,5, '', 'RB', 0, 'L'); //Espacio para la fecha de entrega

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($w_label_firma, 5, 'FIRMA:', 'LB', 0, 'L');
$pdf->Cell($w_value_firma_r, 5, '', 'RB', 1, 'L'); // Espacio para Firma

//SALIDA DEL PDF
$pdf->Output('D', 'Solicitud_Suministros_' . $solicitud_id . '_' . date('Ymd'). '.pdf');
exit;
?>