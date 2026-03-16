<?php
include "../config/db.php";
$id=$_GET['id'];
$conn->query("DELETE FROM services WHERE id=$id");
header("Location:view_services.php");
?>
