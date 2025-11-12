<?php
// reporte_inventario.php
// Este script permite al administrador generar un reporte de movimientos de inventario por período.
session_start();
require_once 'includes/db.php';
require_once 'includes/db_users.php'; // Necesario para buscar el nombre del usuario
require_once 'includes/header.php';

$mensaje = '';
$user_role = $_SESSION['user_role'] ?? 'admin';

// Redirigir si el usuario no es administrador
if ($user_role !== 'admin') {
    header("Location: login.php");
    exit();
}

$movimientos = [];
$fecha_inicio = '';
$fecha_fin = '';
$usuario_seleccionado = '';
$usuarios_sistema = [];

// 1. Obtener la lista de usuarios para el filtro
try {
    $stmt_users = $pdo->query("SELECT id, email FROM db_users.users ORDER BY email");
    $usuarios_sistema = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar usuarios: " . $e->getMessage() . "</p>";
}

// 2. Lógica para procesar el formulario de fechas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar_reporte'])) {
    $fecha_inicio = trim($_POST['fecha_inicio']);
    $fecha_fin = trim($_POST['fecha_fin']);
    $usuario_seleccionado = $_POST['usuario_filtro'] ?? '';

    // Validar y sanear las fechas
    $fecha_inicio_valida = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
    $fecha_fin_valida = DateTime::createFromFormat('Y-m-d', $fecha_fin);

    if (!$fecha_inicio_valida || !$fecha_fin_valida) {
        $mensaje = "<p class='btn-danger'>❌ Por favor, ingrese un rango de fechas válido.</p>";
    } else {
        // Asegurar que la fecha final incluya todo el día
        $fecha_fin_consulta = $fecha_fin . ' 23:59:59';

        try {
            // Construir la consulta base
            $sql = "SELECT im.*, u.email AS email_usuario 
                    FROM inventario_movimientos im
                    JOIN db_users.users u ON im.usuario_id = u.id
                    WHERE im.fecha_movimiento BETWEEN :inicio AND :fin";
            
            $params = [
                ':inicio' => $fecha_inicio,
                ':fin' => $fecha_fin_consulta
            ];

            // Añadir filtro por usuario si se seleccionó uno
            if (!empty($usuario_seleccionado)) {
                $sql .= " AND im.usuario_id = :usuario_id";
                $params[':usuario_id'] = $usuario_seleccionado;
            }

            $sql .= " ORDER BY im.fecha_movimiento DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($movimientos)) {
                $mensaje = "<p class='btn-info'>ℹ️ No se encontraron movimientos en el período seleccionado.</p>";
            }

        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>❌ Error al cargar movimientos: " . $e->getMessage() . 
                       "<br>Asegúrate de haber creado la tabla `inventario_movimientos`.</p>";
        }
    }
}
?>

<div class="container mt-5">
    <h2>Reporte de Movimientos de Inventario</h2>
    <?php echo $mensaje; ?>

    <form action="reporte_inventario.php" method="POST" class="form-inline mb-4">
        <div class="form-group mx-sm-3 mb-2">
            <label for="fecha_inicio" class="sr-only">Fecha Inicio</label>
            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                   value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
        </div>
        <div class="form-group mx-sm-3 mb-2">
            <label for="fecha_fin" class="sr-only">Fecha Fin</label>
            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                   value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
        </div>
        <div class="form-group mx-sm-3 mb-2">
             <label for="usuario_filtro" class="sr-only">Usuario</label>
             <select name="usuario_filtro" id="usuario_filtro" class="form-control">
                <option value="">-- Todos los Usuarios --</option>
                <?php foreach ($usuarios_sistema as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['id']); ?>" 
                        <?php echo ($usuario_seleccionado == $user['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </option>
                <?php endforeach; ?>
             </select>
        </div>
        <button type="submit" name="generar_reporte" class="btn btn-primary mb-2">Generar Reporte</button>
    </form>

    <?php if (!empty($movimientos)): ?>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Tipo Movimiento</th>
                    <th>Producto (Cód.)</th>
                    <th>Categoría</th>
                    <th>Cant. Afectada</th>
                    <th>Usuario</th>
                    <th>Referencia (ID Solicitud)</th>
                    <th>Comentarios</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($mov['fecha_movimiento']); ?></td>
                        <td>
                            <span class="badge badge-pill badge-<?php
                                switch ($mov['tipo_movimiento']) {
                                    case 'ENTREGA_SOLICITUD': echo 'danger'; break; // Salida
                                    case 'REVERSION': 
                                    case 'INGRESO_NUEVO': echo 'success'; break; // Entrada
                                    case 'AJUSTE_MANUAL': echo 'warning'; break;
                                    default: echo 'info';
                                }
                            ?>">
                                <?php echo htmlspecialchars($mov['tipo_movimiento']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($mov['nombre_producto'] . ' (' . $mov['codigo_producto'] . ')'); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($mov['categoria'])); ?></td>
                        <td>
                            <?php 
                                // Mostrar la cantidad en rojo si es salida, verde si es entrada
                                $clase = $mov['cantidad_afectada'] < 0 ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
                                echo "<span class='{$clase}'>" . htmlspecialchars($mov['cantidad_afectada']) . "</span>";
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($mov['email_usuario']); ?></td>
                        <td><?php echo htmlspecialchars($mov['referencia_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($mov['comentarios'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar_reporte'])): ?>
         <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>