<?php
require "sesion.php";
$pagina = "inicio";

$hoy   = date("Y-m-d");
$fecha = fecha_valida($_GET["fecha"] ?? "") ? $_GET["fecha"] : $hoy;

$st = $conexion->prepare(
    "SELECT c.id, " . SQL_CURSO . " AS curso, c.turno,
            COUNT(i.id)  AS inscritos,
            COUNT(a.id)  AS registrados,
            IFNULL(SUM(ea.codigo = 'P'), 0) AS p,
            IFNULL(SUM(ea.codigo = 'F'), 0) AS f,
            IFNULL(SUM(ea.codigo = 'A'), 0) AS a,
            IFNULL(SUM(ea.codigo = 'L'), 0) AS l
     FROM curso c
     JOIN nivel n ON n.id = c.nivel_id
     LEFT JOIN inscripcion i ON i.curso_id = c.id AND i.gestion_id = ? AND i.estado = 'Efectivo'
     LEFT JOIN asistencia a  ON a.inscripcion_id = i.id AND a.fecha = ?
     LEFT JOIN estado_asistencia ea ON ea.id = a.estado_id
     GROUP BY c.id, n.id, n.nombre, c.grado, c.paralelo, c.turno
     ORDER BY n.id, c.grado, c.paralelo, c.turno"
);
$st->bind_param("is", $gestion_id, $fecha);
$st->execute();
$resumen = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$total = ["inscritos" => 0, "p" => 0, "f" => 0, "a" => 0, "l" => 0, "pendientes" => 0];
foreach ($resumen as $r) {
    foreach (["inscritos", "p", "f", "a", "l"] as $k) $total[$k] += $r[$k];
    if ($r["inscritos"] > 0 && $r["registrados"] < $r["inscritos"]) $total["pendientes"]++;
}

$guardado = null;
foreach ($resumen as $r) {
    if (isset($_GET["ok"]) && (int) $_GET["ok"] === (int) $r["id"]) $guardado = $r;
}

include "encabezado.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 m-0">Asistencia del <?= dia_semana($fecha) ?> <?= fecha_es($fecha) ?></h1>
    <form method="get" class="d-flex gap-2">
        <input type="date" name="fecha" class="form-control" value="<?= h($fecha) ?>" max="<?= $hoy ?>" onchange="this.form.submit()">
        <?php if ($fecha !== $hoy): ?><a href="index.php" class="btn btn-outline-secondary">Hoy</a><?php endif; ?>
    </form>
</div>

<?php if (isset($_GET["sin_permiso"])): ?>
    <div class="alert alert-warning">No tienes permiso para entrar a esa página.</div>
<?php endif; ?>

<?php if ($guardado): ?>
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong>Asistencia guardada:</strong> <?= h($guardado["curso"]) ?> —
            <?= $guardado["p"] ?> presentes, <?= $guardado["a"] ?> atrasos,
            <?= $guardado["f"] ?> faltas, <?= $guardado["l"] ?> licencias.
        </div>
        <a class="btn btn-sm btn-outline-success" href="asistencia.php?curso_id=<?= $guardado["id"] ?>&fecha=<?= h($fecha) ?>">Corregir</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $tarjetas = [
        ["Estudiantes", $total["inscritos"], "primary"],
        ["Presentes",   $total["p"],         "success"],
        ["Atrasos",     $total["a"],         "warning"],
        ["Faltas",      $total["f"],         "danger"],
        ["Licencias",   $total["l"],         "info"],
        ["Cursos sin registrar", $total["pendientes"], "secondary"],
    ];
    foreach ($tarjetas as [$titulo, $valor, $color]): ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-<?= $color ?> h-100"><div class="card-body">
                <div class="small text-muted"><?= $titulo ?></div>
                <div class="fs-2 text-<?= $color ?>"><?= $valor ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Curso</th><th>Turno</th><th class="text-center">Estudiantes</th>
                <th class="text-center">P</th><th class="text-center">A</th>
                <th class="text-center">F</th><th class="text-center">L</th>
                <th>Estado</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($resumen as $r):
            if ($r["inscritos"] == 0)                       { $estado = ["Sin estudiantes", "light text-dark"]; }
            elseif ($r["registrados"] == 0)                  { $estado = ["Pendiente", "secondary"]; }
            elseif ($r["registrados"] < $r["inscritos"])     { $estado = ["Incompleto", "warning"]; }
            else                                             { $estado = ["Registrado", "success"]; } ?>
            <tr>
                <td><?= h($r["curso"]) ?></td>
                <td><?= h($r["turno"]) ?></td>
                <td class="text-center"><?= $r["inscritos"] ?></td>
                <td class="text-center"><?= $r["p"] ?></td>
                <td class="text-center"><?= $r["a"] ?></td>
                <td class="text-center"><?= $r["f"] ?></td>
                <td class="text-center"><?= $r["l"] ?></td>
                <td><span class="badge text-bg-<?= $estado[1] ?>"><?= $estado[0] ?></span></td>
                <td class="text-end">
                    <?php if ($r["inscritos"] > 0 && $rol !== "Secretaria"): ?>
                        <a class="btn btn-sm <?= $r["registrados"] ? "btn-outline-primary" : "btn-primary" ?>"
                           href="asistencia.php?curso_id=<?= $r["id"] ?>&fecha=<?= h($fecha) ?>">
                            <?= $r["registrados"] ? "Ver / corregir" : "Registrar" ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include "pie.php"; ?>
