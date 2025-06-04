<?php
// api.php - API Refatorada para Sistema de Controle de Produção

// Incluir configurações centralizadas
require_once 'config.php';

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

// Função para log de erros
function logError($message) {
    $logFile = __DIR__ . '/api_errors.log';
    $timestamp = date('[Y-m-d H:i:s] ');
    file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Try-catch global
try {
    // Usar conexão do config.php (variável $pdo já está disponível)
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Conexão com banco de dados não está disponível');
    }

    // Obter ação
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    // Router das ações
    switch ($action) {
        case 'test':
            jsonResponse(['status' => 'API funcionando', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
            
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
            
        case 'get_processos_ordem':
            getProcessosOrdem($pdo);
            break;
            
        case 'corrigir_ordem_processos':
            corrigirOrdemProcessos($pdo);
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
            
        case 'get_pedido_detalhado':
            getPedidoDetalhado($pdo);
            break;
            
        case 'update_processo_status':
            updateProcessoStatus($pdo);
            break;
            
        case 'get_acompanhamento_pedido':
            getAcompanhamentoPedido($pdo);
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
            jsonResponse(['error' => 'Ação não encontrada: ' . $action], 404);
    }

} catch (Exception $e) {
    logError("Erro fatal: " . $e->getMessage());
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
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
        
        // Inserir sem ordem específica
        $stmt = $pdo->prepare("
            INSERT INTO item_processos (item_id, processo_id, observacoes) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['item_id'],
            $data['processo_id'],
            $data['observacoes'] ?? ''
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Processo adicionado com sucesso! A ordem será definida pela sequência global.']);
        
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
        
        $pdo->commit();
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("addItemToPedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao adicionar item ao pedido'], 500);
    }
}

function removeItemFromPedido($pdo) {
    try {
        $pedido_item_id = $_GET['id'] ?? 0;
        
        if (!$pedido_item_id) {
            jsonResponse(['error' => 'ID do item do pedido é obrigatório'], 400);
        }
        
        $pdo->beginTransaction();
        
        // Remover processos do item
        $stmt = $pdo->prepare("DELETE FROM pedido_item_processos WHERE pedido_item_id = ?");
        $stmt->execute([$pedido_item_id]);
        
        // Remover item do pedido
        $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE id = ?");
        $stmt->execute([$pedido_item_id]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Item do pedido não encontrado'], 404);
        }
        
        $pdo->commit();
        jsonResponse(['success' => true]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("removeItemFromPedido: " . $e->getMessage());
        jsonResponse(['error' => 'Erro ao remover item do pedido'], 500);
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
        
        $error = validateRequired($data, ['processo_atual']);
        if ($error) {
            jsonResponse(['error' => $error], 400);
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