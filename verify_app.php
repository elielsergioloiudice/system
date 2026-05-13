<?php
header('Content-Type: application/json');

$file = 'system.json';

// 1. Leer el archivo
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(["error" => "Archivo no encontrado"]);
    }
    exit;
}

// 2. Modificar el archivo (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);

    if ($data) {
        if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT))) {
            echo json_encode(["status" => "success", "message" => "Datos guardados"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error al escribir en el archivo"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Datos inválidos"]);
    }
    exit;
}
?>
