<?php
// reportes.php
// Muestra el Kárdex de Inventario (inventario_movimientos) con filtros y paginación.
session_start();
require_once 'includes/db.php';
require_once 'includes/db_users.php'; // Incluir si necesitas información del usuario que realizó el movimiento
require_once 'includes/header.php';

$mensaje = '';
$user_role = $_SESSION['user_role'] ?? 'admin';

// Redirigir si el usuario no es administrador
if ($user_role !== 'admin') {
    header("Location: login.php");
    exit();
}

// =================================================================
// --- LÓGICA DE FILTRADO Y PAGINACIÓN ---
// =================================================================

// 1. Configuración de Paginación
$limite_por_pagina = 20;
$pagina_actual = isset($_GET['p']) ? (int) $_GET['p'] : 1;
$offset = ($pagina_actual - 1) * $limite_por_pagina;

// 2. Recolección de Filtros
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';
$filtro_codigo = trim($_GET['codigo'] ?? '');
$filtro_categoria = trim($_GET['categoria'] ?? '');
$filtro_tipo = trim($_GET['tipo'] ?? '');

// 3. Construcción de la Consulta SQL y los Parámetros
$sql_base = "
    SELECT
        m.*,
        u.email AS nombre_usuario
    FROM inventario_movimientos m
    LEFT JOIN db_users.users u ON m.usuario_id = u.id
";

$condiciones = [];
$parametros = [];

// Filtro por Rango de Fechas
if (!empty($filtro_fecha_inicio)) {
    $condiciones[] = "m.fecha_movimiento >= :fecha_inicio";
    $parametros[':fecha_inicio'] = $filtro_fecha_inicio . ' 00:00:00';
}
if (!empty($filtro_fecha_fin)) {
    $condiciones[] = "m.fecha_movimiento <= :fecha_fin";
    $parametros[':fecha_fin'] = $filtro_fecha_fin . ' 23:59:59';
}

// Filtro por Código de Producto
if (!empty($filtro_codigo)) {
    $condiciones[] = "m.codigo_producto LIKE :codigo";
    $parametros[':codigo'] = '%' . $filtro_codigo . '%';
}

// Filtro por Categoría
if (!empty($filtro_categoria)) {
    $condiciones[] = "m.categoria = :categoria";
    $parametros[':categoria'] = $filtro_categoria;
}

// Filtro por Tipo de Movimiento
if (!empty($filtro_tipo)) {
    $condiciones[] = "m.tipo_movimiento = :tipo";
    $parametros[':tipo'] = $filtro_tipo;
}

// Unir condiciones si existen
$where_clause = '';
if (!empty($condiciones)) {
    $where_clause = " WHERE " . implode(" AND ", $condiciones);
}


// --- LÓGICA PRINCIPAL DEL KÁRDEX: Obtener todos los movimientos filtrados y calcular saldo ---
try {
    // 4. Consulta para TODOS los Registros que coinciden con los filtros, ordenados cronológicamente (ASC)
    $sql_all_filtered = $sql_base . $where_clause . " ORDER BY m.fecha_movimiento ASC, m.id ASC";

    $all_params = $parametros;

    $stmt_all = $pdo->prepare($sql_all_filtered);
    $stmt_all->execute($all_params);
    $movimientos_chronological = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    // 5. CÁLCULO DEL SALDO CORRIENTE EN PHP (Kárdex)
    $saldo_por_producto = [];
    $movimientos_con_saldo = [];

    foreach ($movimientos_chronological as $mov) {
        $key = $mov['codigo_producto'] . '|' . $mov['categoria'];

        if (!isset($saldo_por_producto[$key])) {
            $saldo_por_producto[$key] = 0;
        }

        $mov['saldo_anterior'] = $saldo_por_producto[$key];
        $saldo_por_producto[$key] += $mov['cantidad_afectada'];
        $mov['saldo_restante'] = $saldo_por_producto[$key];

        $movimientos_con_saldo[] = $mov;
    }


    // =================================================================
    // --- OPTIMIZACIÓN: OBTENER EL STOCK ACTUAL EN LA BD POR LOTES ---
    // =================================================================

    // 6. 1. Agrupar códigos de producto por tabla de categoría
    $productos_por_tabla = [];
    foreach ($movimientos_con_saldo as $mov) {
        $categoria = $mov['categoria'];
        $codigo = $mov['codigo_producto'];

        // Evitar duplicados de código dentro de la misma tabla
        $productos_por_tabla[$categoria][$codigo] = $codigo;
    }

    // 6. 2. Ejecutar consultas por lotes (una por cada tabla única)
    $stock_actual_map = []; // Mapa final: 'codigo|categoria' => CANT

    foreach ($productos_por_tabla as $categoria => $codigos) {
        // Validación de seguridad para el nombre de la tabla
        if (!preg_match('/^[a-zA-Z0-9_]+$/i', $categoria)) {
            continue;
        }

        // Generar una lista de marcadores de posición para la cláusula IN (ej. ?, ?, ?)
        $placeholders = implode(',', array_fill(0, count($codigos), '?'));

        try {
            // Consultar la cantidad (CANT) y el CODIGO para todos los productos de esta tabla
            $sql_stock = "SELECT CODIGO, CANT FROM `$categoria` WHERE CODIGO IN ($placeholders)";
            $stmt_stock = $pdo->prepare($sql_stock);

            // Ejecutar con el array de códigos
            $stmt_stock->execute(array_values($codigos));
            $stocks_en_tabla = $stmt_stock->fetchAll(PDO::FETCH_KEY_PAIR); // Obtiene [CODIGO => CANT]

            // Fusionar los resultados en el mapa principal
            foreach ($codigos as $codigo) {
                $key = $codigo . '|' . $categoria;
                if (isset($stocks_en_tabla[$codigo])) {
                    // Producto encontrado en la tabla
                    $stock_actual_map[$key] = (int) $stocks_en_tabla[$codigo];
                } else {
                    // Producto no encontrado (posiblemente eliminado de la tabla original)
                    $stock_actual_map[$key] = 'N/D (Eliminado)';
                }
            }
        } catch (PDOException $e) {
            // Marcar todos los productos de esta tabla con error
            foreach ($codigos as $codigo) {
                $stock_actual_map[$codigo . '|' . $categoria] = 'Error de BD';
            }
        }
    }

    // 6. 3. Adjuntar el stock actual a cada movimiento
    foreach ($movimientos_con_saldo as &$mov) {
        $key = $mov['codigo_producto'] . '|' . $mov['categoria'];
        $mov['stock_actual_bd'] = $stock_actual_map[$key] ?? 'N/D';
    }
    unset($mov); // Romper la referencia

    // =================================================================
    // --- FIN OPTIMIZACIÓN ---
    // =================================================================

    // 7. APLICAR PAGINACIÓN Y ORDEN INVERSO (DESC) EN EL ARRAY FINAL

    // Invertir el array para mostrar los más recientes primero (orden DESC)
    $movimientos_con_saldo = array_reverse($movimientos_con_saldo);

    $total_registros = count($movimientos_con_saldo);
    $total_paginas = ceil($total_registros / $limite_por_pagina);

    // Extraer la porción correspondiente a la página actual
    $movimientos = array_slice($movimientos_con_saldo, $offset, $limite_por_pagina);

    // Obtener la lista de categorías (tablas) para el filtro
    $stmt_categorias = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar los movimientos: " . $e->getMessage() . "</p>";
    $movimientos = [];
    $total_registros = 0;
    $total_paginas = 0;
    $categorias = [];
}

// ... (Resto de funciones y variables)

// Tipos de Movimiento fijos
$tipos_movimiento = [
    'INGRESO_NUEVO',
    'AJUSTE_ENTRADA_MANUAL',
    'AJUSTE_SALIDA_MANUAL',
    'ENTREGA_SOLICITUD',
    'DEVOLUCIÓN',
    'ELIMINACION_TOTAL'
];

// Función para generar la URL de paginación (manteniendo filtros)
function generarUrlPaginacion($pagina)
{
    $params = $_GET;
    $params['p'] = $pagina;
    unset($params['mensaje']);
    return 'reportes.php?' . http_build_query($params);
}

?>
<div class="container main-content">
    <h2>Reporte de Movimientos de Inventario</h2>
    <?php echo $mensaje; ?>

    <form method="GET" action="reportes.php" class="mb-4 p-3 border rounded bg-light">
        <div class="row">
            <div class="col-md-3 form-group">
                <label for="fecha_inicio">Fecha Inicio:</label>
                <input type="date" class="form-control" name="fecha_inicio" id="fecha_inicio"
                    value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>">
            </div>
            <div class="col-md-3 form-group">
                <label for="fecha_fin">Fecha Fin:</label>
                <input type="date" class="form-control" name="fecha_fin" id="fecha_fin"
                    value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>">
            </div>
            <div class="col-md-3 form-group">
                <label for="codigo">Cód. Producto:</label>
                <input type="text" class="form-control" name="codigo" id="codigo"
                    value="<?php echo htmlspecialchars($filtro_codigo); ?>">
            </div>
            <div class="col-md-3 form-group">
                <label for="categoria">Categoría:</label>
                <select class="form-control" name="categoria" id="categoria">
                    <option value="">-- Todas --</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filtro_categoria === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($cat)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-3 form-group">
                <label for="tipo">Tipo Movimiento:</label>
                <select class="form-control" name="tipo" id="tipo">
                    <option value="">-- Todos --</option>
                    <?php foreach ($tipos_movimiento as $tipo): ?>
                        <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo ($filtro_tipo === $tipo) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(str_replace('_', ' ', $tipo)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 form-group d-flex align-items-end">
                <button type="submit" class="btn btn-primary mr-2">Filtrar Reporte</button>
                <a href="reportes.php" class="btn btn-secondary">Limpiar Filtros</a>
            </div>
        </div>
    </form>


    <p class="text-muted">Mostrando <?php echo $total_registros; ?> movimientos totales. (Página
        <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)</p>

    <?php if (!empty($movimientos)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover table-sm">
                <thead class="table-dark" style="text-align: center;">
                    <tr>
                        <th>FECHA</th>
                        <th>MOVIMIENTO</th>
                        <th>COD. PRODUCTO</th>
                        <th>PRODUCTO</th>
                        <th>CATEGORIA</th>
                        <th>CANTIDAD</th>
                        <th>#SOLICITUD</th>
                        <th>COMENTARIOS</th>
                        <th>STOCK ACTUAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                        <tr class="<?php echo ($mov['cantidad_afectada'] > 0) ? 'table-success' : 'table-danger'; ?>">
                            <td style="text-align: center;"><?php echo htmlspecialchars($mov['fecha_movimiento']); ?></td>
                            <td style="text-align: center;">
                                <strong><?php echo htmlspecialchars(str_replace('_', ' ', $mov['tipo_movimiento'])); ?></strong>
                            </td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($mov['codigo_producto']); ?></td>
                            <td><?php echo htmlspecialchars($mov['nombre_producto']); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars(ucfirst($mov['categoria'])); ?></td>
                            <td style="text-align: center;">
                                <strong class="text-primary"><?php echo htmlspecialchars($mov['saldo_restante']); ?></strong>
                            </td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($mov['referencia_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($mov['comentarios'] ?? ''); ?></td>
                            <td style="text-align: center;">
                                <span> <?php echo htmlspecialchars($mov['stock_actual_bd']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <nav aria-label="Navegación de páginas">
            <ul class="pagination justify-content-center">
                <?php if ($pagina_actual > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo generarUrlPaginacion($pagina_actual - 1); ?>">Anterior</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo generarUrlPaginacion($i); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo generarUrlPaginacion($pagina_actual + 1); ?>">Siguiente</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php else: ?>
        <p class="alert alert-info">No se encontraron movimientos de inventario con los filtros seleccionados.</p>
    <?php endif; ?>

    <?php require_once 'includes/footer.php'; ?>