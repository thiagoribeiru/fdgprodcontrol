<?php
// api/pedidos.php - Módulo de Pedidos do Sistema de Controle de Produção

// Verificar se foi chamado corretamente
if (!defined('SISTEMA_VERSAO') || !isset($pdo)) {
    die('Acesso direto não permitido');
}

// Router das ações de pedidos
switch ($action) {
    case 'get_pedidos':
        getPedidos($pdo);
        break;
        
    case 'add_pedido':
        addPedido($pdo);
        break;
        
    case 'update_pedido':
        updatePedido($pdo);
        break;
        
    case 'delete_pedido':
        deletePedido($pdo);
        break;
        
    default:
        jsonResponse(['error' => 'Ação de pedido não encontrada: ' . $action], 404);
}

// === FUNÇÕES DO MÓDULO PEDIDOS ===

/**
 * Buscar todos os pedidos
 */
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

/**
 * Adicionar novo pedido
 */
function addPedido($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        $error = validateRequired($data, ['data_entrada', 'data_entrega', 'codigo_pedido', 'cliente', 'processo_atual']);
        if ($error) {
            jsonResponse(['error' => $error], 400);
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

/**
 * Atualizar pedido existente
 */
function updatePedido($pdo) {
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
        
        $error = validateRequired($data, ['data_entrada', 'data_entrega', 'codigo_pedido', 'cliente', 'processo_atual']);
        if ($error) {
            jsonResponse(['error' => $error], 400);
        }
        
        // Verificar se código já existe em outro pedido
        $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE codigo_pedido = ? AND id != ?");
        $stmt->execute([$data['codigo_pedido'], $pedido_id]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Código do pedido já existe'], 400);
        }
        
        $stmt = $pdo->prepare("
            UPDATE pedidos 
            SET data_entrada = ?, data_entrega = ?, codigo_pedido = ?, cliente = ?, processo_atual = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['data_entrada'],
            $data['data_entrega'],
            $data['codigo_pedido'],
            $data['cliente'],
            $data['processo_atual'],
            $pedido_id
        ]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Pedido não encontrado'], 404);
        }
        
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        logError("updatePedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao atualizar pedido'], 500);
    }
}

/**
 * Excluir pedido
 */
function deletePedido($pdo) {
    try {
        $pedido_id = $_GET['id'] ?? 0;
        
        if (!$pedido_id) {
            jsonResponse(['error' => 'ID do pedido é obrigatório'], 400);
        }
        
        $pdo->beginTransaction();
        
        // Remover processos dos itens (compatível com MySQL antigo)
        $stmt = $pdo->prepare("
            DELETE pip FROM pedido_item_processos pip
            INNER JOIN pedido_itens pi ON pip.pedido_item_id = pi.id
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedido_id]);
        
        // Remover itens do pedido
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

?>