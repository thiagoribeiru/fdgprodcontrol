<?php
// test_api.php - Arquivo para testar a API e conexão com banco

// Mostrar erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste da API - Sistema de Controle de Produção</h1>";

// Teste 1: Conexão com banco de dados
echo "<h2>1. Teste de Conexão com Banco</h2>";

$host = 'localhost';
$dbname = 'controle_producao';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ <strong>Conexão com banco: OK</strong><br>";
    
    // Verificar se as tabelas existem
    $tables = ['pedidos', 'itens', 'processos', 'item_processos', 'pedido_itens'];
    echo "<h3>Verificando tabelas:</h3>";
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "✅ Tabela '$table': $count registros<br>";
        } catch (Exception $e) {
            echo "❌ Tabela '$table': ERRO - " . $e->getMessage() . "<br>";
        }
    }
    
} catch(PDOException $e) {
    echo "❌ <strong>Erro de conexão:</strong> " . $e->getMessage() . "<br>";
    echo "<p><strong>Soluções:</strong></p>";
    echo "<ul>";
    echo "<li>Verifique se o MySQL está rodando</li>";
    echo "<li>Confirme as credenciais (usuário: $username, banco: $dbname)</li>";
    echo "<li>Execute o script SQL para criar o banco e tabelas</li>";
    echo "</ul>";
    exit;
}

// Teste 2: Verificar se a API responde
echo "<h2>2. Teste da API</h2>";

// Construir URL correta para o ambiente local
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['REQUEST_URI']);
$api_url = $protocol . '://' . $host . $path . '/api.php';

echo "URL da API: <code>$api_url</code><br>";

// Teste direto sem HTTP request (mais confiável)
echo "<h3>Testando API diretamente:</h3>";

// Simular chamada GET
$_GET['action'] = 'get_pedidos';

// Capturar saída da API
ob_start();
try {
    // Incluir a API diretamente
    if (file_exists('api.php')) {
        include 'api.php';
    } else {
        echo "❌ <strong>Arquivo api.php não encontrado</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Erro ao executar API:</strong> " . $e->getMessage() . "<br>";
}
$api_output = ob_get_clean();

// Limpar $_GET para não interferir
unset($_GET['action']);

if (!empty($api_output)) {
    $data = json_decode($api_output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ <strong>API funcionando corretamente</strong><br>";
        echo "Resposta JSON válida com " . count($data) . " registros<br>";
        if (count($data) > 0) {
            echo "Exemplo de dados: <pre>" . htmlspecialchars(json_encode($data[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        }
    } else {
        echo "❌ <strong>API retornou dados inválidos:</strong><br>";
        echo "<pre>" . htmlspecialchars($api_output) . "</pre>";
    }
} else {
    echo "❌ <strong>API não retornou dados</strong><br>";
}

// Teste adicional via cURL se disponível
echo "<h3>Teste via cURL (se disponível):</h3>";
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '?action=get_pedidos');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ <strong>Erro cURL:</strong> $error<br>";
    } elseif ($http_code !== 200) {
        echo "❌ <strong>HTTP Status:</strong> $http_code<br>";
    } else {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ <strong>cURL funcionando:</strong> " . count($data) . " registros<br>";
        } else {
            echo "❌ <strong>cURL retornou dados inválidos:</strong><br>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        }
    }
} else {
    echo "ℹ️ cURL não disponível neste servidor<br>";
}

// Teste 3: Verificar configuração do PHP
echo "<h2>3. Configuração do PHP</h2>";

$required_extensions = ['pdo', 'pdo_mysql', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extensão '$ext': OK<br>";
    } else {
        echo "❌ Extensão '$ext': NÃO INSTALADA<br>";
    }
}

echo "<h3>Informações do PHP:</h3>";
echo "Versão do PHP: " . phpversion() . "<br>";
echo "Limite de memória: " . ini_get('memory_limit') . "<br>";
echo "Tempo máximo de execução: " . ini_get('max_execution_time') . "s<br>";

// Teste 4: Verificar arquivos
echo "<h2>4. Verificação de Arquivos</h2>";

$required_files = [
    'index.html' => 'Página inicial',
    'adm.html' => 'Página de administração', 
    'style.css' => 'Estilos CSS',
    'script.js' => 'JavaScript principal',
    'api.php' => 'API principal',
    'config.php' => 'Configuração (opcional)'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $file ($description): OK<br>";
    } else {
        echo "❌ $file ($description): NÃO ENCONTRADO<br>";
    }
}

// Teste 5: Dados de exemplo
echo "<h2>5. Inserir Dados de Teste</h2>";

if (isset($_GET['insert_test_data'])) {
    try {
        $pdo->beginTransaction();
        
        // Limpar dados existentes
        $pdo->exec("DELETE FROM pedido_itens");
        $pdo->exec("DELETE FROM item_processos");
        $pdo->exec("DELETE FROM pedidos");
        $pdo->exec("DELETE FROM itens");
        $pdo->exec("DELETE FROM processos");
        
        // Inserir processos
        $stmt = $pdo->prepare("INSERT INTO processos (nome, descricao, ordem) VALUES (?, ?, ?)");
        $processos = [
            ['Corte', 'Processo de corte do material', 1],
            ['Personalização', 'Processo de personalização do produto', 2],
            ['Produção', 'Processo de produção/montagem', 3],
            ['Expedição', 'Processo de preparação para envio', 4]
        ];
        
        foreach ($processos as $processo) {
            $stmt->execute($processo);
        }
        
        // Inserir itens
        $stmt = $pdo->prepare("INSERT INTO itens (nome, descricao) VALUES (?, ?)");
        $itens = [
            ['Camiseta Básica', 'Camiseta básica de algodão'],
            ['Caneca Personalizada', 'Caneca branca para personalização'],
            ['Chaveiro Acrílico', 'Chaveiro de acrílico transparente']
        ];
        
        foreach ($itens as $item) {
            $stmt->execute($item);
        }
        
        // Inserir pedido de teste
        $stmt = $pdo->prepare("
            INSERT INTO pedidos (data_entrada, data_entrega, codigo_pedido, cliente, processo_atual) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            date('Y-m-d'),
            date('Y-m-d', strtotime('+7 days')),
            'PED-001',
            'Cliente Teste',
            'corte'
        ]);
        
        $pdo->commit();
        echo "✅ <strong>Dados de teste inseridos com sucesso!</strong><br>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ <strong>Erro ao inserir dados:</strong> " . $e->getMessage() . "<br>";
    }
} else {
    echo "<a href='?insert_test_data=1' style='background: #4CAF50; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Inserir Dados de Teste</a><br>";
}

echo "<br><hr>";
echo "<h2>Próximos Passos</h2>";
echo "<ol>";
echo "<li>Se todos os testes passaram, acesse <a href='index.html'>index.html</a></li>";
echo "<li>Se houver erros, corrija as configurações indicadas</li>";
echo "<li>Para debug, verifique o arquivo <code>api_errors.log</code></li>";
echo "<li>Após tudo funcionando, remova este arquivo de teste</li>";
echo "</ol>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #333; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
ul, ol { margin-left: 20px; }
a { color: #4CAF50; }
hr { margin: 30px 0; border: none; border-top: 2px solid #ddd; }
</style>