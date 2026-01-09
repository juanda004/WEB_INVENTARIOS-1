<?php
// ver_productos.php
// Este script muestra los productos de una categoría específica y maneja la eliminación.

session_start();

// 1. CONTROL DE ACCESO (Debe ser lo primero, antes de cualquier salida)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';

// --- CONFIGURACIÓN INICIAL DE VARIABLES ---
$mensaje = '';
$categoria_seleccionada = isset($_GET['categoria']) ? $_GET['categoria'] : null;
$user_role = $_SESSION['role'] ?? 'user';
$user_id = $_SESSION['user_id'] ?? 0;
$productos = [];

// =================================================================
// --- FUNCIÓN AUXILIAR PARA EL KÁRDEX ---
// =================================================================
function registrarMovimiento(PDO $pdo, $codigo, $nombre, $categoria, $cantidad, $tipo, $ref_id, $user_id, $comentarios = '')
{
    $stmt = $pdo->prepare("
        INSERT INTO inventario_movimientos
        (fecha_movimiento, codigo_producto, nombre_producto, categoria, cantidad_afectada, tipo_movimiento, referencia_id, usuario_id, comentarios)
        VALUES (NOW(), :codigo, :nombre, :categoria, :cantidad_afectada, :tipo, :ref_id, :user_id, :comentarios)
    ");
    
    $stmt->execute([
        ':codigo' => $codigo,
        ':nombre' => $nombre,
        ':categoria' => $categoria,
        ':cantidad_afectada' => $cantidad,
        ':tipo' => $tipo,
        ':ref_id' => $ref_id,
        ':user_id' => $user_id,
        ':comentarios' => $comentarios,
    ]);
}

// =================================================================
// --- LÓGICA DE PROCESAMIENTO (ELIMINACIÓN) ---
// =================================================================
// IMPORTANTE: Esto debe ir antes de include 'includes/header.php'

if (isset($_GET['action']) && $_GET['action'] == 'eliminar' && isset($_GET['id'])) {
    if ($user_role === 'admin') {
        $codigo_producto = trim($_GET['id']);

        try {
            $sql_select = "SELECT CODIGO, PRODUCTO, CANT FROM `$categoria_seleccionada` WHERE CODIGO = :codigo";
            $stmt_select = $pdo->prepare($sql_select);
            $stmt_select->execute([':codigo' => $codigo_producto]);
            $producto_eliminado = $stmt_select->fetch(PDO::FETCH_ASSOC);

            if ($producto_eliminado) {
                $pdo->beginTransaction();

                $sql_delete = "DELETE FROM `$categoria_seleccionada` WHERE CODIGO = :codigo";
                $stmt_delete = $pdo->prepare($sql_delete);
                $stmt_delete->execute([':codigo' => $codigo_producto]);
                
                registrarMovimiento(
                    $pdo,
                    $codigo_producto,
                    $producto_eliminado['PRODUCTO'],
                    $categoria_seleccionada,
                    $producto_eliminado['CANT'],
                    'ELIMINACION TOTAL',
                    0,
                    $user_id,
                    "Eliminación total del producto por administrador. Stock anterior: {$producto_eliminado['CANT']}"
                );

                $pdo->commit();
                $mensaje_exito = "✅ Producto eliminado y registrado en Kárdex.";
                
                // Redirección limpia tras éxito
                header("Location: ver_productos.php?categoria=" . urlencode($categoria_seleccionada) . "&mensaje=" . urlencode($mensaje_exito));
                exit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje_error = "❌ Error al eliminar: " . $e->getMessage();
            header("Location: ver_productos.php?categoria=" . urlencode($categoria_seleccionada) . "&mensaje=" . urlencode($mensaje_error));
            exit();
        }
    } else {
        $mensaje_error = "❌ Acceso denegado: Solo administradores.";
        header("Location: ver_productos.php?categoria=" . urlencode($categoria_seleccionada) . "&mensaje=" . urlencode($mensaje_error));
        exit();
    }
}

// --- Lógica para obtener el listado de productos ---
if (!empty($categoria_seleccionada) && preg_match('/^[a-zA-Z0-9_]+$/i', $categoria_seleccionada)) {
    try {
        $sql_productos = "SELECT * FROM `$categoria_seleccionada` ORDER BY CODIGO ASC";
        $stmt_productos = $pdo->prepare($sql_productos);
        $stmt_productos->execute();
        $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje = "<p class='btn-danger'>❌ Error al cargar productos: " . $e->getMessage() . "</p>";
    }
}

// Capturar mensaje de la URL si existe
if (isset($_GET['mensaje'])) {
    $mensaje = "<p class='alert alert-info'>" . htmlspecialchars($_GET['mensaje']) . "</p>";
}

// =================================================================
// --- INICIO DE LA VISTA (HTML) ---
// =================================================================
include 'includes/header.php'; 
?>

<div class="container main-content">
    <h2>Inventario de Categoría: <?php echo htmlspecialchars(ucfirst($categoria_seleccionada)); ?></h2>
    
    <?php echo $mensaje; ?>

    <?php if ($user_role === 'admin'): ?>
        <p>
            <a class="btn btn-success"
                href="agregar_producto.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>">Agregar Nuevo Producto</a>
        </p>
    <?php endif; ?>

    <?php if ($productos): ?>
        <table class="table table-responsive table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th style="text-align: center;">CODIGO</th>
                    <th style="text-align: center;">CODIGO DE BARRAS</th>
                    <th style="text-align: center;">DESCRIPCIÓN</th>
                    <th style="text-align: center;">UNIDAD</th>
                    <th style="text-align: center;">CANTIDAD</th>
                    <?php if ($user_role === 'admin'): ?>
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
                        <?php if ($user_role === 'admin'): ?>
                            <td>
                                <div style="display: flex; flex-direction: row; gap: 5px;">
                                    <a href="editar_producto.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                        class="btn btn-warning">Editar</a>
                                    <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($categoria_seleccionada); ?>&action=eliminar&id=<?php echo htmlspecialchars($producto['CODIGO']); ?>"
                                        class="btn btn-danger"
                                        onclick="return confirm ('¿Está seguro de que desea eliminar el producto? Se registrará como ELIMINACIÓN TOTAL en el Kárdex.');">Eliminar</a>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay productos registrados en la categoría '<?php echo htmlspecialchars($categoria_seleccionada); ?>'.</p>
    <?php endif; ?>

    <div class="mt-4">
        <a class="btn btn-dark" href="categorias.php">Volver a Categorías</a>
    </div>

    <?php include 'includes/footer.php'; ?>
</div>