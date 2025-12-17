<?php
// ver_productos.php
// Este script muestra los productos de una categoría específica y maneja la eliminación.

session_start();
// Control de acceso: requiere autenticación
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';
include 'includes/header.php';

$mensaje = '';
$categoria_seleccionada = isset($_GET['categoria']) ? $_GET['categoria'] : null;
$productos = [];
$user_role = $_SESSION['role'] ?? 'user';
$user_id = $_SESSION['user_id'] ?? 0; // ID del usuario logueado

// =================================================================
// --- FUNCIÓN AUXILIAR PARA EL KÁRDEX ---
// =================================================================
/**
 * Registra un movimiento en el Kárdex (inventario_movimientos).
 * Para 'ELIMINACION TOTAL', la cantidad es el stock actual, y el stock final es 0.
 */
function registrarMovimiento(PDO $pdo, $codigo, $nombre, $categoria, $cantidad, $tipo, $ref_id, $user_id, $comentarios = '')
{
    // Para el movimiento de eliminación, el stock final DEBE ser 0.
    $stock_final = 0; 
    $cantidad_afectada = $cantidad; // La cantidad afectada es el stock total que se elimina

    $stmt = $pdo->prepare("
        INSERT INTO inventario_movimientos
        (fecha_movimiento, codigo_producto, nombre_producto, categoria, cantidad_afectada, tipo_movimiento, referencia_id, usuario_id, comentarios)
        VALUES (NOW(), :codigo, :nombre, :categoria, :cantidad_afectada, :tipo, :ref_id, :user_id, :comentarios)
    ");
    
    $stmt->execute([
        ':codigo' => $codigo,
        ':nombre' => $nombre,
        ':categoria' => $categoria,
        ':cantidad_afectada' => $cantidad_afectada,
        ':tipo' => $tipo,
        ':ref_id' => $ref_id,
        ':user_id' => $user_id,
        ':comentarios' => $comentarios,
    ]);
}
// =================================================================
// --- FIN FUNCIÓN AUXILIAR ---
// =================================================================


// --- Validacion Inicial de la categoría seleccionada ---
if (empty($categoria_seleccionada)) {
    echo "<p class='btn-danger'>❌ Error: No se ha especificado una categoría para mostrar productos.</p>";
    include 'includes/footer.php';
    exit();
} else {
    // Asegurar de que el nombre de la tabla sea seguro para evitar SQL Injection
    if (!preg_match('/^[a-zA-Z0-9_]+$/i', $categoria_seleccionada)) {
        echo "<p class='btn-danger'>❌ Error: Nombre de categoría no válido.</p>";
        include 'includes/footer.php';
        exit();
    }
}


// --- Lógica para eliminar un producto (solo para admin) y registrar 'ELIMINACION TOTAL' ---
if (isset($_GET['action']) && $_GET['action'] == 'eliminar' && isset($_GET['id'])) {
    // Verificar si el usuario es administrador
    if ($user_role === 'admin') {
        $codigo_producto = trim($_GET['id']);
        $producto_eliminado = null; // Para guardar la info antes de borrar

        try {
            // 1. Obtener los detalles del producto ANTES de eliminarlo para el Kárdex
            $sql_select = "SELECT CODIGO, PRODUCTO, CANT FROM `$categoria_seleccionada` WHERE CODIGO = :codigo";
            $stmt_select = $pdo->prepare($sql_select);
            $stmt_select->execute([':codigo' => $codigo_producto]);
            $producto_eliminado = $stmt_select->fetch(PDO::FETCH_ASSOC);

            if ($producto_eliminado) {
                // Guardar la información necesaria
                $nombre_producto = $producto_eliminado['PRODUCTO'];
                $cantidad_eliminada = $producto_eliminado['CANT']; // Cantidad total que se va a eliminar

                // Iniciar una transacción para asegurar la consistencia
                $pdo->beginTransaction();

                // 2. Eliminar el producto de la tabla dinámica de la categoría
                $sql_delete = "DELETE FROM `$categoria_seleccionada` WHERE CODIGO = :codigo";
                $stmt_delete = $pdo->prepare($sql_delete);
                $stmt_delete->execute([':codigo' => $codigo_producto]);
                
                // 3. Registrar el movimiento de ELIMINACION TOTAL en el Kárdex
                registrarMovimiento(
                    $pdo,
                    $codigo_producto,
                    $nombre_producto,
                    $categoria_seleccionada,
                    $cantidad_eliminada, // Cantidad total que se elimina del inventario
                    'ELIMINACION TOTAL',
                    0, // ref_id 0 porque no es de una solicitud
                    $user_id,
                    "Eliminación total del producto del inventario por administrador. Stock anterior: {$cantidad_eliminada}"
                );

                $pdo->commit(); // Confirmar la eliminación y el registro
                $mensaje = "<p class='btn-success'>✅ Producto '{$nombre_producto}' (Código: {$codigo_producto}) eliminado totalmente de la categoría '{$categoria_seleccionada}' y registrado en Kárdex.</p>";
            } else {
                $mensaje = "<p class='btn-danger'>❌ Error: Producto no encontrado en la categoría '{$categoria_seleccionada}'.</p>";
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack(); // Deshacer si hubo un error después del inicio de la transacción
            }
            $mensaje = "<p class='btn-danger'>❌ Error al eliminar el producto: " . $e->getMessage() . "</p>";
        }
    } else {
        $mensaje = "<p class='btn-danger'>❌ Acceso denegado: Solo los administradores pueden eliminar productos.</p>";
    }
    // Redireccionar para limpiar los parámetros GET
    header("Location: ver_productos.php?categoria=" . urlencode($categoria_seleccionada) . "&mensaje=" . urlencode(strip_tags($mensaje)));
    exit();
}


// --- Lógica para obtener el listado de productos ---
try {
    $sql_productos = "SELECT * FROM `$categoria_seleccionada` ORDER BY CODIGO ASC";
    $stmt_productos = $pdo->prepare($sql_productos);
    $stmt_productos->execute();
    $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la tabla no existe o hay otro error, se maneja aquí
    $productos = [];
    $mensaje = "<p class='btn-danger'>❌ Error al cargar los productos: " . $e->getMessage() . "</p>";
}

// Mostrar mensaje si existe (útil después de la redirección)
if (isset($_GET['mensaje'])) {
    $mensaje = "<p>" . htmlspecialchars($_GET['mensaje']) . "</p>"; // Asegurarse de que el mensaje HTML se muestre correctamente
}

?>
<div class="container main-content">
    <h2>Inventario de Categoría: <?php echo htmlspecialchars(ucfirst($categoria_seleccionada)); ?></h2>
    <?php echo $mensaje; ?>

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <p>
            <a class="btn btn-success"
                href="agregar_producto.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>">Agregar Nuevo Producto</a>
        </p>
    <?php endif; ?>

    <?php if ($productos): ?>
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th style="text-align: center;">CODIGO</th>
                    <th style="text-align: center;">CODIGO DE BARRAS</th>
                    <th style="text-align: center;">DESCRIPCIÓN</th>
                    <th style="text-align: center;">UNIDAD</th>
                    <th style="text-align: center;">CANTIDAD</th>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <th>ACCIONES</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td style="text-align: center;"><?php echo htmlspecialchars($producto['CODIGO']); ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($producto['CODIGO_BARRAS']); ?></td>
                        <td><?php echo htmlspecialchars($producto['PRODUCTO']); ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($producto['UNIDAD']); ?></td>
                        <td style="text-align: center;"> <?php echo htmlspecialchars($producto['CANT']); ?></td>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <td>
                                <div style="display: flex; flex-direction: row; gap: 5px;">
                                    <a href="editar_producto.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                        class="btn btn-warning">Editar</a>
                                    <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>&action=eliminar&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                        class="btn btn-danger"
                                        onclick="return confirm ('¿Está seguro de que desea eliminar el producto? Se registrará como ELIMINACIÓN TOTAL en el Kárdex.');">Eliminar</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay productos registrados en el inventario para la categoría
            '<?php echo htmlspecialchars($categoria_seleccionada); ?>'.</p>
    <?php endif; ?>
    <div><a class="btn btn-dark" href="categorias.php">Volver a Categorías</a></div>
    <?php include 'includes/footer.php'; ?>
</div>