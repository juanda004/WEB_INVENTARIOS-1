<?php
// editar_producto.php
// Este script permite editar un producto existente en cualquier tabla de categoría o subcategoría.
require_once 'includes/db.php';
require_once 'includes/header.php';
session_start();

$mensaje = '';
$producto = null;
$tabla_seleccionada = ''; // Nombre de la tabla (Categoría o Subcategoría)
$codigo_original = '';    // El CODIGO del producto que se está editando
$user_id = $_SESSION['user_id'] ?? 0; // Se asume que el ID del usuario está aquí

// Control de acceso: solo para administradores
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location:index.php");
    exit();
}

// --- Función Auxiliar necesaria para el Kárdex ---
function registrarMovimiento(PDO $pdo, $codigo, $nombre, $categoria, $cantidad, $tipo, $ref_id, $user_id, $comentarios = '') {
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
// --------------------------------------------------


// --- Bloque POST: Procesar el formulario de edición ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_producto'])) {
    // Recolectar datos
    $codigo_original = trim($_POST['codigo_original']);
    $tabla_seleccionada = trim($_POST['categoria_actual']); // Renombrado de $categoria_actual
    $nuevo_codigo = trim($_POST['nuevo_codigo']);
    $descripcion = trim($_POST['descripcion']);
    $nueva_cantidad = (int)$_POST['cantidad'];
    $unidad = trim($_POST['unidad']);
    $codigo_barras = trim($_POST['codigo_barras'] ?? ''); // Asumo que este campo existe o es opcional


    // 1. Validar el nombre de la tabla
    if (!preg_match('/^[a-zA-Z0-9_]+$/i', $tabla_seleccionada)) {
        $mensaje = "<p class='btn-danger'>Nombre de Categoría/Subcategoría inválido.</p>";
    } else {
        // 2. Obtener la cantidad y nombre ACTUAL antes de la actualización
        try {
            $sql_prev = "SELECT CANT, PRODUCTO FROM `{$tabla_seleccionada}` WHERE CODIGO = :codigo_original";
            $stmt_prev = $pdo->prepare($sql_prev);
            $stmt_prev->execute([':codigo_original' => $codigo_original]);
            $producto_previo = $stmt_prev->fetch(PDO::FETCH_ASSOC);
            $cantidad_anterior = $producto_previo['CANT'] ?? 0;
            $nombre_anterior = $producto_previo['PRODUCTO'] ?? $descripcion;

            // Calcular la diferencia de stock (positivo para entrada, negativo para salida)
            $diferencia_stock = $nueva_cantidad - $cantidad_anterior;

            // Iniciar transacción
            $pdo->beginTransaction();

            // 3. Actualizar el producto en la tabla de categoría
            $sql_update = "UPDATE `{$tabla_seleccionada}`
                           SET CODIGO = :nuevo_codigo,
                               CODIGO_BARRAS = :codigo_barras,
                               PRODUCTO = :descripcion,
                               CANT = :nueva_cantidad,
                               UNIDAD = :unidad
                           WHERE CODIGO = :codigo_original";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':nuevo_codigo' => $nuevo_codigo,
                ':codigo_barras' => $codigo_barras,
                ':descripcion' => $descripcion,
                ':nueva_cantidad' => $nueva_cantidad,
                ':unidad' => $unidad,
                ':codigo_original' => $codigo_original
            ]);

            // 4. REGISTRAR MOVIMIENTO DE AJUSTE (KÁRDEX)
            if ($diferencia_stock != 0) {
                $tipo_ajuste = ($diferencia_stock > 0) ? 'AJUSTE_ENTRADA_MANUAL' : 'AJUSTE_SALIDA_MANUAL';
                $comentarios = "Ajuste manual de stock por edición. Cantidad anterior: {$cantidad_anterior}. Nueva cantidad: {$nueva_cantidad}.";

                registrarMovimiento(
                    $pdo,
                    $nuevo_codigo,
                    $descripcion,
                    $tabla_seleccionada,
                    $diferencia_stock,
                    $tipo_ajuste,
                    NULL,
                    $user_id,
                    $comentarios
                );
            }

            // Confirmar la transacción
            $pdo->commit();

            $mensaje = "<p class='btn-success'>✅ Producto actualizado correctamente. Ajuste de stock registrado.</p>";
            // Redirigir para ver la tabla con el producto actualizado
            header("Location: ver_productos.php?categoria=" . urlencode($tabla_seleccionada));
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "<p class='btn-danger'>❌ Error al actualizar el producto: " . $e->getMessage() . "</p>";
        }
    }

    // Si hubo un error o para recargar el formulario con los datos POST
    $codigo_original = $nuevo_codigo;
    $tabla_seleccionada = $tabla_seleccionada;
}

// --- Bloque GET: Cargar datos iniciales del producto ---
if (isset($_GET['id']) && isset($_GET['categoria'])) {
    $codigo_original = trim($_GET['id']);
    $tabla_seleccionada = trim($_GET['categoria']);

    // 1. Validar el nombre de la tabla para prevenir inyección SQL
    if (!preg_match('/^[a-zA-Z0-9_]+$/i', $tabla_seleccionada)) {
        die("<p class='btn-danger'>Nombre de Categoría/Subcategoría inválido.</p>");
    }

    try {
        // 2. Consultar el producto a editar de la tabla dinámica
        $sql = "SELECT * FROM `{$tabla_seleccionada}` WHERE CODIGO = :codigo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':codigo' => $codigo_original]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            $mensaje = "<p class='btn-danger'>Producto no encontrado.</p>";
        }
    } catch (PDOException $e) {
        $mensaje = "<p class='btn-danger'>Error al cargar el producto: " . $e->getMessage() . "</p>";
    }
} else if (!isset($_POST['editar_producto'])) {
    $mensaje = "<p class='btn-danger'>No se especificó un producto o categoría para editar.</p>";
}
?>

<div class="container mt-5">
    <h2>Editar Producto</h2>
    <?php echo $mensaje; ?>

    <?php if ($producto): ?>
        <form action="editar_producto.php" method="POST">
            <input type="hidden" name="codigo_original" value="<?php echo htmlspecialchars($codigo_original); ?>">
            <input type="hidden" name="categoria_actual" value="<?php echo htmlspecialchars($tabla_seleccionada); ?>">

            <div class="form-group">
                <label for="nuevo_codigo">Código del Producto:</label>
                <input type="text" id="nuevo_codigo" name="nuevo_codigo" value="<?php echo htmlspecialchars($producto['CODIGO']); ?>" required>
            </div>

            <div class="form-group">
                <label for="codigo_barras">Código de Barras (Opcional):</label>
                <input type="text" id="codigo_barras" name="codigo_barras" value="<?php echo htmlspecialchars($producto['CODIGO_BARRAS'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción del Producto:</label>
                <input type="text" id="descripcion" name="descripcion" value="<?php echo htmlspecialchars($producto['PRODUCTO']); ?>" required>
            </div>

            <div class="form-group">
                <label for="cantidad">Cantidad:</label>
                <input type="number" id="cantidad" name="cantidad" value="<?php echo htmlspecialchars($producto['CANT']); ?>" required>
            </div>

            <div class="form-group">
                <label for="unidad">Unidad:</label>
                <select id="unidad" name="unidad" required>
                    <?php
                        $unidades = ['UNIDAD', 'CAJA', 'EMPAQUE', 'PACA', 'PAR', 'FRASCO', 'LITRO', 'METRO', 'ROLLO'];
                        $unidad_actual = $producto['UNIDAD'];
                        foreach ($unidades as $un):
                    ?>
                        <option value="<?php echo $un; ?>" <?php echo ($unidad_actual === $un) ? 'selected' : ''; ?>>
                            <?php echo $un; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="editar_producto" class="btn btn-success">Actualizar Producto</button>
            <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($tabla_seleccionada); ?>" class="btn btn-dark">Cancelar</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>