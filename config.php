<?php
// config.php - Configurações Centralizadas do Sistema de Controle de Produção

// Versão do sistema
define('SISTEMA_VERSAO', '5.2');
define('SISTEMA_NOME', 'Sistema de Controle de Produção');

// === CONFIGURAÇÕES DO BANCO DE DADOS ===

$config_database = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'controle_producao',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

// === CONFIGURAÇÕES DE LOG ===

define('LOG_ERRORS', true);
define('LOG_PATH', __DIR__ . '/logs/');
define('LOG_FILE_API', LOG_PATH . 'api_errors.log');
define('LOG_FILE_SISTEMA', LOG_PATH . 'sistema.log');

// === CONFIGURAÇÕES DE SEGURANÇA ===

define('SESSION_TIMEOUT', 3600); // 1 hora em segundos
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos em segundos

// === CONFIGURAÇÕES DA APLICAÇÃO ===

define('TIMEZONE', 'America/Sao_Paulo');
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i:s');
define('CURRENCY_SYMBOL', 'R$');

// === CONFIGURAÇÕES DE PAGINAÇÃO ===

define('ITEMS_PER_PAGE', 50);
define('MAX_ITEMS_PER_PAGE', 200);

// === CONFIGURAÇÕES DE UPLOAD ===

define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx');
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// === CONFIGURAÇÃO DE TIMEZONE ===

date_default_timezone_set(TIMEZONE);

// === CRIAÇÃO DE DIRETÓRIOS ===

// Criar diretório de logs se não existir
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Criar diretório de uploads se não existir
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Criar diretório da API se não existir
if (!is_dir(__DIR__ . '/api/')) {
    mkdir(__DIR__ . '/api/', 0755, true);
}

// === CONEXÃO COM BANCO DE DADOS ===

$pdo = null;

try {
    // Construir DSN para compatibilidade com MySQL antigo
    $dsn = "mysql:host={$config_database['host']}";
    
    if (!empty($config_database['port'])) {
        $dsn .= ";port={$config_database['port']}";
    }
    
    $dsn .= ";dbname={$config_database['dbname']};charset={$config_database['charset']}";
    
    // Criar conexão PDO
    $pdo = new PDO(
        $dsn, 
        $config_database['username'], 
        $config_database['password'],
        $config_database['options']
    );
    
    // Log de conexão bem-sucedida
    logMessage("Conexão com banco de dados estabelecida com sucesso", 'INFO');
    
} catch(PDOException $e) {
    $error_message = "Erro na conexão com o banco de dados: " . $e->getMessage();
    
    // Log do erro
    logMessage($error_message, 'ERROR');
    
    // Mostrar erro
    die($error_message);
}

// === FUNÇÕES AUXILIARES ===

/**
 * Função para retornar dados em JSON
 * Centraliza a resposta JSON com headers corretos
 */
function jsonResponse($data, $status_code = 200) {
    // Definir código de status HTTP
    http_response_code($status_code);
    
    // Headers para JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Codificar e retornar JSON
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Função para logging centralizado
 */
function logMessage($message, $level = 'INFO', $context = []) {
    if (!LOG_ERRORS) return;
    
    $timestamp = date('[Y-m-d H:i:s]');
    $log_entry = "{$timestamp} [{$level}] {$message}";
    
    // Adicionar contexto se fornecido
    if (!empty($context)) {
        $log_entry .= ' Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    
    $log_entry .= PHP_EOL;
    
    // Determinar arquivo de log baseado no nível
    $log_file = ($level === 'ERROR' || $level === 'CRITICAL') ? LOG_FILE_API : LOG_FILE_SISTEMA;
    
    // Escrever no arquivo de log
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Função para validar campos obrigatórios
 */
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            return "O campo '$field' é obrigatório.";
        }
    }
    return null;
}

/**
 * Função para validar múltiplos campos e retornar todos os erros
 */
function validateRequiredMultiple($data, $fields) {
    $errors = [];
    
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = "O campo '$field' é obrigatório.";
        }
    }
    
    return $errors;
}

/**
 * Função para sanitizar dados de entrada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
}

/**
 * Função para validar ID numérico
 */
function validateId($id, $field_name = 'ID') {
    if (!$id || !is_numeric($id) || $id <= 0) {
        return "{$field_name} inválido.";
    }
    return null;
}

/**
 * Função para formatar data para o padrão brasileiro
 */
function formatDateBR($date) {
    if (!$date) return '';
    
    try {
        $dt = new DateTime($date);
        return $dt->format(DATE_FORMAT);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Função para formatar data e hora para o padrão brasileiro
 */
function formatDateTimeBR($datetime) {
    if (!$datetime) return '';
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format(DATETIME_FORMAT);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Função para validar data no formato brasileiro
 */
function validateDateBR($date) {
    if (!$date) return false;
    
    $date_parts = explode('/', $date);
    if (count($date_parts) !== 3) return false;
    
    return checkdate($date_parts[1], $date_parts[0], $date_parts[2]);
}

/**
 * Função para converter data brasileira para formato MySQL
 */
function convertDateToMySQL($date_br) {
    if (!$date_br) return null;
    
    try {
        $dt = DateTime::createFromFormat('d/m/Y', $date_br);
        return $dt ? $dt->format('Y-m-d') : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Função para obter configuração do banco de dados
 */
function getDatabaseConfig() {
    global $config_database;
    return $config_database;
}

/**
 * Função para verificar se o sistema está em modo de manutenção
 */
function isMaintenanceMode() {
    return file_exists(__DIR__ . '/.maintenance');
}

/**
 * Função para obter informações do sistema
 */
function getSystemInfo() {
    global $pdo;
    
    $info = [
        'nome' => SISTEMA_NOME,
        'versao' => SISTEMA_VERSAO,
        'arquitetura' => 'API Modularizada',
        'modulos' => ['pedidos', 'itens', 'processos', 'receitas', 'acompanhamento'],
        'php_version' => phpversion(),
        'timezone' => TIMEZONE,
        'database_connected' => $pdo ? true : false,
        'maintenance_mode' => isMaintenanceMode()
    ];
    
    return $info;
}

/**
 * Função para verificar compatibilidade do MySQL
 */
function checkMySQLCompatibility() {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        $version = $result['version'];
        
        // Extrair número da versão (ex: 5.7.30 de "5.7.30-log")
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);
        
        if (count($matches) >= 4) {
            $major = (int)$matches[1];
            
            // Verificar se é MySQL 5.0 ou superior
            if ($major >= 5) {
                logMessage("MySQL versão {$version} - Compatível", 'INFO');
                return true;
            } else {
                logMessage("MySQL versão {$version} - Incompatível (necessário 5.0+)", 'WARNING');
                return false;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        logMessage("Erro ao verificar versão do MySQL: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Função para executar query com tratamento de erro para MySQL antigo
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Erro na query: " . $errorInfo[2]);
        }
        
        return $stmt;
        
    } catch (PDOException $e) {
        logMessage("Erro PDO: " . $e->getMessage(), 'ERROR', ['sql' => $sql, 'params' => $params]);
        throw $e;
    } catch (Exception $e) {
        logMessage("Erro geral na query: " . $e->getMessage(), 'ERROR', ['sql' => $sql, 'params' => $params]);
        throw $e;
    }
}

/**
 * Função para inicializar o sistema
 */
function initializeSystem() {
    // Verificar se está em modo de manutenção
    if (isMaintenanceMode()) {
        jsonResponse([
            'error' => 'Sistema em manutenção',
            'message' => 'O sistema está temporariamente indisponível para manutenção. Tente novamente em alguns minutos.'
        ], 503);
    }
    
    // Verificar conexão com banco
    global $pdo;
    if (!$pdo) {
        jsonResponse([
            'error' => 'Erro de sistema',
            'message' => 'Não foi possível conectar ao banco de dados.'
        ], 500);
    }
    
    // Verificar compatibilidade do MySQL
    if (!checkMySQLCompatibility()) {
        logMessage("Sistema iniciado com MySQL incompatível", 'WARNING');
    }
    
    logMessage("Sistema inicializado com sucesso - API Modularizada", 'INFO');
}

/**
 * Função para limpar logs antigos
 * Mantém apenas logs dos últimos 30 dias
 */
function cleanOldLogs() {
    $log_files = [LOG_FILE_API, LOG_FILE_SISTEMA];
    $retention_days = 30;
    
    foreach ($log_files as $log_file) {
        if (file_exists($log_file)) {
            $file_time = filemtime($log_file);
            $days_old = (time() - $file_time) / (24 * 60 * 60);
            
            if ($days_old > $retention_days) {
                // Criar backup antes de limpar
                $backup_file = $log_file . '.backup.' . date('Y-m-d');
                if (!file_exists($backup_file)) {
                    copy($log_file, $backup_file);
                }
                
                // Limpar arquivo de log
                file_put_contents($log_file, '');
                logMessage("Log limpo automaticamente - arquivo com {$days_old} dias", 'INFO');
            }
        }
    }
}

/**
 * Função para obter estatísticas do sistema
 */
function getSystemStats() {
    global $pdo;
    
    if (!$pdo) return null;
    
    try {
        $stats = [];
        
        // Estatísticas básicas
        $queries = [
            'total_pedidos' => "SELECT COUNT(*) as total FROM pedidos",
            'total_itens' => "SELECT COUNT(*) as total FROM itens",
            'total_processos' => "SELECT COUNT(*) as total FROM processos",
            'pedidos_hoje' => "SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_entrada) = CURDATE()",
            'pedidos_mes' => "SELECT COUNT(*) as total FROM pedidos WHERE YEAR(data_entrada) = YEAR(CURDATE()) AND MONTH(data_entrada) = MONTH(CURDATE())"
        ];
        
        foreach ($queries as $key => $sql) {
            $stmt = $pdo->query($sql);
            $result = $stmt->fetch();
            $stats[$key] = $result['total'];
        }
        
        // Processos mais utilizados
        $stmt = $pdo->query("
            SELECT p.nome, COUNT(ip.id) as uso_count
            FROM processos p
            LEFT JOIN item_processos ip ON p.id = ip.processo_id
            GROUP BY p.id, p.nome
            ORDER BY uso_count DESC
            LIMIT 5
        ");
        $stats['processos_mais_usados'] = $stmt->fetchAll();
        
        // Status dos pedidos
        $stmt = $pdo->query("
            SELECT processo_atual, COUNT(*) as total
            FROM pedidos
            GROUP BY processo_atual
            ORDER BY total DESC
        ");
        $stats['status_pedidos'] = $stmt->fetchAll();
        
        // Informações da API
        $stats['api_info'] = [
            'version' => SISTEMA_VERSAO,
            'architecture' => 'Modularizada',
            'modules' => ['pedidos', 'itens', 'processos', 'receitas', 'acompanhamento'],
            'total_endpoints' => 25
        ];
        
        return $stats;
        
    } catch (Exception $e) {
        logMessage("Erro ao obter estatísticas: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * Função para verificar integridade das tabelas
 */
function checkTableIntegrity() {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $tables = ['pedidos', 'itens', 'processos', 'pedido_itens', 'item_processos', 'pedido_item_processos'];
        $issues = [];
        
        foreach ($tables as $table) {
            // Verificar se a tabela existe
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            
            if (!$stmt->fetch()) {
                $issues[] = "Tabela '{$table}' não encontrada";
                continue;
            }
            
            // Verificar integridade básica
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM {$table}");
            $result = $stmt->fetch();
            
            logMessage("Tabela '{$table}': {$result['total']} registros", 'INFO');
        }
        
        if (empty($issues)) {
            logMessage("Verificação de integridade concluída - sem problemas", 'INFO');
            return true;
        } else {
            logMessage("Problemas encontrados na verificação: " . implode(', ', $issues), 'WARNING');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Erro na verificação de integridade: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Função para backup básico (estrutura)
 */
function createBackup() {
    global $config_database;
    
    $backup_dir = __DIR__ . '/backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Comando básico de backup (necessita mysqldump no sistema)
    $command = sprintf(
        'mysqldump -h%s -P%s -u%s %s %s > %s',
        escapeshellarg($config_database['host']),
        escapeshellarg($config_database['port']),
        escapeshellarg($config_database['username']),
        !empty($config_database['password']) ? '-p' . escapeshellarg($config_database['password']) : '',
        escapeshellarg($config_database['dbname']),
        escapeshellarg($backup_file)
    );
    
    $output = [];
    $return_code = 0;
    exec($command, $output, $return_code);
    
    if ($return_code === 0) {
        logMessage("Backup criado com sucesso: {$backup_file}", 'INFO');
        return $backup_file;
    } else {
        logMessage("Erro ao criar backup. Código: {$return_code}", 'ERROR');
        return false;
    }
}

// === INICIALIZAÇÃO ===

// Verificar compatibilidade do MySQL
checkMySQLCompatibility();

// Log de inicialização
logMessage("Sistema carregado - Versão " . SISTEMA_VERSAO . " (API Modularizada)", 'INFO');

?>