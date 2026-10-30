<?php
require "sesion.php";
solo_para("Director");   
$pagina = "usuarios";

$roles = ["Regente", "Secretaria", "Director"];
$error = "";
$editar_id = (int) ($_GET["editar"] ?? 0);
$form = ["id" => 0, "nombre" => "", "usuario" => "", "rol" => "Regente"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    if ($accion === "activo") {
        $id = (int) $_POST["id"];
        if ($id === $usuario_id) {
            $error = "No puedes desactivar tu propia cuenta.";
        } else {
            $st = $conexion->prepare("UPDATE usuario SET activo = NOT activo WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            header("Location: usuarios.php?ok=1");
            exit;
        }
    }

    if ($accion === "guardar") {
        $form = [
            "id"      => (int) $_POST["id"],
            "nombre"  => trim($_POST["nombre"] ?? ""),
            "usuario" => strtolower(trim($_POST["usuario"] ?? "")),
            "rol"     => in_array($_POST["rol"] ?? "", $roles) ? $_POST["rol"] : "Regente",
        ];
        $clave = $_POST["clave"] ?? "";
        $editar_id = $form["id"];

        if ($form["nombre"] === "" || $form["usuario"] === "") {
            $error = "Escribe el nombre y el usuario.";
        } elseif (!$form["id"] && strlen($clave) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } elseif ($form["id"] && $clave !== "" && strlen($clave) < 6) {
            $error = "La nueva contraseña debe tener al menos 6 caracteres.";
        } elseif ($form["id"] === $usuario_id && $form["rol"] !== "Director") {
            $error = "No puedes quitarte el rol de Director a ti mismo.";
        } else {
            try {
                if ($form["id"]) {
                    $st = $conexion->prepare("UPDATE usuario SET nombre = ?, usuario = ?, rol = ? WHERE id = ?");
                    $st->bind_param("sssi", $form["nombre"], $form["usuario"], $form["rol"], $form["id"]);
                    $st->execute();
                    if ($clave !== "") {   // solo si escribió una nueva
                        $hash = password_hash($clave, PASSWORD_DEFAULT);
                        $st = $conexion->prepare("UPDATE usuario SET clave = ? WHERE id = ?");
                        $st->bind_param("si", $hash, $form["id"]);
                        $st->execute();
                    }
                } else {
                    $hash = password_hash($clave, PASSWORD_DEFAULT);
                    $st = $conexion->prepare("INSERT INTO usuario (nombre, usuario, clave, rol) VALUES (?, ?, ?, ?)");
                    $st->bind_param("ssss", $form["nombre"], $form["usuario"], $hash, $form["rol"]);
                    $st->execute();
                }
                header("Location: usuarios.php?ok=1");
                exit;
            } catch (mysqli_sql_exception $e) {
                $error = $e->getCode() == 1062 ? "Ese nombre de usuario ya existe. Elige otro." : "No se pudo guardar: " . $e->getMessage();
            }
        }
    }
}

if ($editar_id && $_SERVER["REQUEST_METHOD"] !== "POST") {
    $st = $conexion->prepare("SELECT id, nombre, usuario, rol FROM usuario WHERE id = ?");
    $st->bind_param("i", $editar_id);
    $st->execute();
    $form = $st->get_result()->fetch_assoc() ?: $form;
}

$usuarios = $conexion->query(
    "SELECT u.id, u.nombre, u.usuario, u.rol, u.activo,
            (SELECT COUNT(DISTINCT a.fecha) FROM asistencia a WHERE a.usuario_id = u.id) AS dias_registrados
     FROM usuario u
     ORDER BY u.activo DESC, FIELD(u.rol, 'Director', 'Regente', 'Secretaria'), u.nombre"
)->fetch_all(MYSQLI_ASSOC);

include "encabezado.php";
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 m-0">Usuarios del sistema</h1>
    <a href="index.php" class="btn btn-outline-secondary">&larr; Volver al inicio</a>
</div>

<?php if (isset($_GET["ok"])): ?><div class="alert alert-success">Cambios guardados.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Formulario -->
    <div class="col-lg-4">
        <form method="post" class="card card-body">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $form["id"] ?>">
            <h2 class="h5 mb-3"><?= $form["id"] ? "Editar usuario" : "Nuevo usuario" ?></h2>

            <label class="form-label">Nombre completo</label>
            <input name="nombre" class="form-control mb-3" maxlength="100" required value="<?= h($form["nombre"]) ?>">

            <label class="form-label">Usuario (para ingresar)</label>
            <input name="usuario" class="form-control mb-3" maxlength="30" required value="<?= h($form["usuario"]) ?>">

            <label class="form-label">Rol</label>
            <select name="rol" class="form-select mb-3">
                <?php foreach ($roles as $r): ?>
                    <option <?= $form["rol"] === $r ? "selected" : "" ?>><?= $r ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label"><?= $form["id"] ? "Nueva contraseña" : "Contraseña" ?></label>
            <input type="text" name="clave" class="form-control" minlength="6" <?= $form["id"] ? "" : "required" ?>>
            <div class="form-text mb-3">
                <?= $form["id"] ? "Déjala vacía para no cambiarla." : "Mínimo 6 caracteres." ?>
                Entrégasela a la persona en persona.
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Guardar</button>
                <?php if ($form["id"]): ?><a href="usuarios.php" class="btn btn-outline-secondary">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Lista -->
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th class="text-center">Días registrados</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr class="<?= $u["activo"] ? "" : "text-muted" ?>">
                        <td><?= h($u["nombre"]) ?><?= $u["id"] == $usuario_id ? ' <span class="badge text-bg-light">tú</span>' : "" ?></td>
                        <td><?= h($u["usuario"]) ?></td>
                        <td><?= h($u["rol"]) ?></td>
                        <td><span class="badge text-bg-<?= $u["activo"] ? "success" : "secondary" ?>"><?= $u["activo"] ? "Activo" : "Desactivado" ?></span></td>
                        <td class="text-center"><?= $u["dias_registrados"] ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="usuarios.php?editar=<?= $u["id"] ?>">Editar</a>
                            <?php if ($u["id"] != $usuario_id): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="accion" value="activo">
                                <input type="hidden" name="id" value="<?= $u["id"] ?>">
                                <button class="btn btn-sm <?= $u["activo"] ? "btn-outline-danger" : "btn-outline-success" ?>">
                                    <?= $u["activo"] ? "Desactivar" : "Activar" ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <p class="small text-muted mt-2">
            Cuando un regente o secretaria termina su contrato, <strong>desactiva</strong> su cuenta en lugar de borrarla:
            así ya no puede entrar, pero el sistema sigue mostrando qué días registró.
        </p>
    </div>
</div>

<?php include "pie.php"; ?>
