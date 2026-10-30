<?php
require "sesion.php";
$pagina = "estudiantes";

$q        = trim($_GET["q"] ?? "");
$curso_id = (int) ($_GET["curso_id"] ?? 0);
$like     = "%$q%";

$st = $conexion->prepare(
    "SELECT e.id, e.rude, e.ci,
            CONCAT(e.apellido_paterno, ' ', IFNULL(e.apellido_materno, ''), ', ', e.nombres) AS nombre,
            " . SQL_CURSO . " AS curso, i.estado,
            CONCAT(t.nombres, ' ', t.apellidos) AS tutor, t.telefono
     FROM estudiante e
     LEFT JOIN inscripcion i ON i.estudiante_id = e.id AND i.gestion_id = ?
     LEFT JOIN curso c ON c.id = i.curso_id
     LEFT JOIN nivel n ON n.id = c.nivel_id
     LEFT JOIN (SELECT estudiante_id, MIN(tutor_id) AS tutor_id FROM estudiante_tutor GROUP BY estudiante_id) et
            ON et.estudiante_id = e.id
     LEFT JOIN tutor t ON t.id = et.tutor_id
     WHERE (? = '' OR CONCAT_WS(' ', e.nombres, e.apellido_paterno, e.apellido_materno, e.rude, e.ci) LIKE ?)
       AND (? = 0 OR i.curso_id = ?)
     ORDER BY c.id IS NULL, n.id, c.grado, c.paralelo, e.apellido_paterno, e.apellido_materno, e.nombres"
);
$st->bind_param("issii", $gestion_id, $q, $like, $curso_id, $curso_id);
$st->execute();
$lista = $st->get_result()->fetch_all(MYSQLI_ASSOC);

include "encabezado.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 m-0">Estudiantes · Gestión <?= h($gestion["anio"]) ?></h1>
    <a href="estudiante_form.php" class="btn btn-primary">+ Nuevo estudiante</a>
</div>

<?php if (isset($_GET["ok"])): ?>
    <div class="alert alert-success">Datos del estudiante guardados correctamente.</div>
<?php endif; ?>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-6">
        <input type="search" name="q" class="form-control" placeholder="Buscar por nombre, apellido, RUDE o CI" value="<?= h($q) ?>">
    </div>
    <div class="col-md-4">
        <select name="curso_id" class="form-select" onchange="this.form.submit()">
            <option value="0">Todos los cursos</option>
            <?php foreach ($cursos as $c): ?>
                <option value="<?= $c["id"] ?>" <?= $c["id"] == $curso_id ? "selected" : "" ?>><?= h($c["nombre"] . " - " . $c["turno"]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-secondary w-100">Buscar</button></div>
</form>

<div class="card">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>RUDE</th><th>Estudiante</th><th>Curso</th><th>Estado</th><th>Tutor</th><th>Teléfono</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$lista): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron estudiantes.</td></tr>
        <?php endif; ?>
        <?php foreach ($lista as $e):
            $color = ["Efectivo" => "success", "Retirado" => "secondary", "Trasladado" => "warning"][$e["estado"]] ?? "light text-dark"; ?>
            <tr>
                <td><?= h($e["rude"]) ?></td>
                <td><?= h($e["nombre"]) ?></td>
                <td><?= h($e["curso"] ?? "Sin inscribir") ?></td>
                <td><span class="badge text-bg-<?= $color ?>"><?= h($e["estado"] ?? "—") ?></span></td>
                <td><?= h($e["tutor"]) ?></td>
                <td><?= h($e["telefono"]) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="estudiante_form.php?id=<?= $e["id"] ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<p class="small text-muted mt-2"><?= count($lista) ?> estudiante(s)</p>

<?php include "pie.php"; ?>
