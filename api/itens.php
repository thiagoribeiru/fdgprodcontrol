<?php
// api/itens.php - Módulo de Itens do Sistema de Controle de Produção

// Verificar se foi chamado corretamente
if (!defined('SISTEMA_VERSAO') || !isset($pdo)) {
    die('Acesso direto não permitido');
}

// Router das ações de itens
switch ($action) {
    case 'get_itens':
        getItens($pdo);
        break;
        
    case 'add_item':
        addItem($pdo);
        break;
        
    case 'delete_item':
        deleteItem($pdo);
        break;
        
    default:
        jsonResponse(['error' => 'Ação de item não encontrada: ' . $action], 404);
}

// === FUNÇÕES DO MÓDULO ITENS ===

/**
 * Buscar todos os itens
 */
function getItens($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT i.*, COUNT(ip.id) as total_processos
            FROM itens i
            LEFT JOIN item_processos ip ON i.id = ip.item_id
            GROUP BY i.id
            ORDER BY i.nome
        ");
        $itens = $stmt->fetchAll();
        jsonResponse($itens);
    } catch (Exception $e) {
        logError("getItens: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar itens'], 500);
    }
}

/**
 * Adicionar novo item
 */
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
        
        // Verificar se já existe item com mesmo nome
        $stmt = $pdo->prepare("SELECT id FROM itens WHERE nome = ?");
        $stmt->execute([$data['nome']]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Já existe um item com este nome'], 400);
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

/**
 * Excluir item
 */
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
            INNER JOIN pedidos p ON pi.pedido_id = p.id
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
        
        // Buscar nome do item
        $stmt = $pdo->prepare("SELECT nome FROM itens WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Item não encontrado'], 404);
        }
        
        // Remover processos do item
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

?>