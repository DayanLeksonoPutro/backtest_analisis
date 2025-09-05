<?php
// Simulate the upload process with our new HTML file
$_POST["submit"] = "submit";
$_FILES["reportFile"]["name"] = "ReportTester-5036666090.html";
$_FILES["reportFile"]["tmp_name"] = "ReportTester-5036666090.html";

include 'upload_final.php';
?>