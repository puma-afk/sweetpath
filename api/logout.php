<?php
session_start();
// Limpiamos solo las variables del cliente para no afectar al admin si está abierto
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);
unset($_SESSION['user_avatar']);

// Redirigir a la página de inicio
header("Location: ../public/index.html");
exit;