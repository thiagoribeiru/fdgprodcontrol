<?php
// api.php - Router Principal do Sistema de Controle de Produção v5.2 (VERSÃO CORRIGIDA)

// === CONFIGURAÇÃO DE DEBUG ===
error_reporting(E_ALL);
ini_set('display_errors', 0); // Manter OFF em produção
ini_set('log_errors', 1);

// === FUNÇÃO str_starts_with PARA COMPATIBILIDADE ===
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) === 0;
    }
}

// === FUNÇÃO DE LOG DE ERROS ===
function logError($message) {
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'api_errors.log';
    $timestamp = date('[Y-m-d H:i:s] ');
    @file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// === FUNÇÃO DE RESPOSTA JSON SEGURA ===
function safeJsonResponse($data, $status_code = 200) {
    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Definir headers
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Retornar JSON
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // === INCLUIR CONFIGURAÇÕES ===
    if (!file_exists(__DIR__ . '/config.php')) {
        throw new Exception('Arquivo config.php não encontrado');
    }
    
    // Capturar erros do config.php
    ob_start();
    $configLoaded = include_once __DIR__ . '/config.php';
    $configOutput = ob_get_clean();
    
    if ($configLoaded === false) {
        throw new Exception('Erro ao carregar config.php');
    }
    
    // Verificar se as variáveis essenciais foram definidas
    if (!defined('SISTEMA_VERSAO')) {
        throw new Exception('SISTEMA_VERSAO não definida no config.php');
    }
    
    if (!isset($pdo)) {
        throw new Exception('Variável $pdo não definida no config.php');
    }
    
    if (!($pdo instanceof PDO)) {
        throw new Exception('$pdo não é uma instância PDO válida');
    }
    
    // Verificar se a função jsonResponse existe, se não criar uma
    if (!function_exists('jsonResponse')) {
        function jsonResponse($data, $status_code = 200) {
            safeJsonResponse($data, $status_code);
        }
    }

    // === CONFIGURAR HEADERS DE SEGURANÇA ===
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    // === RESPONDER A REQUISIÇÕES OPTIONS ===
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // === OBTER AÇÃO E MÉTODO ===
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';
    
    if (empty($action)) {
        safeJsonResponse(['error' => 'Parâmetro action é obrigatório'], 400);
    }

    // === ROUTER DAS AÇÕES POR MÓDULO ===
    $moduleLoaded = false;
    
    switch (true) {
        // === AÇÕES DE TESTE ===
        case $action === 'test':
            safeJsonResponse([
                'status' => 'API funcionando', 
                'timestamp' => date('Y-m-d H:i:s'), 
                'version' => SISTEMA_VERSAO,
                'architecture' => 'Modular',
                'php_version' => phpversion(),
                'str_starts_with' => function_exists('str_starts_with') ? 'native' : 'polyfill'
            ]);
            break;
            
        // === MÓDULO PEDIDOS ===
        case str_starts_with($action, 'pedido') || in_array($action, ['get_pedidos', 'add_pedido', 'update_pedido', 'delete_pedido']):
            $modulePath = __DIR__ . '/api/pedidos.php';
            if (!file_exists($modulePath)) {
                throw new Exception("Módulo pedidos não encontrado: $modulePath");
            }
            require_once $modulePath;
            $moduleLoaded = true;
            break;
            
        // === MÓDULO ITENS ===
        case str_starts_with($action, 'item') || in_array($action, ['get_itens', 'add_item', 'delete_item']):
            $modulePath = __DIR__ . '/api/itens.php';
            if (!file_exists($modulePath)) {
                throw new Exception("Módulo itens não encontrado: $modulePath");
            }
            require_once $modulePath;
            $moduleLoaded = true;
            break;
            
        // === MÓDULO PROCESSOS ===
        case str_starts_with($action, 'processo') || in_array($action, ['get_processos', 'add_processo', 'update_processo', 'delete_processo', 'get_processos_ordem', 'corrigir_ordem_processos']):
            $modulePath = __DIR__ . '/api/processos.php';
            if (!file_exists($modulePath)) {
                throw new Exception("Módulo processos não encontrado: $modulePath");
            }
            require_once $modulePath;
            $moduleLoaded = true;
            break;
            
        // === MÓDULO RECEITAS (ITEM_PROCESSOS) ===
        case in_array($action, ['get_item_processos', 'add_item_processo', 'delete_item_processo']):
            $modulePath = __DIR__ . '/api/receitas.php';
            if (!file_exists($modulePath)) {
                throw new Exception("Módulo receitas não encontrado: $modulePath");
            }
            require_once $modulePath;
            $moduleLoaded = true;
            break;
            
        // === MÓDULO ACOMPANHAMENTO ===
        case in_array($action, ['update_processo_status', 'get_acompanhamento_pedido', 'get_pedido_detalhado', 'add_item_to_pedido', 'remove_item_from_pedido', 'get_pedido_itens', 'update_processo_pedido']):
            $modulePath = __DIR__ . '/api/acompanhamento.php';
            if (!file_exists($modulePath)) {
                throw new Exception("Módulo acompanhamento não encontrado: $modulePath");
            }
            require_once $modulePath;
            $moduleLoaded = true;
            break;
            
        // === AÇÃO NÃO ENCONTRADA ===
        default:
            safeJsonResponse([
                'error' => 'Ação não encontrada: ' . $action, 
                'available_modules' => ['pedidos', 'itens', 'processos', 'receitas', 'acompanhamento'],
                'debug_info' => [
                    'action' => $action,
                    'method' => $method,
                    'available_actions' => [
                        'test',
                        'get_pedidos', 'add_pedido', 'update_pedido', 'delete_pedido',
                        'get_itens', 'add_item', 'delete_item',
                        'get_processos', 'add_processo', 'update_processo', 'delete_processo',
                        'get_item_processos', 'add_item_processo', 'delete_item_processo',
                        'get_pedido_detalhado', 'update_processo_status'
                    ]
                ]
            ], 404);
    }
    
    // Se chegou até aqui e nenhum módulo foi carregado, há um problema
    if (!$moduleLoaded && $action !== 'test') {
        safeJsonResponse([
            'error' => 'Módulo não foi carregado para a ação: ' . $action,
            'debug_info' => [
                'action' => $action,
                'method' => $method
            ]
        ], 500);
    }

} catch (Exception $e) {
    // Log do erro
    logError("Erro fatal no router: " . $e->getMessage() . " | Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
    
    // Resposta de erro segura
    safeJsonResponse([
        'error' => 'Erro interno do servidor',
        'debug_info' => [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'action' => $_GET['action'] ?? 'não definida',
            'system_version' => defined('SISTEMA_VERSAO') ? SISTEMA_VERSAO : 'não definida',
            'php_version' => phpversion()
        ]
    ], 500);
    
} catch (Error $e) {
    // Log do erro fatal
    logError("Erro fatal PHP no router: " . $e->getMessage() . " | Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
    
    // Resposta de erro fatal
    safeJsonResponse([
        'error' => 'Erro fatal do sistema',
        'debug_info' => [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'type' => 'Fatal Error'
        ]
    ], 500);
}

?>