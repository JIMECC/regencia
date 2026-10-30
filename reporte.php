<?php
require "sesion.php";
$pagina = "reporte";

$st = $conexion->prepare("SELECT id, nombre FROM periodo WHERE gestion_id = ? ORDER BY fecha_inicio");
$st->bind_param("i", $gestion_id);
$st->execute();
$periodos = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// Por defecto: el trimestre en el que estamos
$periodo_id = (int) ($_GET["periodo_id"] ?? 0);
if (!isset($_GET["periodo_id"])) {
    $hoy = date("Y-m-d");
    $st = $conexion->prepare("SELECT id FROM periodo WHERE gestion_id = ? AND ? BETWEEN fecha_inicio AND fecha_fin");
    $st->bind_param("is", $gestion_id, $hoy);
    $st->execute();
    $periodo_id = (int) ($st->get_result()->fetch_assoc()["id"] ?? 0);
}
$curso_id = (int) ($_GET["curso_id"] ?? 0);
$minimo   = 80;   // porcentaje mínimo de asistencia esperado

$st = $conexion->prepare(
    "SELECT v.* FROM v_resumen_asistencia v
     JOIN periodo p ON p.id = v.periodo_id
     WHERE p.gestion_id = ? AND (? = 0 OR v.periodo_id = ?) AND (? = 0 OR v.curso_id = ?)
     ORDER BY p.fecha_inicio, v.curso_id, v.estudiante"
);
$st->bind_param("iiiii", $gestion_id, $periodo_id, $periodo_id, $curso_id, $curso_id);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$en_riesgo = count(array_filter($filas, fn($f) => $f["porcentaje_asistencia"] < $minimo));

include "encabezado.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 m-0">Reporte de asistencia</h1>
    <a href="index.php" class="btn btn-outline-secondary d-print-none">&larr; Volver al inicio</a>
</div>

<form method="get" class="row g-2 mb-3 d-print-none">
    <div class="col-auto">
        <select name="periodo_id" class="form-select" onchange="this.form.submit()">
            <option value="0">Toda la gestión</option>
            <?php foreach ($periodos as $p): ?>
                <option value="<?= $p["id"] ?>" <?= $p["id"] == $periodo_id ? "selected" : "" ?>><?= h($p["nombre"]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="curso_id" class="form-select" onchange="this.form.submit()">
            <option value="0">Todos los cursos</option>
            <?php foreach ($cursos as $c): ?>
                <option value="<?= $c["id"] ?>" <?= $c["id"] == $curso_id ? "selected" : "" ?>><?= h($c["nombre"] . " - " . $c["turno"]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
    </div>
</form>

<?php if (!$filas): ?>
    <div class="alert alert-info">Todavía no hay asistencia registrada para este filtro.</div>
<?php else: ?>
    <?php if ($en_riesgo): ?>
        <div class="alert alert-warning"><?= $en_riesgo ?> estudiante(s) con menos de <?= $minimo ?> % de asistencia.</div>
    <?php endif; ?>
<div class="table-responsive">
<table class="table table-bordered table-sm align-middle bg-white">
    <thead class="table-light">
        <tr><th>Periodo</th><th>Curso</th><th>RUDE</th><th>Estudiante</th>
            <th class="text-center">P</th><th class="text-center">A</th><th class="text-center">F</th>
            <th class="text-center">L</th><th class="text-center">Días</th><th class="text-center">% Asistencia</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f):
        $pct = (float) $f["porcentaje_asistencia"];
        $color = $pct >= $minimo ? "success" : ($pct >= 60 ? "warning" : "danger"); ?>
        <tr>
            <td><?= h($f["periodo"]) ?></td>
            <td><?= h($f["curso"]) ?></td>
            <td><?= h($f["rude"]) ?></td>
            <td><?= h($f["estudiante"]) ?></td>
            <td class="text-center"><?= $f["presentes"] ?></td>
            <td class="text-center"><?= $f["atrasos"] ?></td>
            <td class="text-center"><?= $f["faltas"] ?></td>
            <td class="text-center"><?= $f["licencias"] ?></td>
            <td class="text-center"><?= $f["dias"] ?></td>
            <td class="text-center"><span class="badge text-bg-<?= $color ?>"><?= $pct ?> %</span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="small text-muted">% Asistencia = (presentes + atrasos) ÷ días registrados. La licencia cuenta como inasistencia justificada.</p>
<?php endif; ?>

<?php include "pie.php"; ?>
