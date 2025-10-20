<?php
// ver_solicitudes.php
// Este script permite al administrador ver y gestionar las solicitudes de productos pendientes.
session_start();
require_once 'includes/db.php';
require_once 'includes/db_users.php';
require_once 'includes/header.php';

$mensaje = '';
$user_role = $_SESSION['user_role'] ?? 'admin';

// Redirigir si el usuario no es administrador
if ($user_role !== 'admin') {
    header("Location: index.php");
    exit();
}

$solicitudes = [];

// Lógica para actualizar el estado de una solicitud
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion_solicitud'])) {
    $solicitud_id = $_POST['solicitud_id'];
    $nuevo_estado = $_POST['nuevo_estado'];

    try {
        $stmt = $pdo->prepare("UPDATE solicitudes SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $solicitud_id]);
        $mensaje = "<p class='btn-success'>✅ Estado de la solicitud #{$solicitud_id} actualizado a '" . htmlspecialchars($nuevo_estado) . "'.</p>";
    } catch (PDOException $e) {
        $mensaje = "<p class='btn-danger'>❌ Error al actualizar el estado: " . $e->getMessage() . "</p>";
    }
}

// Lógica para obtener todas las solicitudes pendientes y entregadas
try {
    $stmt = $pdo->query("SELECT s.*, u.email FROM solicitudes s JOIN db_users.users u ON s.user_id = u.id ORDER BY s.fecha_solicitud DESC");
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar las solicitudes: " . $e->getMessage() . "</p>";
}
?>

<div class="container mt-5">
    <h2>Gestión de Solicitudes de Productos</h2>
    <?php echo $mensaje; ?>

    <?php if (!empty($solicitudes)): ?>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>N° Orden</th>
                    <th>Usuario</th>
                    <th>Área</th>
                    <th>Productos Solicitados</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['id']); ?></td>
                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                        <td><?php echo htmlspecialchars($s['area_solicitante']); ?></td>
                        <td>
                            <ul class="list">
                                <?php
                                $productos_solicitados = json_decode($s['productos_solicitados'], true);
                                foreach ($productos_solicitados as $p) {
                                    echo "<li> " . htmlspecialchars($p['nombre']) . " (" . htmlspecialchars($p['codigo']) . ") - Cantidad: " . htmlspecialchars($p['cantidad']) . "</li>";
                                }
                                ?>
                            </ul>
                        </td>
                        <td><?php echo htmlspecialchars($s['fecha_solicitud']); ?></td>
                        <td>
                            <span class="badge badge-pill badge-<?php
                            switch ($s['estado']) {
                                case 'pendiente':
                                    echo 'warning';
                                    break;
                                case 'entregado':
                                    echo 'success';
                                    break;
                                case 'cancelado':
                                    echo 'danger';
                                    break;
                                default:
                                    echo 'info';
                            }
                            ?>">
                                <?php echo ucfirst(htmlspecialchars($s['estado'])); ?>
                            </span>
                        </td>
                        <td>
                            <form action="ver_solicitudes.php" method="POST" class="d-inline">
                                <input type="hidden" name="solicitud_id" value="<?php echo htmlspecialchars($s['id']); ?>">
                                <select name="nuevo_estado" class="form-control form-control-sm d-inline w-auto">
                                    <option value="pendiente" <?php echo ($s['estado'] == 'pendiente') ? 'selected' : ''; ?>>
                                        PENDIENTE</option>
                                    <option value="entregado" <?php echo ($s['estado'] == 'entregado') ? 'selected' : ''; ?>>
                                        ENTREGADO</option>
                                    <option value="cancelado" <?php echo ($s['estado'] == 'cancelado') ? 'selected' : ''; ?>>
                                        CANCELADO</option>
                                </select>
                                <button type="submit" name="accion_solicitud" class="btn btn-primary btn-sm mt-1">
                                    Actualizar
                                </button>
                                <a href="descargar_solicitudes_pdf.php?id=<?php echo htmlspecialchars($s['id']); ?>" target="_blank" class="btn btn-secondary btn-sm">
                                    Descargar PDF
                                </a>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay solicitudes pendientes.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>