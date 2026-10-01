<?php
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();

switch (departamentoUsuario()) {
    case "Qualidade":
        header("Location: status");
        break;
    case "RH":
    case "Marketing":
        header("Location: mural");
        break;
    case "TI":
        header("Location: usuarios");
        break;
    default:
        header("Location: ../index");
}
exit;
