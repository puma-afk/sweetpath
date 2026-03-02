<?php
// Abre en navegador: http://localhost:8012/sweetpath/admin/make_hash.php?pw=TUCLAVE
$pw = $_GET['pw'] ?? '';
if ($pw === '') exit("Pon ?pw=TUCLAVE");
echo password_hash($pw, PASSWORD_DEFAULT);