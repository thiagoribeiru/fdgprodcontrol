<?php
// api/receitas.php - Módulo de Receitas (Item-Processos) do Sistema de Controle de Produção

// Verificar se foi chamado corretamente
if (!defined('SISTEMA_VERSAO') || !isset($pdo)) {
    die('Acesso direto não permitido');
}

// Router das ações de receitas
switch ($action) {
    case 'get_item_processos':
        getItemProcessos($pdo);
        break;
        
    case 'add_item_processo':
        addItemProcesso($pdo);
        break;
        
    case 'delete_item_processo':
        deleteItemProcesso($pdo);
        break;
        
    default:
        jsonResponse(['error' => 'Ação de receita não encontrada: ' . $action], 404);
}

// === FUNÇÕES DO MÓDULO RECEITAS ===

/**
 * Buscar processos de um item específico
 */
function getItemProcessos($pdo) {
    try {
        $item_id = $_GET['item_id'] ?? 0;
        
        if (!$item_id) {
            jsonResponse(['error' => 'ID do item é obrigatório'], 400);
        }
        
        // Ordenar pela ordem global dos processos
        $stmt = $pdo->prepare("
            SELECT 
                ip.id,
                ip.item_id,
                ip.processo_id,
                ip.observacoes,
                p.nome as processo_nome, 
                p.descricao as processo_descricao,
                p.ordem as ordem_global
            FROM item_processos ip
            INNER JOIN processos p ON ip.processo_id = p.id
            WHERE ip.item_id = ?
            ORDER BY p.ordem ASC, p.nome ASC
        ");
        
        $stmt->execute([$item_id]);
        $processos = $stmt->fetchAll();
        
        jsonResponse($processos);
        
    } catch (Exception $e) {
        logError("getItemProcessos: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar processos do item'], 500);
    }
}

/**
 * Adicionar processo a um item (receita)
 */
function addItemProcesso($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        $error = validateRequired($data, ['item_id', 'processo_id']);
        if ($error) {
            jsonResponse(['error' => $error], 400);
        }
        
        // Verificar se já existe este processo para este item
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM item_processos 
            WHERE item_id = ? AND processo_id = ?
        ");
        $stmt->execute([$data['item_id'], $data['processo_id']]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            jsonResponse(['error' => 'Este processo já foi adicionado ao item'], 400);
        }
        
        // Verificar se o item existe
        $stmt = $pdo->prepare("SELECT nome FROM itens WHERE id = ?");
        $stmt->execute([$data['item_id']]);
        $item = $stmt->fetch();
        
        if (!$item) {
            jsonResponse(['error' => 'Item não encontrado'], 404);
        }
        
        // Verificar se o processo existe
        $stmt = $pdo->prepare("SELECT nome FROM processos WHERE id = ?");
        $stmt->execute([$data['processo_id']]);
        $processo = $stmt->fetch();
        
        if (!$processo) {
            jsonResponse(['error' => 'Processo não encontrado'], 404);
        }
        
        // Inserir sem ordem específica (ordem será pela sequência global)
        $stmt = $pdo->prepare("
            INSERT INTO item_processos (item_id, processo_id, observacoes) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['item_id'],
            $data['processo_id'],
            $data['observacoes'] ?? ''
        ]);
        
        jsonResponse([
            'success' => true, 
            'message' => "Processo '{$processo['nome']}' adicionado ao item '{$item['nome']}' com sucesso! A ordem será definida pela sequência global."
        ]);
        
    } catch (Exception $e) {
        logError("addItemProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao adicionar processo: ' . $e->getMessage()], 500);
    }
}

/**
 * Remover processo de um item
 */
function deleteItemProcesso($pdo) {
    try {
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            jsonResponse(['error' => 'ID é obrigatório'], 400);
        }
        
        // Buscar informações antes de excluir para log
        $stmt = $pdo->prepare("
            SELECT ip.id, i.nome as item_nome, p.nome as processo_nome
            FROM item_processos ip
            INNER JOIN itens i ON ip.item_id = i.id
            INNER JOIN processos p ON ip.processo_id = p.id
            WHERE ip.id = ?
        ");
        $stmt->execute([$id]);
        $info = $stmt->fetch();
        
        if (!$info) {
            jsonResponse(['error' => 'Processo do item não encontrado'], 404);
        }
        
        // Verificar se há pedidos ativos usando este processo para este item
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM pedido_item_processos pip
            INNER JOIN pedido_itens pi ON pip.pedido_item_id = pi.id
            INNER JOIN item_processos ip ON pi.item_id = ip.item_id AND pip.processo_id = ip.processo_id
            WHERE ip.id = ? AND pip.status IN ('em_andamento', 'completo')
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            jsonResponse([
                'error' => 'Não é possível remover este processo',
                'details' => "Existem {$result['total']} processo(s) em andamento ou completos em pedidos ativos"
            ], 400);
        }
        
        // Remover o processo
        $stmt = $pdo->prepare("DELETE FROM item_processos WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Processo não encontrado'], 404);
        }
        
        logError("Processo removido de item: '{$info['processo_nome']}' removido do item '{$info['item_nome']}'");
        
        jsonResponse([
            'success' => true,
            'message' => "Processo '{$info['processo_nome']}' removido do item '{$info['item_nome']}' com sucesso"
        ]);
        
    } catch (Exception $e) {
        logError("deleteItemProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao remover processo'], 500);
    }
}

?>