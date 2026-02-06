<?php
include 'includes/db_mantenimiento.php';
include 'includes/headerM.php';

if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

$view = isset($_GET['view']) ? $_GET['view'] : 'tareas';
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'TODAS';

// --- LÓGICA DE PROCESAMIENTO CON PDO ---

// Guardar Trabajador
if (isset($_POST['guardar_trabajador'])) {
    $stmt = $pdo->prepare("INSERT INTO trabajadores (nombre, cargo) VALUES (?, ?)");
    $stmt->execute([$_POST['nombre_t'], $_POST['cargo_t']]);
    header("Location: mantenimiento.php?view=trabajadores");
    exit();
}

// Crear Tarea
if (isset($_POST['crear_tarea'])) {
    $ruta_antes = "uploads/antes_" . time() . "_" . $_FILES['foto_antes']['name'];

    if (move_uploaded_file($_FILES['foto_antes']['tmp_name'], $ruta_antes)) {
        // Insertar tarea
        $stmt = $pdo->prepare("INSERT INTO tareas (descripcion, foto_antes, fecha_intervencion) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['descripcion'], $ruta_antes, $_POST['fecha_intervencion']]);
        $id_tarea = $pdo->lastInsertId();

        // Asignar trabajadores (uno o muchos)
        foreach ($_POST['trabajadores'] as $id_w) {
            $stmtAsig = $pdo->prepare("INSERT INTO asignaciones (tarea_id, trabajador_id) VALUES (?, ?)");
            $stmtAsig->execute([$id_tarea, $id_w]);
        }
    }
}

// Finalizar Tarea
if (isset($_POST['finalizar_tarea'])) {
    $ruta_despues = "uploads/despues_" . time() . "_" . $_FILES['foto_despues']['name'];
    if (move_uploaded_file($_FILES['foto_despues']['tmp_name'], $ruta_despues)) {
        $stmt = $pdo->prepare("UPDATE tareas SET foto_despues=?, estado='Completada', fecha_finalizacion=NOW() WHERE id=?");
        $stmt->execute([$ruta_despues, $_POST['id_tarea']]);
    }
}
?>
<style>
    /* Contenedor de imágenes responsivo */
    .img-box {
        width: 100%;
        max-width: 120px;
        /* Tamaño máximo en escritorio */
        aspect-ratio: 1 / 1;
        /* Mantiene proporciones cuadradas */
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .img-box:hover {
        transform: scale(1.05);
    }

    /* Adaptación a dispositivos móviles */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr !important;
        }

        .img-box {
            max-width: 80px;
        }

        table {
            font-size: 0.85rem;
        }

        th,
        td {
            padding: 8px;
        }
    }

    /* Botones de acción */
    .btn-edit {
        background: #3b82f6;
        color: white;
        padding: 5px 10px;
        margin-right: 5px;
    }

    .btn-delete {
        background: #ef4444;
        color: white;
        padding: 5px 10px;
    }
</style>


<body>
    <div class="container main-content">
        <nav>
            <a href="?view=tareas" class="<?= $view == 'tareas' ? 'active' : '' ?>">📋 TAREAS</a>
            <a href="?view=trabajadores" class="<?= $view == 'trabajadores' ? 'active' : '' ?>">👥 PERSONAL</a>
        </nav>
    </div>
    <br>
    <div class="container main-content">

        <?php if ($view == 'trabajadores'): ?>
            <div class="card">
                <h2>Registrar Nuevo Trabajador</h2>
                <form method="POST" style="display: flex; gap: 15px;">
                    <input type="text" name="nombre_t" placeholder="Nombre completo" required style="flex: 2; padding: 10px;">
                    <input type="text" name="cargo_t" placeholder="Cargo/Especialidad" style="flex: 1; padding: 10px;">
                    <button type="submit" name="guardar_trabajador" class="btn btn-blue">Guardar Personal</button>
                </form>
            </div>
            <div class="card">
                <table>
                    <tr>
                        <th>Nombre</th>
                        <th>Cargo</th>
                        <th>Ingreso</th>
                    </tr>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM trabajadores ORDER BY nombre ASC");
                    while ($t = $stmt->fetch()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($t['cargo']) ?></td>
                            <td><?= $t['fecha_registro'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>

        <?php else: ?>
            <div class="card">
                <h2>Crear Nueva Tarea de Mantenimiento</h2>
                <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                    <input type="text" name="descripcion" placeholder="Descripción de la anomalía" required style="grid-column: span 3; padding: 10px;">
                    <div>
                        <label>Fecha Intervención:</label>
                        <input type="date" name="fecha_intervencion" required style="width: 100%;">
                    </div>
                    <div>
                        <label>Asignar Personal:</label>
                        <select name="trabajadores[]" multiple required style="width: 100%; height: 60px;">
                            <?php
                            $stmt = $pdo->query("SELECT * FROM trabajadores");
                            while ($t = $stmt->fetch()): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label>Foto Antes:</label>
                        <input type="file" name="foto_antes" accept="image/*" required>
                    </div>
                    <button type="submit" name="crear_tarea" class="btn btn-blue" style="grid-column: span 3;">ASIGNAR TAREA</button>
                </form>
            </div>

            <div class="card">
                <div class="filter-bar">
                    <strong>Ver:</strong>
                    <a href="?view=tareas&filtro=TODAS" class="filter-btn <?= $filtro == 'TODAS' ? 'active' : '' ?>">Todas</a>
                    <a href="?view=tareas&filtro=Pendiente" class="filter-btn <?= $filtro == 'Pendiente' ? 'active' : '' ?>">Pendientes</a>
                    <a href="?view=tareas&filtro=Completada" class="filter-btn <?= $filtro == 'Completada' ? 'active' : '' ?>">Completadas</a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Asignados</th>
                            <th>Programado</th>
                            <th>Evidencias</th>
                            <th>Acción Empleado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT t.*, GROUP_CONCAT(tr.nombre SEPARATOR ', ') as nombres 
                            FROM tareas t 
                            LEFT JOIN asignaciones a ON t.id = a.tarea_id 
                            LEFT JOIN trabajadores tr ON a.trabajador_id = tr.id ";

                        if ($filtro != 'TODAS') {
                            $sql .= " WHERE t.estado = :filtro ";
                        }

                        $sql .= " GROUP BY t.id ORDER BY t.id DESC";

                        $stmt = $pdo->prepare($sql);
                        if ($filtro != 'TODAS') {
                            $stmt->execute(['filtro' => $filtro]);
                        } else {
                            $stmt->execute();
                        }

                        while ($r = $stmt->fetch()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($r['descripcion']) ?></strong><br>
                                    <span class="badge <?= $r['estado'] ?>"><?= $r['estado'] ?></span>
                                </td>
                                <td><small><?= htmlspecialchars($r['nombres']) ?></small></td>
                                <td><?= $r['fecha_intervencion'] ?></td>
                                <td>
                                    <img src="<?= $r['foto_antes'] ?>" class="img-box" title="Antes">
                                    <?php if ($r['foto_despues']): ?>
                                        <img src="<?= $r['foto_despues'] ?>" class="img-box" title="Después">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['estado'] == 'Pendiente'): ?>
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="id_tarea" value="<?= $r['id'] ?>">
                                            <input type="file" name="foto_despues" required style="font-size: 10px;"><br>
                                            <button type="submit" name="finalizar_tarea" class="btn btn-green" style="padding: 5px; margin-top:5px; width: 100%;">Cerrar Tarea</button>
                                        </form>
                                    <?php else: ?>
                                        <small>Fin: <?= $r['fecha_finalizacion'] ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>