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
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
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
$contador_condicion = 1;

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

// 4. Consulta para el Total de Registros (para la paginación)
$sql_count = "SELECT COUNT(*) FROM inventario_movimientos m" . $where_clause;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($parametros);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $limite_por_pagina);

// 5. Consulta para los Registros a Mostrar
$sql_data = $sql_base . $where_clause . " ORDER BY m.fecha_movimiento DESC LIMIT :limit OFFSET :offset";
$parametros[':limit'] = $limite_por_pagina;
$parametros[':offset'] = $offset;

try {
    $stmt_data = $pdo->prepare($sql_data);
    // Ejecutar con todos los parámetros de filtro y paginación
    $stmt_data->execute($parametros);
    $movimientos = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    // Obtener la lista de categorías (tablas) para el filtro
    $stmt_categorias = $pdo->query("SELECT nombre_categoria FROM categorias ORDER BY nombre_categoria");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $mensaje = "<p class='btn-danger'>❌ Error al cargar los movimientos: " . $e->getMessage() . "</p>";
    $movimientos = [];
}

// Tipos de Movimiento fijos (Basado en la implementación de los archivos anteriores)
$tipos_movimiento = [
    'INGRESO_NUEVO',
    'AJUSTE_ENTRADA_MANUAL',
    'AJUSTE_SALIDA_MANUAL',
    'ENTREGA_SOLICITUD',
    'REVERSION',
    'ELIMINACION_TOTAL'
];

// Función para generar la URL de paginación (manteniendo filtros)
function generarUrlPaginacion($pagina) {
    $params = $_GET;
    $params['p'] = $pagina;
    // Remueve 'mensaje' si existe
    unset($params['mensaje']);
    return 'reportes.php?' . http_build_query($params);
}

?>

<div class="container mt-5">
    <h2>Reporte de Movimientos de Inventario (Kárdex)</h2>
    <?php echo $mensaje; ?>

    <form method="GET" action="reportes.php" class="mb-4 p-3 border rounded bg-light">
        <div class="row">
            <div class="col-md-3 form-group">
                <label for="fecha_inicio">Fecha Inicio:</label>
                <input type="date" class="form-control" name="fecha_inicio" id="fecha_inicio" value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>">
            </div>
            <div class="col-md-3 form-group">
                <label for="fecha_fin">Fecha Fin:</label>
                <input type="date" class="form-control" name="fecha_fin" id="fecha_fin" value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>">
            </div>
            <div class="col-md-3 form-group">
                <label for="codigo">Cód. Producto:</label>
                <input type="text" class="form-control" name="codigo" id="codigo" value="<?php echo htmlspecialchars($filtro_codigo); ?>">
            </div>
            <div class="col-md-3 form-group">
                <label for="categoria">Categoría:</label>
                <select class="form-control" name="categoria" id="categoria">
                    <option value="">-- Todas --</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo ($filtro_categoria === $cat) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo htmlspecialchars($tipo); ?>"
                                <?php echo ($filtro_tipo === $tipo) ? 'selected' : ''; ?>>
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

    <p class="text-muted">Mostrando **<?php echo $total_registros; ?>** movimientos totales. (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)</p>

    <?php if (!empty($movimientos)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Tipo Movimiento</th>
                        <th>Cód. Producto</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Cantidad Afectada</th>
                        <th>Usuario</th>
                        <th>Ref. Solicitud</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                        <tr class="<?php echo ($mov['cantidad_afectada'] > 0) ? 'table-success' : 'table-danger'; ?>">
                            <td><?php echo htmlspecialchars($mov['id']); ?></td>
                            <td><?php echo htmlspecialchars($mov['fecha_movimiento']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars(str_replace('_', ' ', $mov['tipo_movimiento'])); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($mov['codigo_producto']); ?></td>
                            <td><?php echo htmlspecialchars($mov['nombre_producto']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($mov['categoria'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($mov['cantidad_afectada']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($mov['nombre_usuario'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($mov['referencia_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($mov['comentarios'] ?? ''); ?></td>
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
</div>

<?php require_once 'includes/footer.php'; ?>