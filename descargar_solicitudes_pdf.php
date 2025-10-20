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

//DIMENSIONES AJUSTADS PARA LANDSCAPE
$w_logo = 30;
$w_nombre_empresa = $ancho_util - $w_logo;

//Celda Logo (30mm ancho)
$pdf->React($margin_x, $y_start, $w_logo, 15);
$pdf->Cell($w_logo,15,'',1,0,'C');

//Celda Nombre Empresa (247mm ancho)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell($w_nombre_empresa, 7.5, 'SEMILLEROS PARA EL FUTURO S.A.S', 1, 1, 'C');
$pdf->SetX($margin_x + $w_logo);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($w_nombre_empresa, 7.5, utf8_decode('SOLICITUD DE SUMINISTROS'),1,1, 'C');

// --------------------------------------------------------
// -- INFO SUPERIOR (Fecha, Área, Solicitante) AJUSTADO --
// --------------------------------------------------------
$pdf-> SetY();

?>