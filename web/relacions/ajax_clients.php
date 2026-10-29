<?php
require('../scale/login.php');
if(!$login){die(json_encode(['error' => 'No login']));}

$search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
$search = $link->real_escape_string($search);

if(strlen($search) < 1){
    die(json_encode([]));
}

$q = "SELECT ID, `Value` FROM Clients WHERE `Value` LIKE '%{$search}%' ORDER BY `Value` LIMIT 50";
$result = $link->query($q);

$clients = [];
while($row = $result->fetch_assoc()){
    $clients[] = ['id' => $row['ID'], 'name' => urldecode($row['Value'])];
}

header('Content-Type: application/json');
echo json_encode($clients);
