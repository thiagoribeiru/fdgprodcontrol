<?php
// api/acompanhamento.php - Módulo de Acompanhamento do Sistema de Controle de Produção

// Verificar se foi chamado corretamente
if (!defined('SISTEMA_VERSAO') || !isset($pdo)) {
    die('Acesso direto não permitido');
}

// Router das ações de acompanhamento
switch ($action) {
    case 'get_pedido_detalhado':
        getPedidoDetalhado($pdo);
        break;
        
    case 'get_pedido_itens':
        getPedidoItens($pdo);
        break;
        
    case 'get_acompanhamento_pedido':
        getAcompanhamentoPedido($pdo);
        break;
        
    case 'update_processo_status':
        updateProcessoStatus($pdo);
        break;
        
    case 'add_item_to_pedido':
        addItemToPedido($pdo);
        break;
        
    case 'remove_item_from_pedido':
        removeItemFromPedido($pdo);
        break;
        
    case 'update_processo_pedido':
        updateProcessoPedido($pdo);
        break;
        
    default:
        jsonResponse(['error' => 'Ação de acompanhamento não encontrada: ' . $action], 404);
}

// === FUNÇÕES DO MÓDULO ACOMPANHAMENTO ===

/**
 * Buscar detalhes completos de um pedido
 */
function getPedidoDetalhado($pdo) {
    try {
        $pedido_id = $_GET['pedido_id'] ?? 0;
        
        if (!$pedido_id) {
            jsonResponse(['error' => 'ID do pedido é obrigatório'], 400);
        }
        
        // Buscar dados do pedido
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt->execute([$pedido_id]);
        $pedido = $stmt->fetch();
        
        if (!$pedido) {
            jsonResponse(['error' => 'Pedido não encontrado'], 404);
        }
        
        // Buscar processos ordenados APENAS pela ordem global
        $stmt = $pdo->prepare("
            SELECT 
                pi.id as pedido_item_id,
                i.nome as item_nome,
                pi.quantidade,
                proc.id as processo_id,
                proc.nome as processo_nome,
                proc.ordem as processo_ordem_global,
                COALESCE(pip.status, 'aguardando') as status,
                pip.data_inicio,
                pip.data_conclusao,
                pip.observacoes,
                pip.usuario_responsavel,
                CONCAT(i.nome, ' - ', proc.nome) as processo_completo
            FROM pedido_itens pi
            INNER JOIN itens i ON pi.item_id = i.id
            INNER JOIN item_processos ip ON i.id = ip.item_id
            INNER JOIN processos proc ON ip.processo_id = proc.id
            LEFT JOIN pedido_item_processos pip ON pi.id = pip.pedido_item_id AND proc.id = pip.processo_id
            WHERE pi.pedido_id = ?
            ORDER BY proc.ordem ASC, i.nome ASC
        ");
        
        $stmt->execute([$pedido_id]);
        $todos_processos = $stmt->fetchAll();
        
        $total_processos = count($todos_processos);
        $peso_completos = 0;
        
        foreach ($todos_processos as $processo) {
            if ($processo['status'] === 'completo') {
                $peso_completos += 1;
            } elseif ($processo['status'] === 'em_andamento') {
                $peso_completos += 0.5;
            }
        }
        
        $progresso_geral = $total_processos > 0 ? ($peso_completos / $total_processos) * 100 : 0;
        $pedido['progresso_geral'] = round($progresso_geral, 2);
        
        jsonResponse([
            'pedido' => $pedido,
            'processos' => $todos_processos,
            'total_processos' => $total_processos,
            'processos_completos' => $peso_completos
        ]);
        
    } catch (Exception $e) {
        logError("getPedidoDetalhado: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar detalhes do pedido'], 500);
    }
}

/**
 * Buscar itens de um pedido
 */
function getPedidoItens($pdo) {
    try {
        $pedido_id = $_GET['pedido_id'] ?? 0;
        
        if (!$pedido_id) {
            jsonResponse(['error' => 'ID do pedido é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT pi.*, i.nome as item_nome, i.descricao as item_descricao
            FROM pedido_itens pi
            INNER JOIN itens i ON pi.item_id = i.id
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

/**
 * Buscar dados de acompanhamento de um pedido
 */
function getAcompanhamentoPedido($pdo) {
    try {
        $pedido_id = $_GET['pedido_id'] ?? 0;
        
        if (!$pedido_id) {
            jsonResponse(['error' => 'ID do pedido é obrigatório'], 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                p.id as pedido_id,
                p.codigo_pedido,
                p.cliente,
                p.data_entrada,
                p.data_entrega,
                pi.id as pedido_item_id,
                i.nome as item_nome,
                pi.quantidade,
                proc.id as processo_id,
                proc.nome as processo_nome,
                proc.ordem as processo_ordem,
                COALESCE(pip.status, 'aguardando') as processo_status,
                pip.data_inicio,
                pip.data_conclusao,
                pip.observacoes as processo_observacoes,
                pip.usuario_responsavel
            FROM pedidos p
            INNER JOIN pedido_itens pi ON p.id = pi.pedido_id
            INNER JOIN itens i ON pi.item_id = i.id
            INNER JOIN item_processos ip ON i.id = ip.item_id
            INNER JOIN processos proc ON ip.processo_id = proc.id
            LEFT JOIN pedido_item_processos pip ON pi.id = pip.pedido_item_id AND proc.id = pip.processo_id
            WHERE p.id = ?
            ORDER BY proc.ordem, i.nome
        ");
        $stmt->execute([$pedido_id]);
        $dados = $stmt->fetchAll();
        
        jsonResponse($dados);
        
    } catch (Exception $e) {
        logError("getAcompanhamentoPedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar acompanhamento'], 500);
    }
}

/**
 * Atualizar status de um processo
 */
function updateProcessoStatus($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        $error = validateRequired($data, ['pedido_item_id', 'processo_id', 'status']);
        if ($error) {
            jsonResponse(['error' => $error], 400);
        }
        
        $validStatus = ['aguardando', 'em_andamento', 'completo'];
        if (!in_array($data['status'], $validStatus)) {
            jsonResponse(['error' => 'Status inválido'], 400);
        }
        
        // Definir datas baseadas no status
        $dataInicio = null;
        $dataConclusao = null;
        
        if ($data['status'] === 'em_andamento') {
            $dataInicio = date('Y-m-d H:i:s');
        } elseif ($data['status'] === 'completo') {
            $dataConclusao = date('Y-m-d H:i:s');
            // Se não tinha data de início, definir agora
            $stmt = $pdo->prepare("SELECT data_inicio FROM pedido_item_processos WHERE pedido_item_id = ? AND processo_id = ?");
            $stmt->execute([$data['pedido_item_id'], $data['processo_id']]);
            $processo = $stmt->fetch();
            if (!$processo || !$processo['data_inicio']) {
                $dataInicio = date('Y-m-d H:i:s');
            }
        }
        
        // Verificar se já existe registro
        $stmt = $pdo->prepare("SELECT id FROM pedido_item_processos WHERE pedido_item_id = ? AND processo_id = ?");
        $stmt->execute([$data['pedido_item_id'], $data['processo_id']]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            // Atualizar registro existente
            $sql = "UPDATE pedido_item_processos SET status = ?, observacoes = ?, usuario_responsavel = ?";
            $params = [$data['status'], $data['observacoes'] ?? '', $data['usuario_responsavel'] ?? ''];
            
            if ($dataInicio) {
                $sql .= ", data_inicio = ?";
                $params[] = $dataInicio;
            }
            if ($dataConclusao) {
                $sql .= ", data_conclusao = ?";
                $params[] = $dataConclusao;
            }
            
            $sql .= " WHERE pedido_item_id = ? AND processo_id = ?";
            $params[] = $data['pedido_item_id'];
            $params[] = $data['processo_id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Criar novo registro
            $stmt = $pdo->prepare("
                INSERT INTO pedido_item_processos (pedido_item_id, processo_id, status, data_inicio, data_conclusao, observacoes, usuario_responsavel)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['pedido_item_id'],
                $data['processo_id'],
                $data['status'],
                $dataInicio,
                $dataConclusao,
                $data['observacoes'] ?? '',
                $data['usuario_responsavel'] ?? ''
            ]);
        }
        
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        logError("updateProcessoStatus: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao atualizar status do processo'], 500);
    }
}

/**
 * Adicionar item a pedido existente
 */
function addItemToPedido($pdo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'JSON inválido'], 400);
        }
        
        $error = validateRequired($data, ['pedido_id', 'item_id', 'quantidade']);
        if ($error) {
            jsonResponse(['error' => $error], 400);
        }
        
        // Verificar se o item já foi adicionado ao pedido
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM pedido_itens 
            WHERE pedido_id = ? AND item_id = ?
        ");
        $stmt->execute([$data['pedido_id'], $data['item_id']]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            jsonResponse(['error' => 'Este item já foi adicionado ao pedido'], 400);
        }
        
        // Verificar se pedido e item existem
        $stmt = $pdo->prepare("SELECT codigo_pedido FROM pedidos WHERE id = ?");
        $stmt->execute([$data['pedido_id']]);
        $pedido = $stmt->fetch();
        
        if (!$pedido) {
            jsonResponse(['error' => 'Pedido não encontrado'], 404);
        }
        
        $stmt = $pdo->prepare("SELECT nome FROM itens WHERE id = ?");
        $stmt->execute([$data['item_id']]);
        $item = $stmt->fetch();
        
        if (!$item) {
            jsonResponse(['error' => 'Item não encontrado'], 404);
        }
        
        $pdo->beginTransaction();
        
        // Inserir item no pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedido_itens (pedido_id, item_id, quantidade, observacoes) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['pedido_id'],
            $data['item_id'],
            $data['quantidade'],
            $data['observacoes'] ?? ''
        ]);
        
        $pedido_item_id = $pdo->lastInsertId();
        
        // Criar processos para este item (inicialmente com status 'aguardando')
        $stmt = $pdo->prepare("
            INSERT INTO pedido_item_processos (pedido_item_id, processo_id, status)
            SELECT ?, ip.processo_id, 'aguardando'
            FROM item_processos ip
            WHERE ip.item_id = ?
        ");
        $stmt->execute([$pedido_item_id, $data['item_id']]);
        $processosInseridos = $stmt->rowCount();
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => "Item '{$item['nome']}' adicionado ao pedido '{$pedido['codigo_pedido']}' com sucesso. $processosInseridos processo(s) configurados."
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("addItemToPedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao adicionar item ao pedido'], 500);
    }
}

/**
 * Remover item de pedido
 */
function removeItemFromPedido($pdo) {
    try {
        $pedido_item_id = $_GET['id'] ?? 0;
        
        if (!$pedido_item_id) {
            jsonResponse(['error' => 'ID do item do pedido é obrigatório'], 400);
        }
        
        // Buscar informações antes de excluir para log
        $stmt = $pdo->prepare("
            SELECT pi.id, i.nome as item_nome, p.codigo_pedido
            FROM pedido_itens pi
            INNER JOIN itens i ON pi.item_id = i.id
            INNER JOIN pedidos p ON pi.pedido_id = p.id
            WHERE pi.id = ?
        ");
        $stmt->execute([$pedido_item_id]);
        $info = $stmt->fetch();
        
        if (!$info) {
            jsonResponse(['error' => 'Item do pedido não encontrado'], 404);
        }
        
        $pdo->beginTransaction();
        
        // Remover processos do item
        $stmt = $pdo->prepare("DELETE FROM pedido_item_processos WHERE pedido_item_id = ?");
        $stmt->execute([$pedido_item_id]);
        $processosRemovidos = $stmt->rowCount();
        
        // Remover item do pedido
        $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE id = ?");
        $stmt->execute([$pedido_item_id]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Item do pedido não encontrado'], 404);
        }
        
        $pdo->commit();
        
        logError("Item removido de pedido: '{$info['item_nome']}' removido do pedido '{$info['codigo_pedido']}' - $processosRemovidos processos removidos");
        
        jsonResponse([
            'success' => true,
            'message' => "Item '{$info['item_nome']}' removido do pedido '{$info['codigo_pedido']}' com sucesso"
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("removeItemFromPedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao remover item do pedido'], 500);
    }
}

/**
 * Atualizar processo atual do pedido
 */
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
        
        $error = validateRequired($data, ['processo_atual']);
        if ($error) {
            jsonResponse(['error' => $error], 400);
        }
        
        // Validar se o processo existe
        $stmt = $pdo->prepare("SELECT nome FROM processos WHERE nome = ?");
        $stmt->execute([$data['processo_atual']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Processo não encontrado'], 404);
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