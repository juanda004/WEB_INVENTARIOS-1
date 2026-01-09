<?php
// editar_producto.php
// Este script permite editar un producto existente en cualquier tabla de categoría o subcategoría.

session_start(); // Aseguramos el inicio de sesión
require_once 'includes/db.php';

// --- 1. CONTROL DE ACCESO: Debe ir antes de cualquier HTML ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$mensaje = '';
$producto = null;
$tabla_seleccionada = ''; 
$codigo_original = '';    
$user_id = $_SESSION['user_id'] ?? 0;

// --- 2. FUNCIÓN AUXILIAR PARA EL KÁRDEX ---
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

// --- 3. BLOQUE POST: Procesar el formulario de edición ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_producto'])) {
    $codigo_original = trim($_POST['codigo_original']);
    $tabla_seleccionada = trim($_POST['categoria_actual']);
    $nuevo_codigo = trim($_POST['nuevo_codigo']);
    $descripcion = trim($_POST['descripcion']);
    $nueva_cantidad = (int)$_POST['cantidad'];
    $unidad = trim($_POST['unidad']);
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_]+$/i', $tabla_seleccionada)) {
        $mensaje = "<p class='btn-danger'>Nombre de Categoría inválido.</p>";
    } else {
        try {
            // Obtener datos previos para el cálculo de la diferencia
            $sql_prev = "SELECT CANT, PRODUCTO FROM `{$tabla_seleccionada}` WHERE CODIGO = :codigo_original";
            $stmt_prev = $pdo->prepare($sql_prev);
            $stmt_prev->execute([':codigo_original' => $codigo_original]);
            $producto_previo = $stmt_prev->fetch(PDO::FETCH_ASSOC);
            
            $cantidad_anterior = $producto_previo['CANT'] ?? 0;
            $diferencia_stock = $nueva_cantidad - $cantidad_anterior;

            $pdo->beginTransaction();

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

            if ($diferencia_stock != 0) {
                $tipo_ajuste = ($diferencia_stock > 0) ? 'AJUSTE_ENTRADA_MANUAL' : 'AJUSTE_SALIDA_MANUAL';
                registrarMovimiento(
                    $pdo, $nuevo_codigo, $descripcion, $tabla_seleccionada, 
                    $diferencia_stock, $tipo_ajuste, NULL, $user_id, 
                    "Ajuste manual. Anterior: {$cantidad_anterior}. Nueva: {$nueva_cantidad}."
                );
            }

            $pdo->commit();

            // REDIRECCIÓN EXITOSA: Ahora sí funcionará porque no hay HTML previo
            header("Location: ver_productos.php?categoria=" . urlencode($tabla_seleccionada) . "&mensaje=" . urlencode("✅ Producto actualizado correctamente"));
            exit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje = "<p class='btn-danger'>❌ Error al actualizar: " . $e->getMessage() . "</p>";
        }
    }
}

// --- 4. BLOQUE GET: Cargar datos iniciales del producto ---
if (isset($_GET['id']) && isset($_GET['categoria'])) {
    $codigo_original = trim($_GET['id']);
    $tabla_seleccionada = trim($_GET['categoria']);

    if (preg_match('/^[a-zA-Z0-9_]+$/i', $tabla_seleccionada)) {
        try {
            $sql = "SELECT * FROM `{$tabla_seleccionada}` WHERE CODIGO = :codigo";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':codigo' => $codigo_original]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$producto) $mensaje = "<p class='btn-danger'>Producto no encontrado.</p>";
        } catch (PDOException $e) {
            $mensaje = "<p class='btn-danger'>Error al cargar: " . $e->getMessage() . "</p>";
        }
    }
}

// --- 5. INICIO DE SALIDA HTML (Después de toda la lógica de cabeceras) ---
require_once 'includes/header.php';
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
                <input type="text" id="nuevo_codigo" name="nuevo_codigo" class="form-control" value="<?php echo htmlspecialchars($producto['CODIGO']); ?>" required>
            </div>

            <div class="form-group">
                <label for="codigo_barras">Código de Barras (Opcional):</label>
                <input type="text" id="codigo_barras" name="codigo_barras" class="form-control" value="<?php echo htmlspecialchars($producto['CODIGO_BARRAS'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción del Producto:</label>
                <input type="text" id="descripcion" name="descripcion" class="form-control" value="<?php echo htmlspecialchars($producto['PRODUCTO']); ?>" required>
            </div>

            <div class="form-group">
                <label for="cantidad">Cantidad:</label>
                <input type="number" id="cantidad" name="cantidad" class="form-control" value="<?php echo htmlspecialchars($producto['CANT']); ?>" required>
            </div>

            <div class="form-group">
                <label for="unidad">Unidad:</label>
                <select id="unidad" name="unidad" class="form-control" required>
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

            <div class="mt-3">
                <button type="submit" name="editar_producto" class="btn btn-success">Actualizar Producto</button>
                <a href="ver_productos.php?categoria=<?php echo htmlspecialchars($tabla_seleccionada); ?>" class="btn btn-dark">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
    
    <?php require_once 'includes/footer.php'; ?>
</div>