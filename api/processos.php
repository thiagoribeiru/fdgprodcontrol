<?php
// api/processos.php - Módulo de Processos do Sistema de Controle de Produção

// Verificar se foi chamado corretamente
if (!defined('SISTEMA_VERSAO') || !isset($pdo)) {
    die('Acesso direto não permitido');
}

// Router das ações de processos
switch ($action) {
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
        
    case 'get_processos_ordem':
        getProcessosOrdem($pdo);
        break;
        
    case 'corrigir_ordem_processos':
        corrigirOrdemProcessos($pdo);
        break;
        
    default:
        jsonResponse(['error' => 'Ação de processo não encontrada: ' . $action], 404);
}

// === FUNÇÕES DO MÓDULO PROCESSOS ===

/**
 * Buscar todos os processos
 */
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

/**
 * Adicionar novo processo
 */
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
        
        $pdo->beginTransaction();
        
        // Se não informou ordem, pegar a próxima disponível
        if (empty($data['ordem'])) {
            $stmt = $pdo->query("SELECT COALESCE(MAX(ordem), 0) + 1 as proxima_ordem FROM processos");
            $result = $stmt->fetch();
            $data['ordem'] = $result['proxima_ordem'];
            $reorganizacaoFeita = false;
        } else {
            // Verificar se a ordem já existe
            $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM processos WHERE ordem = ?");
            $stmt->execute([$data['ordem']]);
            $result = $stmt->fetch();
            
            if ($result['existe'] > 0) {
                // Reorganizar: incrementar ordem dos processos >= ordem informada
                $stmt = $pdo->prepare("
                    UPDATE processos 
                    SET ordem = ordem + 1 
                    WHERE ordem >= ?
                    ORDER BY ordem DESC
                ");
                $stmt->execute([$data['ordem']]);
                $processosMovidos = $stmt->rowCount();
                $reorganizacaoFeita = true;
                
                logError("Reorganização automática: $processosMovidos processos movidos para inserir ordem {$data['ordem']}");
            } else {
                $reorganizacaoFeita = false;
            }
        }
        
        // Inserir o novo processo
        $stmt = $pdo->prepare("INSERT INTO processos (nome, descricao, ordem) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['nome'], 
            $data['descricao'] ?? '', 
            $data['ordem']
        ]);
        
        $processo_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        $response = [
            'success' => true, 
            'processo_id' => $processo_id,
            'ordem_final' => $data['ordem']
        ];
        
        if ($reorganizacaoFeita) {
            $response['reorganizacao'] = true;
            $response['processos_movidos'] = $processosMovidos ?? 0;
            $response['message'] = "Processo criado na ordem {$data['ordem']}. {$processosMovidos} processo(s) foram reorganizados automaticamente.";
        } else {
            $response['reorganizacao'] = false;
            $response['message'] = "Processo criado na ordem {$data['ordem']}.";
        }
        
        jsonResponse($response);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("addProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao salvar processo: ' . $e->getMessage()], 500);
    }
}

/**
 * Atualizar processo existente
 */
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
        
        $pdo->beginTransaction();
        
        // Buscar dados atuais do processo
        $stmt = $pdo->prepare("SELECT nome, ordem FROM processos WHERE id = ?");
        $stmt->execute([$processo_id]);
        $processoAtual = $stmt->fetch();
        
        if (!$processoAtual) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Processo não encontrado'], 404);
        }
        
        $ordemAtual = $processoAtual['ordem'];
        $novaOrdem = (int)$data['ordem'];
        $reorganizacaoFeita = false;
        $processosMovidos = 0;
        
        // Se a ordem mudou, fazer reorganização
        if ($ordemAtual != $novaOrdem) {
            // Verificar se a nova ordem já está ocupada por outro processo
            $stmt = $pdo->prepare("SELECT id, nome FROM processos WHERE ordem = ? AND id != ?");
            $stmt->execute([$novaOrdem, $processo_id]);
            $processoConflito = $stmt->fetch();
            
            if ($processoConflito) {
                if ($novaOrdem > $ordemAtual) {
                    // Movendo para baixo: decrementar ordem dos processos entre ordemAtual+1 e novaOrdem
                    $stmt = $pdo->prepare("
                        UPDATE processos 
                        SET ordem = ordem - 1 
                        WHERE ordem > ? AND ordem <= ? AND id != ?
                    ");
                    $stmt->execute([$ordemAtual, $novaOrdem, $processo_id]);
                } else {
                    // Movendo para cima: incrementar ordem dos processos entre novaOrdem e ordemAtual-1
                    $stmt = $pdo->prepare("
                        UPDATE processos 
                        SET ordem = ordem + 1 
                        WHERE ordem >= ? AND ordem < ? AND id != ?
                        ORDER BY ordem DESC
                    ");
                    $stmt->execute([$novaOrdem, $ordemAtual, $processo_id]);
                }
                
                $processosMovidos = $stmt->rowCount();
                $reorganizacaoFeita = true;
                
                logError("Reorganização na edição: $processosMovidos processos movidos. Processo '{$data['nome']}' movido da ordem $ordemAtual para $novaOrdem");
            }
        }
        
        // Atualizar o processo
        $stmt = $pdo->prepare("
            UPDATE processos 
            SET nome = ?, descricao = ?, ordem = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['nome'],
            $data['descricao'] ?? '',
            $novaOrdem,
            $processo_id
        ]);
        
        $pdo->commit();
        
        $response = [
            'success' => true,
            'ordem_anterior' => $ordemAtual,
            'ordem_final' => $novaOrdem
        ];
        
        if ($reorganizacaoFeita) {
            $response['reorganizacao'] = true;
            $response['processos_movidos'] = $processosMovidos;
            if ($novaOrdem > $ordemAtual) {
                $response['message'] = "Processo atualizado. Movido da ordem $ordemAtual para $novaOrdem. $processosMovidos processo(s) foram reorganizados automaticamente.";
            } else {
                $response['message'] = "Processo atualizado. Movido da ordem $ordemAtual para $novaOrdem. $processosMovidos processo(s) foram reorganizados automaticamente.";
            }
        } else {
            $response['reorganizacao'] = false;
            $response['message'] = "Processo atualizado com sucesso.";
        }
        
        jsonResponse($response);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("updateProcesso: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao atualizar processo: ' . $e->getMessage()], 500);
    }
}

/**
 * Excluir processo
 */
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
            INNER JOIN itens i ON ip.item_id = i.id
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

/**
 * Buscar processos com informações de ordem
 */
function getProcessosOrdem($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                id,
                nome,
                ordem,
                descricao,
                COUNT(ip.id) as total_usos,
                CASE 
                    WHEN nome IN ('corte', 'personalização', 'produção', 'expedição') THEN 'sistema'
                    ELSE 'personalizado'
                END as tipo
            FROM processos p
            LEFT JOIN item_processos ip ON p.id = ip.processo_id
            GROUP BY p.id
            ORDER BY p.ordem ASC, p.nome ASC
        ");
        
        $processos = $stmt->fetchAll();
        
        // Verificar se há problemas na numeração
        $ordens = array_column($processos, 'ordem');
        $duplicadas = array_count_values($ordens);
        $problemas = array_filter($duplicadas, function($count) { return $count > 1; });
        
        $response = [
            'processos' => $processos,
            'total' => count($processos),
            'ordens_duplicadas' => !empty($problemas) ? array_keys($problemas) : [],
            'sequencia_ok' => empty($problemas)
        ];
        
        jsonResponse($response);
        
    } catch (Exception $e) {
        logError("getProcessosOrdem: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao buscar ordem dos processos'], 500);
    }
}

/**
 * Corrigir ordem dos processos
 */
function corrigirOrdemProcessos($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Buscar todos os processos ordenados por ordem atual, depois por nome
        $stmt = $pdo->query("SELECT id, nome, ordem FROM processos ORDER BY ordem ASC, nome ASC");
        $processos = $stmt->fetchAll();
        
        $novaOrdem = 1;
        $processosCorrigidos = 0;
        
        foreach ($processos as $processo) {
            if ($processo['ordem'] != $novaOrdem) {
                $stmt = $pdo->prepare("UPDATE processos SET ordem = ? WHERE id = ?");
                $stmt->execute([$novaOrdem, $processo['id']]);
                $processosCorrigidos++;
                logError("Correção de ordem: '{$processo['nome']}' mudou da ordem {$processo['ordem']} para $novaOrdem");
            }
            $novaOrdem++;
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'processos_corrigidos' => $processosCorrigidos,
            'nova_sequencia_final' => $novaOrdem - 1,
            'message' => $processosCorrigidos > 0 ? 
                "Numeração corrigida! $processosCorrigidos processo(s) tiveram sua ordem ajustada." :
                "Numeração já estava correta. Nenhum ajuste necessário."
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("corrigirOrdemProcessos: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao corrigir ordem dos processos'], 500);
    }
}

?>