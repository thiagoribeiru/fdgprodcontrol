<?php
// api.php - Versão otimizada para WAMP64

// Configurar headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a requisições OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configurar error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Função para resposta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Função para log de erros
function logError($message) {
    $logFile = __DIR__ . '/api_errors.log';
    $timestamp = date('[Y-m-d H:i:s] ');
    file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Configuração do banco - AJUSTE CONFORME SUA CONFIGURAÇÃO WAMP
$config = [
    'host' => 'localhost',
    'port' => '3306',        // Porta padrão do MySQL no WAMP
    'dbname' => 'controle_producao',
    'username' => 'root',
    'password' => ''         // Geralmente vazio no WAMP
];

// Conectar ao banco
try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    logError("Conexão falhou: " . $e->getMessage());
    jsonResponse([
        'error' => 'Erro de conexão com banco de dados',
        'details' => $e->getMessage(),
        'config' => "Host: {$config['host']}, DB: {$config['dbname']}, User: {$config['username']}"
    ], 500);
}

// Obter ação
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Router principal
try {
    switch ($action) {
        case 'get_pedidos':
            getPedidos($pdo);
            break;
            
        case 'add_pedido':
            addPedido($pdo);
            break;
            
        case 'delete_pedido':
            deletePedido($pdo);
            break;
            
        case 'get_itens':
            getItens($pdo);
            break;
            
        case 'add_item':
            addItem($pdo);
            break;
            
        case 'delete_item':
            deleteItem($pdo);
            break;
            
        case 'get_processos':
            getProcessos($pdo);
            break;
            
        case 'add_processo':
            addProcesso($pdo);
            break;
            
        case 'update_processo':
            updateProcesso($pdo);
            break;
            
        case 'delete_processo':
            deleteProcesso($pdo);
            break;
            
        case 'get_item_processos':
            getItemProcessos($pdo);
            break;
            
        case 'add_item_processo':
            addItemProcesso($pdo);
            break;
            
        case 'delete_item_processo':
            deleteItemProcesso($pdo);
            break;
            
        case 'get_pedido_itens':
            getPedidoItens($pdo);
            break;
            
        case 'update_processo_pedido':
            updateProcessoPedido($pdo);
            break;
            
        case 'test':
            jsonResponse(['status' => 'API funcionando', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
            
        default:
            jsonResponse(['error' => 'Ação não encontrada: ' . $action], 404);
    }
    
} catch (Exception $e) {
    logError("Erro na ação '$action': " . $e->getMessage());
    jsonResponse(['error' => 'Erro interno do servidor', 'action' => $action], 500);
}

// === FUNÇÕES DA API ===

function getPedidos($pdo) {
    try {
        $sql = "
            SELECT p.*, COUNT(pi.id) as total_itens 
            FROM pedidos p 
            LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id 
            GROUP BY p.id 
            ORDER BY p.data_entrada DESC
        ";
        
        $stmt = $pdo->query($sql);
        $pedidos = $stmt->fetchAll();
        
        jsonResponse($pedidos);
        
    } catch (Exception $e) {
        logError("getPedidos: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar pedidos'], 500);
    }
}

function addPedido($pdo) {
    try {
        // Obter dados JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        // Validar campos obrigatórios
        $required = ['data_entrada', 'data_entrega', 'codigo_pedido', 'cliente', 'processo_atual'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                jsonResponse(['error' => "Campo '$field' é obrigatório"], 400);
            }
        }
        
        // Verificar se código já existe
        $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE codigo_pedido = ?");
        $stmt->execute([$data['codigo_pedido']]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Código do pedido já existe'], 400);
        }
        
        $pdo->beginTransaction();
        
        // Inserir pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedidos (data_entrada, data_entrega, codigo_pedido, cliente, processo_atual) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['data_entrada'],
            $data['data_entrega'],
            $data['codigo_pedido'],
            $data['cliente'],
            $data['processo_atual']
        ]);
        
        $pedido_id = $pdo->lastInsertId();
        
        // Inserir itens se houver
        if (!empty($data['itens']) && is_array($data['itens'])) {
            $stmt_item = $pdo->prepare("
                INSERT INTO pedido_itens (pedido_id, item_id, quantidade, observacoes) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($data['itens'] as $item) {
                if (isset($item['item_id']) && isset($item['quantidade'])) {
                    $stmt_item->execute([
                        $pedido_id,
                        $item['item_id'],
                        $item['quantidade'],
                        $item['observacoes'] ?? ''
                    ]);
                }
            }
        }
        
        $pdo->commit();
        jsonResponse(['success' => true, 'pedido_id' => $pedido_id]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("addPedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao salvar pedido: ' . $e->getMessage()], 500);
    }
}

function deletePedido($pdo) {
    try {
        $pedido_id = $_GET['id'] ?? 0;
        
        if (!$pedido_id) {
            jsonResponse(['error' => 'ID do pedido é obrigatório'], 400);
        }
        
        $pdo->beginTransaction();
        
        // Remover itens primeiro
        $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE pedido_id = ?");
        $stmt->execute([$pedido_id]);
        
        // Remover pedido
        $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
        $stmt->execute([$pedido_id]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Pedido não encontrado'], 404);
        }
        
        $pdo->commit();
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("deletePedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao excluir pedido'], 500);
    }
}

function getItens($pdo) {
    try {
        $sql = "
            SELECT i.*, COUNT(ip.id) as total_processos
            FROM itens i
            LEFT JOIN item_processos ip ON i.id = ip.item_id
            GROUP BY i.id
            ORDER BY i.nome
        ";
        
        $stmt = $pdo->query($sql);
        $itens = $stmt->fetchAll();
        
        jsonResponse($itens);
        
    } catch (Exception $e) {
        logError("getItens: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar itens'], 500);
    }
}

function addItem($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        if (empty($data['nome'])) {
            jsonResponse(['error' => 'Nome do item é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("INSERT INTO itens (nome, descricao) VALUES (?, ?)");
        $stmt->execute([$data['nome'], $data['descricao'] ?? '']);
        
        $item_id = $pdo->lastInsertId();
        jsonResponse(['success' => true, 'item_id' => $item_id]);
        
    } catch (Exception $e) {
        logError("addItem: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao salvar item'], 500);
    }
}

function deleteItem($pdo) {
    try {
        $item_id = $_GET['id'] ?? 0;
        
        if (!$item_id) {
            jsonResponse(['error' => 'ID do item é obrigatório'], 400);
        }
        
        $pdo->beginTransaction();
        
        // Verificar se está sendo usado em pedidos
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   GROUP_CONCAT(DISTINCT p.codigo_pedido SEPARATOR ', ') as pedidos_afetados
            FROM pedido_itens pi
            JOIN pedidos p ON pi.pedido_id = p.id
            WHERE pi.item_id = ?
        ");
        $stmt->execute([$item_id]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $pdo->rollBack();
            jsonResponse([
                'error' => 'Item está sendo usado em pedidos',
                'details' => "Pedidos afetados: {$result['pedidos_afetados']}",
                'count' => $result['total']
            ], 400);
        }
        
        // Buscar nome do item para log
        $stmt = $pdo->prepare("SELECT nome FROM itens WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Item não encontrado'], 404);
        }
        
        // Remover processos do item (receitas)
        $stmt = $pdo->prepare("DELETE FROM item_processos WHERE item_id = ?");
        $stmt->execute([$item_id]);
        $processosRemovidos = $stmt->rowCount();
        
        // Remover item
        $stmt = $pdo->prepare("DELETE FROM itens WHERE id = ?");
        $stmt->execute([$item_id]);
        
        $pdo->commit();
        
        logError("Item excluído: {$item['nome']} (ID: $item_id) - $processosRemovidos processos removidos");
        jsonResponse([
            'success' => true, 
            'message' => "Item '{$item['nome']}' excluído com sucesso",
            'processos_removidos' => $processosRemovidos
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("deleteItem: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao excluir item'], 500);
    }
}

function getProcessos($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT p.*, COUNT(ip.id) as total_usos
            FROM processos p
            LEFT JOIN item_processos ip ON p.id = ip.processo_id
            GROUP BY p.id
            ORDER BY p.ordem, p.nome
        ");
        $processos = $stmt->fetchAll();
        jsonResponse($processos);
    } catch (Exception $e) {
        logError("getProcessos: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar processos'], 500);
    }
}

function addProcesso($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        if (empty($data['nome'])) {
            jsonResponse(['error' => 'Nome do processo é obrigatório'], 400);
        }
        
        // Verificar se já existe processo com mesmo nome
        $stmt = $pdo->prepare("SELECT id FROM processos WHERE nome = ?");
        $stmt->execute([$data['nome']]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Já existe um processo com este nome'], 400);
        }
        
        // Se não informou ordem, pegar a próxima disponível
        if (empty($data['ordem'])) {
            $stmt = $pdo->query("SELECT COALESCE(MAX(ordem), 0) + 1 as proxima_ordem FROM processos");
            $result = $stmt->fetch();
            $data['ordem'] = $result['proxima_ordem'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO processos (nome, descricao, ordem) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['nome'], 
            $data['descricao'] ?? '', 
            $data['ordem']
        ]);
        
        $processo_id = $pdo->lastInsertId();
        jsonResponse(['success' => true, 'processo_id' => $processo_id]);
        
    } catch (Exception $e) {
        logError("addProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao salvar processo'], 500);
    }
}

function updateProcesso($pdo) {
    try {
        $processo_id = $_GET['id'] ?? 0;
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        if (!$processo_id) {
            jsonResponse(['error' => 'ID do processo é obrigatório'], 400);
        }
        
        if (empty($data['nome'])) {
            jsonResponse(['error' => 'Nome do processo é obrigatório'], 400);
        }
        
        // Verificar se já existe outro processo com mesmo nome
        $stmt = $pdo->prepare("SELECT id FROM processos WHERE nome = ? AND id != ?");
        $stmt->execute([$data['nome'], $processo_id]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Já existe outro processo com este nome'], 400);
        }
        
        $stmt = $pdo->prepare("
            UPDATE processos 
            SET nome = ?, descricao = ?, ordem = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['nome'],
            $data['descricao'] ?? '',
            $data['ordem'],
            $processo_id
        ]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Processo não encontrado'], 404);
        }
        
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        logError("updateProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao atualizar processo'], 500);
    }
}

function deleteProcesso($pdo) {
    try {
        $processo_id = $_GET['id'] ?? 0;
        
        if (!$processo_id) {
            jsonResponse(['error' => 'ID do processo é obrigatório'], 400);
        }
        
        // Verificar se está sendo usado em receitas de itens
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   GROUP_CONCAT(DISTINCT i.nome SEPARATOR ', ') as itens_afetados
            FROM item_processos ip
            JOIN itens i ON ip.item_id = i.id
            WHERE ip.processo_id = ?
        ");
        $stmt->execute([$processo_id]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            jsonResponse([
                'error' => 'Processo está sendo usado em receitas de itens',
                'details' => "Itens afetados: {$result['itens_afetados']}",
                'count' => $result['total']
            ], 400);
        }
        
        // Verificar se é um processo padrão do sistema
        $stmt = $pdo->prepare("SELECT nome FROM processos WHERE id = ?");
        $stmt->execute([$processo_id]);
        $processo = $stmt->fetch();
        
        if (!$processo) {
            jsonResponse(['error' => 'Processo não encontrado'], 404);
        }
        
        $processosEssenciais = ['corte', 'personalização', 'produção', 'expedição'];
        if (in_array(strtolower($processo['nome']), $processosEssenciais)) {
            // Verificar se há pedidos usando este processo
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total,
                       GROUP_CONCAT(DISTINCT codigo_pedido SEPARATOR ', ') as pedidos_afetados
                FROM pedidos 
                WHERE processo_atual = ?
            ");
            $stmt->execute([strtolower($processo['nome'])]);
            $resultPedidos = $stmt->fetch();
            
            if ($resultPedidos['total'] > 0) {
                jsonResponse([
                    'error' => 'Processo essencial está sendo usado em pedidos ativos',
                    'details' => "Pedidos afetados: {$resultPedidos['pedidos_afetados']}",
                    'count' => $resultPedidos['total']
                ], 400);
            }
        }
        
        // Se chegou até aqui, pode excluir
        $stmt = $pdo->prepare("DELETE FROM processos WHERE id = ?");
        $stmt->execute([$processo_id]);
        
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        logError("deleteProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao excluir processo'], 500);
    }
}

function getItemProcessos($pdo) {
    try {
        $item_id = $_GET['item_id'] ?? 0;
        
        if (!$item_id) {
            jsonResponse(['error' => 'ID do item é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT ip.*, p.nome as processo_nome, p.descricao as processo_descricao
            FROM item_processos ip
            JOIN processos p ON ip.processo_id = p.id
            WHERE ip.item_id = ?
            ORDER BY ip.ordem
        ");
        
        $stmt->execute([$item_id]);
        $processos = $stmt->fetchAll();
        jsonResponse($processos);
        
    } catch (Exception $e) {
        logError("getItemProcessos: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar processos do item'], 500);
    }
}

function addItemProcesso($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        $required = ['item_id', 'processo_id', 'ordem'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                jsonResponse(['error' => "Campo '$field' é obrigatório"], 400);
            }
        }
        
        // Verificar duplicação
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM item_processos 
            WHERE item_id = ? AND (processo_id = ? OR ordem = ?)
        ");
        $stmt->execute([$data['item_id'], $data['processo_id'], $data['ordem']]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            jsonResponse(['error' => 'Processo ou ordem já existe para este item'], 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO item_processos (item_id, processo_id, ordem, observacoes) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['item_id'],
            $data['processo_id'],
            $data['ordem'],
            $data['observacoes'] ?? ''
        ]);
        
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        logError("addItemProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao adicionar processo'], 500);
    }
}

function deleteItemProcesso($pdo) {
    try {
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            jsonResponse(['error' => 'ID é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM item_processos WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Processo não encontrado'], 404);
        }
        
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        logError("deleteItemProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao remover processo'], 500);
    }
}

function getPedidoItens($pdo) {
    try {
        $pedido_id = $_GET['pedido_id'] ?? 0;
        
        if (!$pedido_id) {
            jsonResponse(['error' => 'ID do pedido é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT pi.*, i.nome as item_nome, i.descricao as item_descricao
            FROM pedido_itens pi
            JOIN itens i ON pi.item_id = i.id
            WHERE pi.pedido_id = ?
            ORDER BY i.nome
        ");
        
        $stmt->execute([$pedido_id]);
        $itens = $stmt->fetchAll();
        jsonResponse($itens);
        
    } catch (Exception $e) {
        logError("getPedidoItens: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar itens do pedido'], 500);
    }
}

function updateProcessoPedido($pdo) {
    try {
        $pedido_id = $_GET['id'] ?? 0;
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        if (!$pedido_id) {
            jsonResponse(['error' => 'ID do pedido é obrigatório'], 400);
        }
        
        if (empty($data['processo_atual'])) {
            jsonResponse(['error' => 'Processo atual é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("UPDATE pedidos SET processo_atual = ? WHERE id = ?");
        $stmt->execute([$data['processo_atual'], $pedido_id]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Pedido não encontrado'], 404);
        }
        
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        logError("updateProcessoPedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao atualizar processo'], 500);
    }
}
?>