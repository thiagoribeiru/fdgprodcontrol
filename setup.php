<?php
// setup.php - Instalador do Sistema de Controle de Produção

// Verificar se o sistema já foi instalado
if (file_exists(__DIR__ . '/.installed')) {
    die('Sistema já está instalado. Para reinstalar, remova o arquivo .installed');
}

// Configurações do instalador
$installer_config = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'controle_producao',
    'db_user' => 'root',
    'db_pass' => '',
    'create_sample_data' => true
];

// Verificar se é uma requisição POST (formulário de instalação)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Processar instalação
    processInstallation($_POST);
}

/**
 * Processa a instalação do sistema
 */
function processInstallation($post_data) {
    try {
        // Validar dados do formulário
        $config = validateInstallationData($post_data);
        
        // Conectar ao MySQL
        $pdo = connectToMySQL($config);
        
        // Criar banco de dados se não existir
        createDatabase($pdo, $config['db_name']);
        
        // Conectar ao banco específico
        $pdo_db = connectToDatabase($config);
        
        // Criar tabelas
        createTables($pdo_db);
        
        // Inserir dados iniciais
        insertInitialData($pdo_db);
        
        // Inserir dados de exemplo se solicitado
        if ($config['create_sample_data']) {
            insertSampleData($pdo_db);
        }
        
        // Criar arquivo de configuração
        createConfigFile($config);
        
        // Marcar como instalado
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        
        // Sucesso
        showSuccessPage();
        
    } catch (Exception $e) {
        showErrorPage($e->getMessage());
    }
}

/**
 * Valida os dados do formulário de instalação
 */
function validateInstallationData($data) {
    $config = [];
    
    // Validar host
    $config['db_host'] = trim($data['db_host'] ?? 'localhost');
    if (empty($config['db_host'])) {
        throw new Exception('Host do banco de dados é obrigatório');
    }
    
    // Validar porta
    $config['db_port'] = trim($data['db_port'] ?? '3306');
    if (!is_numeric($config['db_port'])) {
        throw new Exception('Porta deve ser um número');
    }
    
    // Validar nome do banco
    $config['db_name'] = trim($data['db_name'] ?? 'controle_producao');
    if (empty($config['db_name'])) {
        throw new Exception('Nome do banco de dados é obrigatório');
    }
    
    // Validar usuário
    $config['db_user'] = trim($data['db_user'] ?? 'root');
    if (empty($config['db_user'])) {
        throw new Exception('Usuário do banco de dados é obrigatório');
    }
    
    // Senha pode ser vazia
    $config['db_pass'] = $data['db_pass'] ?? '';
    
    // Dados de exemplo
    $config['create_sample_data'] = isset($data['create_sample_data']);
    
    return $config;
}

/**
 * Conecta ao MySQL (sem especificar banco)
 */
function connectToMySQL($config) {
    try {
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4";
        
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception("Erro ao conectar ao MySQL: " . $e->getMessage());
    }
}

/**
 * Cria o banco de dados se não existir
 */
function createDatabase($pdo, $db_name) {
    try {
        // Verificar se o banco já existe
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$db_name]);
        
        if (!$stmt->fetch()) {
            // Criar banco de dados
            $sql = "CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $pdo->exec($sql);
        }
        
    } catch (PDOException $e) {
        throw new Exception("Erro ao criar banco de dados: " . $e->getMessage());
    }
}

/**
 * Conecta ao banco de dados específico
 */
function connectToDatabase($config) {
    try {
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
        
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception("Erro ao conectar ao banco: " . $e->getMessage());
    }
}

/**
 * Cria as tabelas do sistema
 */
function createTables($pdo) {
    $tables = [
        // Tabela de pedidos
        "CREATE TABLE IF NOT EXISTS `pedidos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `data_entrada` date NOT NULL,
            `data_entrega` date NOT NULL,
            `codigo_pedido` varchar(50) NOT NULL,
            `cliente` varchar(100) NOT NULL,
            `processo_atual` varchar(50) NOT NULL DEFAULT 'corte',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `codigo_pedido` (`codigo_pedido`),
            KEY `idx_data_entrada` (`data_entrada`),
            KEY `idx_processo_atual` (`processo_atual`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de itens
        "CREATE TABLE IF NOT EXISTS `itens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nome` varchar(100) NOT NULL,
            `descricao` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `nome` (`nome`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de processos
        "CREATE TABLE IF NOT EXISTS `processos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nome` varchar(50) NOT NULL,
            `descricao` text,
            `ordem` int(11) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `nome` (`nome`),
            UNIQUE KEY `ordem` (`ordem`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de itens do pedido
        "CREATE TABLE IF NOT EXISTS `pedido_itens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `pedido_id` int(11) NOT NULL,
            `item_id` int(11) NOT NULL,
            `quantidade` int(11) NOT NULL DEFAULT 1,
            `observacoes` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `fk_pedido_itens_pedido` (`pedido_id`),
            KEY `fk_pedido_itens_item` (`item_id`),
            CONSTRAINT `fk_pedido_itens_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pedido_itens_item` FOREIGN KEY (`item_id`) REFERENCES `itens` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de processos dos itens (receitas)
        "CREATE TABLE IF NOT EXISTS `item_processos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `item_id` int(11) NOT NULL,
            `processo_id` int(11) NOT NULL,
            `observacoes` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `fk_item_processos_item` (`item_id`),
            KEY `fk_item_processos_processo` (`processo_id`),
            CONSTRAINT `fk_item_processos_item` FOREIGN KEY (`item_id`) REFERENCES `itens` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_item_processos_processo` FOREIGN KEY (`processo_id`) REFERENCES `processos` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de controle de processos dos itens do pedido
        "CREATE TABLE IF NOT EXISTS `pedido_item_processos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `pedido_item_id` int(11) NOT NULL,
            `processo_id` int(11) NOT NULL,
            `status` enum('aguardando','em_andamento','completo') NOT NULL DEFAULT 'aguardando',
            `data_inicio` datetime NULL,
            `data_conclusao` datetime NULL,
            `observacoes` text,
            `usuario_responsavel` varchar(100),
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `fk_pip_pedido_item` (`pedido_item_id`),
            KEY `fk_pip_processo` (`processo_id`),
            KEY `idx_status` (`status`),
            CONSTRAINT `fk_pip_pedido_item` FOREIGN KEY (`pedido_item_id`) REFERENCES `pedido_itens` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pip_processo` FOREIGN KEY (`processo_id`) REFERENCES `processos` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
}

/**
 * Insere dados iniciais obrigatórios
 */
function insertInitialData($pdo) {
    // Processos padrão do sistema
    $processos_padrao = [
        ['nome' => 'corte', 'descricao' => 'Processo de corte dos materiais', 'ordem' => 1],
        ['nome' => 'personalização', 'descricao' => 'Processo de personalização do produto', 'ordem' => 2],
        ['nome' => 'produção', 'descricao' => 'Processo principal de produção', 'ordem' => 3],
        ['nome' => 'expedição', 'descricao' => 'Processo de expedição e envio', 'ordem' => 4]
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO processos (nome, descricao, ordem) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($processos_padrao as $processo) {
        $stmt->execute([$processo['nome'], $processo['descricao'], $processo['ordem']]);
    }
}

/**
 * Insere dados de exemplo para demonstração
 */
function insertSampleData($pdo) {
    // Itens de exemplo
    $itens_exemplo = [
        ['nome' => 'Camiseta Básica', 'descricao' => 'Camiseta básica 100% algodão'],
        ['nome' => 'Caneca Personalizada', 'descricao' => 'Caneca branca para personalização'],
        ['nome' => 'Adesivo Vinil', 'descricao' => 'Adesivo em vinil para aplicação'],
        ['nome' => 'Banner 1x1m', 'descricao' => 'Banner em lona 440g 1x1 metro']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO itens (nome, descricao) VALUES (?, ?)");
    
    foreach ($itens_exemplo as $item) {
        $stmt->execute([$item['nome'], $item['descricao']]);
    }
    
    // Buscar IDs dos processos e itens criados
    $processos = $pdo->query("SELECT id, nome FROM processos ORDER BY ordem")->fetchAll();
    $itens = $pdo->query("SELECT id, nome FROM itens")->fetchAll();
    
    // Criar receitas (quais processos cada item passa)
    $receitas = [
        'Camiseta Básica' => ['corte', 'personalização', 'produção'],
        'Caneca Personalizada' => ['personalização', 'produção', 'expedição'],
        'Adesivo Vinil' => ['corte', 'personalização', 'expedição'],
        'Banner 1x1m' => ['corte', 'personalização', 'produção', 'expedição']
    ];
    
    $stmt_receita = $pdo->prepare("INSERT INTO item_processos (item_id, processo_id) VALUES (?, ?)");
    
    foreach ($receitas as $item_nome => $processos_nomes) {
        // Encontrar ID do item
        $item_id = null;
        foreach ($itens as $item) {
            if ($item['nome'] === $item_nome) {
                $item_id = $item['id'];
                break;
            }
        }
        
        if ($item_id) {
            foreach ($processos_nomes as $processo_nome) {
                // Encontrar ID do processo
                $processo_id = null;
                foreach ($processos as $processo) {
                    if ($processo['nome'] === $processo_nome) {
                        $processo_id = $processo['id'];
                        break;
                    }
                }
                
                if ($processo_id) {
                    $stmt_receita->execute([$item_id, $processo_id]);
                }
            }
        }
    }
    
    // Pedidos de exemplo
    $pedidos_exemplo = [
        [
            'data_entrada' => date('Y-m-d'),
            'data_entrega' => date('Y-m-d', strtotime('+7 days')),
            'codigo_pedido' => 'PED-001',
            'cliente' => 'Empresa ABC Ltda',
            'processo_atual' => 'corte'
        ],
        [
            'data_entrada' => date('Y-m-d', strtotime('-1 day')),
            'data_entrega' => date('Y-m-d', strtotime('+5 days')),
            'codigo_pedido' => 'PED-002',
            'cliente' => 'João da Silva',
            'processo_atual' => 'personalização'
        ]
    ];
    
    $stmt_pedido = $pdo->prepare("
        INSERT INTO pedidos (data_entrada, data_entrega, codigo_pedido, cliente, processo_atual) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($pedidos_exemplo as $pedido) {
        $stmt_pedido->execute([
            $pedido['data_entrada'],
            $pedido['data_entrega'],
            $pedido['codigo_pedido'],
            $pedido['cliente'],
            $pedido['processo_atual']
        ]);
    }
}

/**
 * Cria/atualiza apenas as configurações de banco no config.php existente
 */
function createConfigFile($config) {
    $config_file = __DIR__ . '/config.php';
    
    // Verificar se já existe um config.php completo
    if (file_exists($config_file)) {
        // Ler o arquivo existente
        $existing_content = file_get_contents($config_file);
        
        // Verificar se é um config.php completo (tem mais de 50 linhas)
        if (substr_count($existing_content, "\n") > 50) {
            // É um arquivo completo, apenas atualizar as configurações do banco
            updateDatabaseConfig($config);
            return;
        }
    }
    
    // Se não existe ou é muito simples, criar config completo
    createFullConfigFile($config);
}

/**
 * Atualiza apenas as configurações do banco em um config.php existente
 */
function updateDatabaseConfig($config) {
    $config_file = __DIR__ . '/config.php';
    $content = file_get_contents($config_file);
    
    // Padrão para encontrar o array de configuração do banco
    $pattern = '/\$config_database\s*=\s*\[(.*?)\];/s';
    
    // Nova configuração do banco
    $new_db_config = '$config_database = [' . "\n";
    $new_db_config .= "    'host' => '" . addslashes($config['db_host']) . "'," . "\n";
    $new_db_config .= "    'port' => '" . addslashes($config['db_port']) . "'," . "\n";
    $new_db_config .= "    'dbname' => '" . addslashes($config['db_name']) . "'," . "\n";
    $new_db_config .= "    'username' => '" . addslashes($config['db_user']) . "'," . "\n";
    $new_db_config .= "    'password' => '" . addslashes($config['db_pass']) . "'," . "\n";
    $new_db_config .= "    'charset' => 'utf8mb4'," . "\n";
    $new_db_config .= "    'options' => [" . "\n";
    $new_db_config .= "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION," . "\n";
    $new_db_config .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC," . "\n";
    $new_db_config .= "        PDO::ATTR_EMULATE_PREPARES => false," . "\n";
    $new_db_config .= '        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"' . "\n";
    $new_db_config .= "    ]" . "\n";
    $new_db_config .= '];';
    
    // Substituir apenas a configuração do banco
    if (preg_match($pattern, $content)) {
        $updated_content = preg_replace($pattern, $new_db_config, $content);
        file_put_contents($config_file, $updated_content);
    } else {
        // Se não encontrou o padrão, adicionar no início após <?php
        $lines = explode("\n", $content);
        $new_lines = [];
        $added = false;
        
        foreach ($lines as $line) {
            $new_lines[] = $line;
            if (!$added && (strpos($line, '<?php') !== false || strpos($line, 'config_database') !== false)) {
                $new_lines[] = '';
                $new_lines[] = '// Configurações do banco de dados (atualizado pelo instalador)';
                $new_lines[] = $new_db_config;
                $new_lines[] = '';
                $added = true;
            }
        }
        
        if (!$added) {
            // Fallback: adicionar no final
            $new_lines[] = '';
            $new_lines[] = '// Configurações do banco de dados (atualizado pelo instalador)';
            $new_lines[] = $new_db_config;
        }
        
        file_put_contents($config_file, implode("\n", $new_lines));
    }
}

/**
 * Cria um arquivo config.php completo (usado quando não existe um arquivo completo)
 */
function createFullConfigFile($config) {
    // Construir conteúdo linha por linha para evitar problemas com aspas
    $lines = [];
    $lines[] = '<?php';
    $lines[] = '// config.php - Configurações do Sistema (Gerado automaticamente)';
    $lines[] = '';
    $lines[] = '// Versão do sistema';
    $lines[] = "define('SISTEMA_VERSAO', '5.0');";
    $lines[] = "define('SISTEMA_NOME', 'Sistema de Controle de Produção');";
    $lines[] = '';
    $lines[] = '// Configurações do banco de dados';
    $lines[] = '$config_database = [';
    $lines[] = "    'host' => '" . addslashes($config['db_host']) . "',";
    $lines[] = "    'port' => '" . addslashes($config['db_port']) . "',";
    $lines[] = "    'dbname' => '" . addslashes($config['db_name']) . "',";
    $lines[] = "    'username' => '" . addslashes($config['db_user']) . "',";
    $lines[] = "    'password' => '" . addslashes($config['db_pass']) . "',";
    $lines[] = "    'charset' => 'utf8mb4',";
    $lines[] = "    'options' => [";
    $lines[] = "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,";
    $lines[] = "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,";
    $lines[] = "        PDO::ATTR_EMULATE_PREPARES => false,";
    $lines[] = '        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"';
    $lines[] = "    ]";
    $lines[] = '];';
    $lines[] = '';
    $lines[] = '// Configurações de timezone';
    $lines[] = "define('TIMEZONE', 'America/Sao_Paulo');";
    $lines[] = 'date_default_timezone_set(TIMEZONE);';
    $lines[] = '';
    $lines[] = '// Configurações de log';
    $lines[] = "define('LOG_ERRORS', true);";
    $lines[] = "define('LOG_PATH', __DIR__ . '/logs/');";
    $lines[] = '';
    $lines[] = '// Criar diretório de logs se não existir';
    $lines[] = 'if (!is_dir(LOG_PATH)) {';
    $lines[] = '    mkdir(LOG_PATH, 0755, true);';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '// Variável global para conexão PDO';
    $lines[] = '$pdo = null;';
    $lines[] = '';
    $lines[] = 'try {';
    $lines[] = '    $dsn = "mysql:host={$config_database[\'host\']};port={$config_database[\'port\']};dbname={$config_database[\'dbname\']};charset={$config_database[\'charset\']}";';
    $lines[] = '    $pdo = new PDO($dsn, $config_database[\'username\'], $config_database[\'password\'], $config_database[\'options\']);';
    $lines[] = '} catch(PDOException $e) {';
    $lines[] = '    die("Erro na conexão com o banco de dados: " . $e->getMessage());';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '// Função para retornar dados em JSON';
    $lines[] = 'function jsonResponse($data, $status_code = 200) {';
    $lines[] = '    http_response_code($status_code);';
    $lines[] = '    header(\'Content-Type: application/json; charset=utf-8\');';
    $lines[] = '    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);';
    $lines[] = '    exit;';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '// Função para validar campos obrigatórios';
    $lines[] = 'function validateRequired($data, $fields) {';
    $lines[] = '    foreach ($fields as $field) {';
    $lines[] = '        if (!isset($data[$field]) || empty(trim($data[$field]))) {';
    $lines[] = '            return "O campo \'$field\' é obrigatório.";';
    $lines[] = '        }';
    $lines[] = '    }';
    $lines[] = '    return null;';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '?>';
    
    $config_content = implode("\n", $lines);
    
    file_put_contents(__DIR__ . '/config.php', $config_content);
} = $new_db_config;
                $new_lines[] = '';
                $added = true;
            }
        }
        
        if (!$added) {
            // Fallback: adicionar no final
            $new_lines[] = '';
            $new_lines[] = '// Configurações do banco de dados (atualizado pelo instalador)';
            $new_lines[] = $new_db_config;
        }
        
        file_put_contents($config_file, implode("\n", $new_lines));
    }
}

/**
 * Cria um arquivo config.php completo (usado quando não existe um arquivo completo)
 */
function createFullConfigFile($config) {
    // Construir conteúdo linha por linha para evitar problemas com aspas
    $lines = [];
    $lines[] = '<?php';
    $lines[] = '// config.php - Configurações do Sistema (Gerado automaticamente)';
    $lines[] = '';
    $lines[] = '// Versão do sistema';
    $lines[] = "define('SISTEMA_VERSAO', '5.0');";
    $lines[] = "define('SISTEMA_NOME', 'Sistema de Controle de Produção');";
    $lines[] = '';
    $lines[] = '// Configurações de ambiente';
    $lines[] = "define('AMBIENTE', 'desenvolvimento');";
    $lines[] = '';
    $lines[] = '// Configurações do banco de dados';
    $lines[] = '$config_database = [';
    $lines[] = "    'host' => '" . addslashes($config['db_host']) . "',";
    $lines[] = "    'port' => '" . addslashes($config['db_port']) . "',";
    $lines[] = "    'dbname' => '" . addslashes($config['db_name']) . "',";
    $lines[] = "    'username' => '" . addslashes($config['db_user']) . "',";
    $lines[] = "    'password' => '" . addslashes($config['db_pass']) . "',";
    $lines[] = "    'charset' => 'utf8mb4',";
    $lines[] = "    'options' => [";
    $lines[] = "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,";
    $lines[] = "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,";
    $lines[] = "        PDO::ATTR_EMULATE_PREPARES => false,";
    $lines[] = '        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"';
    $lines[] = "    ]";
    $lines[] = '];';
    $lines[] = '';
    $lines[] = '// Configurações de timezone';
    $lines[] = "define('TIMEZONE', 'America/Sao_Paulo');";
    $lines[] = 'date_default_timezone_set(TIMEZONE);';
    $lines[] = '';
    $lines[] = '// Configurações de log';
    $lines[] = "define('LOG_ERRORS', true);";
    $lines[] = "define('LOG_PATH', __DIR__ . '/logs/');";
    $lines[] = '';
    $lines[] = '// Criar diretório de logs se não existir';
    $lines[] = 'if (!is_dir(LOG_PATH)) {';
    $lines[] = '    mkdir(LOG_PATH, 0755, true);';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '// Variável global para conexão PDO';
    $lines[] = '$pdo = null;';
    $lines[] = '';
    $lines[] = 'try {';
    $lines[] = '    $dsn = "mysql:host={$config_database[\'host\']};port={$config_database[\'port\']};dbname={$config_database[\'dbname\']};charset={$config_database[\'charset\']}";';
    $lines[] = '    $pdo = new PDO($dsn, $config_database[\'username\'], $config_database[\'password\'], $config_database[\'options\']);';
    $lines[] = '} catch(PDOException $e) {';
    $lines[] = '    die("Erro na conexão com o banco de dados: " . $e->getMessage());';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '// Função para retornar dados em JSON';
    $lines[] = 'function jsonResponse($data, $status_code = 200) {';
    $lines[] = '    http_response_code($status_code);';
    $lines[] = '    header(\'Content-Type: application/json; charset=utf-8\');';
    $lines[] = '    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);';
    $lines[] = '    exit;';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '// Função para validar campos obrigatórios';
    $lines[] = 'function validateRequired($data, $fields) {';
    $lines[] = '    foreach ($fields as $field) {';
    $lines[] = '        if (!isset($data[$field]) || empty(trim($data[$field]))) {';
    $lines[] = '            return "O campo \'$field\' é obrigatório.";';
    $lines[] = '        }';
    $lines[] = '    }';
    $lines[] = '    return null;';
    $lines[] = '}';
    $lines[] = '';
    $lines[] = '?>';
    
    $config_content = implode("\n", $lines);
    
    file_put_contents(__DIR__ . '/config.php', $config_content);
}

/**
 * Exibe página de sucesso
 */
function showSuccessPage() {
    $db_name = htmlspecialchars($_POST['db_name'] ?? 'controle_producao');
    $sample_data = isset($_POST['create_sample_data']) ? 'Sim' : 'Não';
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Instalação Concluída - Sistema de Controle de Produção</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .success { color: #4CAF50; text-align: center; }
            .success h1 { font-size: 2.5em; margin-bottom: 20px; }
            .success .icon { font-size: 4em; margin-bottom: 20px; }
            .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; color: #856404; }
            .btn { display: inline-block; background: #4CAF50; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .btn:hover { background: #45a049; }
            .next-steps { margin: 30px 0; }
            .next-steps h3 { color: #333; margin-bottom: 15px; }
            .next-steps ol { padding-left: 20px; }
            .next-steps li { margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success">
                <div class="icon">✅</div>
                <h1>Instalação Concluída!</h1>
                <p>O Sistema de Controle de Produção foi instalado com sucesso.</p>
            </div>
            
            <div class="info">
                <strong>Informações da Instalação:</strong><br>
                • Data/Hora: <?= date('d/m/Y H:i:s') ?><br>
                • Versão: 5.0<br>
                • Banco: <?= $db_name ?><br>
                • Dados de exemplo: <?= $sample_data ?><br>
                • Config: <?= file_exists(__DIR__ . '/config.php') && (substr_count(file_get_contents(__DIR__ . '/config.php'), "\n") > 50) ? 'Atualizado (preservado)' : 'Criado novo' ?>
            </div>
            
            <div class="warning">
                <strong>⚠️ Importante:</strong><br>
                Por segurança, remova ou renomeie o arquivo <code>setup.php</code> após a instalação.
            </div>
            
            <div class="next-steps">
                <h3>📋 Próximos Passos:</h3>
                <ol>
                    <li><strong>Acesse o sistema:</strong> Clique no botão abaixo para ir à página inicial</li>
                    <li><strong>Configure processos:</strong> Vá em "Administração > Gerenciar Processos" para personalizar</li>
                    <li><strong>Cadastre itens:</strong> Em "Administração > Gerenciar Itens" adicione seus produtos</li>
                    <li><strong>Crie pedidos:</strong> Comece a usar o sistema criando seus primeiros pedidos</li>
                    <li><strong>Backup:</strong> Configure backups automáticos para seus dados</li>
                </ol>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.html" class="btn">🏠 Ir para o Sistema</a>
                <a href="adm.html" class="btn">⚙️ Administração</a>
            </div>
            
            <div style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9em;">
                Sistema de Controle de Produção v5.0<br>
                Desenvolvido para otimizar seus processos produtivos
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Exibe página de erro
 */
function showErrorPage($error_message) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erro na Instalação - Sistema de Controle de Produção</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error { color: #f44336; text-align: center; }
            .error h1 { font-size: 2.5em; margin-bottom: 20px; }
            .error .icon { font-size: 4em; margin-bottom: 20px; }
            .error-details { background: #ffebee; padding: 15px; border-radius: 5px; margin: 20px 0; color: #c62828; }
            .btn { display: inline-block; background: #2196F3; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .btn:hover { background: #1976D2; }
            .troubleshooting { margin: 30px 0; }
            .troubleshooting h3 { color: #333; margin-bottom: 15px; }
            .troubleshooting ul { padding-left: 20px; }
            .troubleshooting li { margin: 8px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error">
                <div class="icon">❌</div>
                <h1>Erro na Instalação</h1>
                <p>Ocorreu um problema durante a instalação do sistema.</p>
            </div>
            
            <div class="error-details">
                <strong>Detalhes do erro:</strong><br>
                <?= htmlspecialchars($error_message) ?>
            </div>
            
            <div class="troubleshooting">
                <h3>🔧 Possíveis Soluções:</h3>
                <ul>
                    <li><strong>Conexão com banco:</strong> Verifique se o MySQL está rodando e as credenciais estão corretas</li>
                    <li><strong>Permissões:</strong> Certifique-se que o usuário do banco tem permissões para criar bancos e tabelas</li>
                    <li><strong>Versão do MySQL:</strong> O sistema requer MySQL 5.0 ou superior</li>
                    <li><strong>Extensão PDO:</strong> Verifique se a extensão PDO MySQL está instalada no PHP</li>
                    <li><strong>Firewall:</strong> Verifique se não há bloqueios de firewall na porta do MySQL</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="setup.php" class="btn">🔄 Tentar Novamente</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Se chegou até aqui, mostrar formulário de instalação
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Sistema de Controle de Produção</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            margin: 0; 
            padding: 20px; 
            min-height: 100vh;
        }
        .container { 
            max-width: 700px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
        }
        .header { 
            text-align: center; 
            margin-bottom: 40px; 
            color: #333;
        }
        .header h1 { 
            font-size: 2.5em; 
            margin-bottom: 10px; 
            color: #4CAF50;
        }
        .header p { 
            font-size: 1.2em; 
            color: #666; 
            margin-bottom: 0;
        }
        .form-group { 
            margin-bottom: 25px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #333; 
        }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e1e1e1; 
            border-radius: 8px; 
            font-size: 16px; 
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus { 
            outline: none; 
            border-color: #4CAF50; 
        }
        .form-row { 
            display: flex; 
            gap: 20px; 
        }
        .form-row .form-group { 
            flex: 1; 
        }
        .checkbox-group { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            margin: 20px 0;
        }
        .checkbox-group input[type="checkbox"] { 
            width: auto; 
            margin: 0;
        }
        .btn-install { 
            background: linear-gradient(135deg, #4CAF50, #45a049); 
            color: white; 
            padding: 15px 30px; 
            border: none; 
            border-radius: 8px; 
            font-size: 18px; 
            font-weight: 600; 
            cursor: pointer; 
            width: 100%; 
            transition: all 0.3s ease;
        }
        .btn-install:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        .info-box { 
            background: #e3f2fd; 
            border-left: 4px solid #2196F3; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 5px;
        }
        .warning-box { 
            background: #fff3cd; 
            border-left: 4px solid #ffc107; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 5px; 
            color: #856404;
        }
        .requirements { 
            margin: 30px 0; 
        }
        .requirements h3 { 
            color: #333; 
            margin-bottom: 15px; 
        }
        .requirements ul { 
            padding-left: 20px; 
        }
        .requirements li { 
            margin: 8px 0; 
        }
        .req-ok { color: #4CAF50; }
        .req-error { color: #f44336; }
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .container { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏭 Sistema de Controle de Produção</h1>
            <p>Assistente de Instalação - Versão 5.0</p>
        </div>
        
        <div class="info-box">
            <strong>ℹ️ Sobre a Instalação:</strong><br>
            Este assistente irá configurar o banco de dados, criar as tabelas necessárias e inserir os dados iniciais do sistema.
        </div>
        
        <?php
        // Verificar requisitos do sistema
        $requirements_ok = true;
        ?>
        
        <div class="requirements">
            <h3>📋 Verificação de Requisitos:</h3>
            <ul>
                <li class="<?= version_compare(PHP_VERSION, '7.0.0', '>=') ? 'req-ok' : 'req-error' ?>">
                    PHP 7.0+ (atual: <?= PHP_VERSION ?>)
                    <?= version_compare(PHP_VERSION, '7.0.0', '>=') ? '✅' : '❌' ?>
                </li>
                <li class="<?= extension_loaded('pdo') ? 'req-ok' : 'req-error' ?>">
                    Extensão PDO 
                    <?= extension_loaded('pdo') ? '✅' : '❌' ?>
                    <?php if (!extension_loaded('pdo')) $requirements_ok = false; ?>
                </li>
                <li class="<?= extension_loaded('pdo_mysql') ? 'req-ok' : 'req-error' ?>">
                    Extensão PDO MySQL 
                    <?= extension_loaded('pdo_mysql') ? '✅' : '❌' ?>
                    <?php if (!extension_loaded('pdo_mysql')) $requirements_ok = false; ?>
                </li>
                <li class="<?= is_writable(__DIR__) ? 'req-ok' : 'req-error' ?>">
                    Permissão de escrita no diretório 
                    <?= is_writable(__DIR__) ? '✅' : '❌' ?>
                    <?php if (!is_writable(__DIR__)) $requirements_ok = false; ?>
                </li>
            </ul>
        </div>
        
        <?php if (!$requirements_ok): ?>
        <div class="warning-box">
            <strong>⚠️ Requisitos não atendidos!</strong><br>
            Corrija os problemas indicados acima antes de continuar com a instalação.
        </div>
        <?php else: ?>
        
        <form method="POST" action="setup.php">
            <h3>🔧 Configuração do Banco de Dados:</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="db_host">Host do Banco:</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($installer_config['db_host']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_port">Porta:</label>
                    <input type="number" id="db_port" name="db_port" value="<?= htmlspecialchars($installer_config['db_port']) ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="db_name">Nome do Banco:</label>
                <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($installer_config['db_name']) ?>" required>
                <small style="color: #666;">O banco será criado automaticamente se não existir</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="db_user">Usuário:</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($installer_config['db_user']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Senha:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($installer_config['db_pass']) ?>">
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="create_sample_data" name="create_sample_data" <?= $installer_config['create_sample_data'] ? 'checked' : '' ?>>
                <label for="create_sample_data">Criar dados de exemplo (recomendado para testes)</label>
            </div>
            
            <div class="warning-box">
                <strong>⚠️ Atenção:</strong><br>
                • Certifique-se que o usuário do banco tem permissões para criar bancos e tabelas<br>
                • Todos os dados existentes no banco serão preservados<br>
                • Esta instalação é compatível com MySQL 5.0+
            </div>
            
            <button type="submit" class="btn-install">
                🚀 Instalar Sistema
            </button>
        </form>
        
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9em;">
            Sistema de Controle de Produção v5.0<br>
            Refatorado para eliminar redundâncias e melhorar performance
        </div>
    </div>
</body>
</html>