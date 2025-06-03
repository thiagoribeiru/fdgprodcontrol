<?php
// config.php - Configuração de conexão com banco de dados

$host = 'localhost';
$dbname = 'controle_producao';
$username = 'root'; // Altere conforme sua configuração
$password = '';     // Altere conforme sua configuração

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Função para retornar dados em JSON
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Função para validar dados obrigatórios
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            return "O campo $field é obrigatório.";
        }
    }
    return null;
}
?>