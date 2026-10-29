<?php
require('login.php');

if ($login) {
    $id = $_POST['id'];
    $origen = $_POST['origen'];

    $q = "UPDATE productnamesurlencoded SET origen='$origen' WHERE ID=$id";
    mysqli_query($link, $q) or die(mysqli_error($link));
}
//echo "<script>window.location.hash = 'origen.php#modificat';</script>";
header("Location: origen.php#$id");
exit();
?>
