<?php
// ver_solicitudes.php
// Este script permite al administrador ver y gestionar las solicitudes de productos pendientes,
// incluyendo el ajuste de inventario por todas las categorías.
session_start();
require_once 'includes/db.php';
require_once 'includes/db_users.php';
require_once 'includes/header.php';

$mensaje = '';
$user_role = $_SESSION['user_role'] ?? 'admin';
$user_id = $_SESSION['user_id'] ?? 0; //ID del usuario

// Redirigir si el usuario no es administrador
if ($user_role !== 'admin') {
    header("Location: login.php");
    exit();
}

$solicitudes = [];

// =================================================================
// --- INICIO DE FUNCIONES AUXILIARES (AQUÍ DEBEN ESTAR) ---
// =================================================================

/**
 * Obtiene todos los nombres de tabla de inventario desde la tabla 'categorias'.
 */

function registrarMovimiento(PDO $pdo, $codigo, $nombre, $categoria, $cantidad, $tipo, $ref_id, $user_id, $comentarios = '')
{
    $stmt = $pdo->prepare("
    INSERT INTO inventario_movimientos
    (fecha_movimiento, codigo_producto, nombre_producto, categoria, cantidad_afectada, tipo_movimiento, referencia_id, usuario_id, comentarios)
    VALUES (NOW(), :codigo, :nombre, :categoria, :cantidad, :tipo, :ref_id, :user_id, :comentarios)
    ");

    $stmt->execute([
        ':codigo' => $codigo,
        ':nombre' => $nombre,
        ':categoria' => $categoria,
        ':cantidad' => $cantidad,
        ':tipo' => $tipo,
        ':ref_id' => $ref_id,
        ':user_id' => $user_id,
        ':comentarios' => $comentarios
    ]);
}
;

/** 
 * Obtiene todos los nombres de tabla de inventario desde la tabla 'categorias'.
 */
function obtenerTodasLasTablas(PDO $pdo): array
{
    try {
        // Se asume que la tabla de control se llama 'categorias' y tiene la columna 'nombre_categoria'
        $stmt_categorias = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
        return $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // En caso de que la tabla 'categorias' no exista, retorna vacío.
        return [];
    }
}

/**
 * Aplica o revierte el ajuste de inventario buscando el producto por código en TODAS las tablas, y registra el movimiento.
 * @param array $productos Array de productos desde el JSON (debe contener 'codigo', 'cantidad' y 'nombre').
 * @param string $operacion '+' para aumentar (revertir/devolver), '-' para disminuir (descontar).
 * @param int $solicitud_id ID de la solicitud para referencia en el kárdex.
 * @param int $user_id ID del usuario que realiza la acción.
 * @throws Exception Si hay un error de stock, producto no encontrado o error SQL.
 */
function gestionarInventario(PDO $pdo, array $productos, string $operacion, int $solicitud_id, int $user_id)
{
    $tablas = obtenerTodasLasTablas($pdo);
    $tipo_movimiento = ($operacion === '-') ? 'ENTREGA_SOLICITUD' : 'DEVOLUCIÓN';

    if (empty($tablas)) {
        throw new Exception("No se encontraron tablas de categorías para buscar productos.");
    }

    foreach ($productos as $producto) {
        $codigo = $producto['codigo'] ?? null;
        $cantidad = (int) ($producto['cantidad'] ?? 0);
        $nombre = $producto['nombre'] ?? 'Desconocido';


        if (empty($codigo) || $cantidad <= 0)
            continue;

        $encontrado_y_actualizado = false;

        foreach ($tablas as $tabla) {
            // Validación de seguridad de nombre de tabla
            if (!preg_match('/^[a-zA-Z0-9_]+$/i', $tabla))
                continue;

            // 1. Intentar actualizar el stock en la tabla actual
            $sql_update = "UPDATE `$tabla` 
                           SET CANT = CANT {$operacion} :cantidad_update 
                           WHERE CODIGO = :codigo";

            $params = [
                'cantidad_update' => $cantidad, //Parametro para el SET
                ':codigo' => $codigo
            ];

            // Si es un descuento ('-'), incluimos la condición de stock mínimo para prevenir CANT < 0.
            if ($operacion === '-') {
                $sql_update .= " AND CANT >= :cantidad_check";
                $params[':cantidad_check'] = $cantidad;
            }

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute($params);

            // 2. Verificar si se actualizó una fila (producto encontrado en esta tabla)
            if ($stmt_update->rowCount() > 0) {
                $encontrado_y_actualizado = true;

                // REGISTRO DE MOVIMIENTO
                $cantidad_movimiento = ($operacion === '-') ? (-1 * $cantidad) : $cantidad; // Negativo para salida, Postivo para entrada
                registrarMovimiento($pdo, $codigo, $nombre, $tabla, $cantidad_movimiento, $tipo_movimiento, $solicitud_id, $user_id, "Movimiento desde Solicitud #{$solicitud_id}");

                break; // Producto encontrado y actualizado, pasar al siguiente producto solicitado
            }
        }

        // 3. Manejar el error de no encontrado o stock insuficiente durante el descuento
        if (!$encontrado_y_actualizado) {
            if ($operacion === '-') {
                // Si no se pudo descontar (no existe o no hay stock), lanzamos excepción.
                throw new Exception("Fallo de Stock/Producto No Encontrado: El producto '{$codigo}' (Cant: {$cantidad}) no existe o no tiene stock suficiente para el descuento.");
            } else {
                // Es una reversión que falló, se registra pero se intenta continuar con el resto de productos.
                error_log("ADVERTENCIA: Reversión de inventario fallida para producto con código {$codigo}. No encontrado.");
            }
        }
    }
}

// =================================================================
// --- FIN DE FUNCIONES AUXILIARES ---
// =================================================================


// --- LÓGICA PRINCIPAL: Actualización de estado y gestión de inventario ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion_solicitud'])) {
    $solicitud_id = $_POST['solicitud_id'];
    $nuevo_estado = $_POST['nuevo_estado'];

    try {
        // 1. INICIAR TRANSACCIÓN SQL
        $pdo->beginTransaction();

        // 2. Obtener estado actual y productos solicitados
        $stmt_data = $pdo->prepare("SELECT estado, productos_solicitados FROM solicitudes WHERE id = ?");
        $stmt_data->execute([$solicitud_id]);
        $solicitud_data = $stmt_data->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud_data) {
            throw new Exception("Solicitud no encontrada.");
        }

        $estado_actual = $solicitud_data['estado'];
        $productos = json_decode($solicitud_data['productos_solicitados'], true);

        $fecha_update = "";
        $mensaje_accion = "sin cambios en inventario";

        // Caso A: Transición a ENTREGADO (Descuento)
        if ($nuevo_estado === 'entregado' && $estado_actual !== 'entregado') {
            gestionarInventario($pdo, $productos, '-', $solicitud_id, $user_id); // Descontar unidades y registrar
            $fecha_update = ", fecha_entrega = NOW()";
            $mensaje_accion = "descontado";

            // Caso B: Transición de ENTREGADO a otro estado (Reversión/Devolución)
        } elseif ($nuevo_estado !== 'entregado' && $estado_actual === 'entregado') {
            gestionarInventario($pdo, $productos, '+', $solicitud_id, $user_id); // Revertir unidades y registrar
            $fecha_update = ", fecha_entrega = NULL";
            $mensaje_accion = "devuelto";

            // Caso C: Otro cambio de estado o el estado se mantiene
        } elseif ($nuevo_estado === 'cancelado' && $estado_actual !== 'cancelado') {
            // Si se cancela una solicitud que NO estaba entregada, no hace falta revertir stock, solo actualizar estado.
            $mensaje_accion = "estado cambiado a CANCELADO";
        } else {
            // No se hace nada con el inventario
        }

        // 3. Actualizar el estado de la solicitud principal
        $sql_update_sol = "UPDATE solicitudes SET estado = ? {$fecha_update} WHERE id = ?";
        $stmt_update_sol = $pdo->prepare($sql_update_sol);
        $stmt_update_sol->execute([$nuevo_estado, $solicitud_id]);

        // 4. CONFIRMAR LA TRANSACCIÓN
        $pdo->commit();

        $mensaje = "<p class='btn-success'>✅ Solicitud #{$solicitud_id} actualizada a **" . strtoupper($nuevo_estado) . "**. Inventario {$mensaje_accion} correctamente.</p>";

    } catch (Exception $e) {
        // 5. SI HAY ERROR, DESHACER CAMBIOS
        $pdo->rollBack();
        $mensaje = "<p class='btn-danger'>❌ Error de Procesamiento (ROLLBACK): " . $e->getMessage() . "</p>";
    }
}

// Lógica para obtener todas las solicitudes
try {
    $stmt = $pdo->query("SELECT s.*, u.email FROM solicitudes s JOIN db_users.users u ON s.user_id = u.id ORDER BY s.fecha_solicitud DESC");
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje .= "<p class='btn-danger'>❌ Error al cargar las solicitudes: " . $e->getMessage() . "</p>";
}
?>

<!--<div class="container mt-5">-->
<div class="container main-content">
    <h2>Gestión de Solicitudes de Productos</h2>
    <?php echo $mensaje; ?>

    <?php if (!empty($solicitudes)): ?>
        <table class="table table-responsive table-bordered table-striped">
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
                                // Asegurar que $productos_solicitados sea un array antes de iterar
                                if (is_array($productos_solicitados)) {
                                    foreach ($productos_solicitados as $p) {
                                        // Se asume que el JSON tiene 'nombre', 'codigo' y 'cantidad'
                                        $nombre = htmlspecialchars($p['nombre'] ?? 'N/D');
                                        $codigo = htmlspecialchars($p['codigo'] ?? 'N/D');
                                        $cantidad = htmlspecialchars($p['cantidad'] ?? 'N/D');
                                        echo "<li> " . $nombre . " (" . $codigo . ") - Cantidad: " . $cantidad . "</li>";
                                    }
                                } else {
                                    echo "<li>Datos de productos inválidos.</li>";
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
                                <a href="descargar_solicitudes_pdf.php?id=<?php echo htmlspecialchars($s['id']); ?>"
                                    class="btn btn-secondary btn-sm">
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
    <?php require_once 'includes/footer.php'; ?>
</div>