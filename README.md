# Sistema de Controle de Produção v5.0

## 🏭 Descrição

Sistema completo para controle e acompanhamento de processos produtivos, desenvolvido em PHP/MySQL com interface moderna e responsiva. Esta versão 5.0 foi **completamente refatorada** para eliminar redundâncias, melhorar a performance e facilitar a manutenção.

## ⚡ Principais Melhorias da v5.0

### 🔧 Refatoração Completa
- **Eliminação de redundância**: Centralizadas todas as configurações de banco de dados no `config.php`
- **API unificada**: Todas as funções da API agora usam a mesma conexão PDO
- **Compatibilidade MySQL**: Otimizado para funcionar com versões antigas do MySQL (5.0+)
- **Logging centralizado**: Sistema unificado de logs e tratamento de erros

### 🆕 Novas Funcionalidades
- **Instalador automático**: `setup.php` para instalação guiada
- **Verificação de requisitos**: Checagem automática de dependências do sistema
- **Configurações flexíveis**: Ambiente facilmente configurável (desenvolvimento/produção)
- **Funções auxiliares**: Biblioteca completa de funções para validação e formatação

### 🛡️ Melhorias de Segurança
- **Validação rigorosa**: Todas as entradas são validadas e sanitizadas
- **Prepared statements**: Proteção contra SQL injection
- **Headers de segurança**: Headers HTTP apropriados configurados
- **Logs de auditoria**: Rastreamento detalhado de operações

### 📊 Performance
- **Queries otimizadas**: Consultas SQL melhoradas para versões antigas do MySQL
- **Cache de configurações**: Configurações carregadas uma única vez
- **Gerenciamento de memória**: Controle otimizado do uso de recursos

## 📋 Requisitos do Sistema

### Requisitos Mínimos
- **PHP**: 7.0 ou superior
- **MySQL**: 5.0 ou superior (compatível com versões antigas)
- **Extensões PHP**:
  - PDO
  - PDO MySQL
  - JSON (geralmente incluída)
- **Servidor Web**: Apache, Nginx ou IIS
- **Permissões**: Escrita no diretório da aplicação

### Requisitos Recomendados
- **PHP**: 7.4 ou superior
- **MySQL**: 5.7 ou MariaDB 10.2+
- **Memória**: 128MB+
- **Espaço em disco**: 50MB+

## 🚀 Instalação

### Método 1: Instalação Automática (Recomendado)

1. **Faça o upload dos arquivos** para seu servidor web
2. **Acesse o instalador** em seu navegador: `http://seudominio.com/setup.php`
3. **Siga o assistente** de instalação:
   - Configure os dados do banco de dados
   - Escolha se deseja dados de exemplo
   - Clique em "Instalar Sistema"
4. **Remova o instalador** por segurança: `rm setup.php`

### Método 2: Instalação Manual

1. **Crie o banco de dados**:
```sql
CREATE DATABASE controle_producao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Configure o arquivo config.php**:
```php
$config_database = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'controle_producao',
    'username' => 'seu_usuario',
    'password' => 'sua_senha',
    // ...
];
```

3. **Execute as queries SQL** do arquivo `database.sql` (se disponível)

4. **Configure permissões** de escrita no diretório

## 📁 Estrutura de Arquivos

```
sistema-controle-producao/
├── index.html              # Página inicial
├── adm.html                # Interface de administração
├── config.php              # Configurações centralizadas ✨ NOVO
├── api.php                 # API refatorada ✨ MELHORADO
├── setup.php               # Instalador automático ✨ NOVO
├── script.js               # JavaScript do sistema
├── style.css               # Estilos CSS
├── logs/                   # Diretório de logs ✨ NOVO
│   ├── api_errors.log
│   └── sistema.log
└── backups/                # Backups automáticos ✨ NOVO
```

## 🔧 Configuração

### Ambientes

O sistema suporta diferentes ambientes configuráveis no `config.php`:

```php
define('AMBIENTE', 'desenvolvimento'); // desenvolvimento, producao, teste
```

### Logs

Os logs são automaticamente organizados em:
- `logs/api_errors.log` - Erros da API
- `logs/sistema.log` - Logs gerais do sistema

### Backups

Em ambiente de produção, backups automáticos podem ser configurados chamando a função `createBackup()`.

## 📊 Funcionalidades

### 📦 Gestão de Pedidos
- Criação, edição e exclusão de pedidos
- Acompanhamento de status por processo
- Visualização detalhada com progresso
- Controle de itens por pedido

### 🛠️ Gestão de Processos
- **Reorganização automática** de ordem dos processos
- Processos customizáveis além dos padrão (corte, personalização, produção, expedição)
- **Verificação de integridade** da numeração
- Correção automática de ordens duplicadas

### 📋 Gestão de Itens
- Cadastro de produtos/itens
- **Receitas de produção** (quais processos cada item passa)
- Configuração flexível de processos por item
- Ordem global automática baseada na sequência de processos

### 📈 Acompanhamento
- Dashboard com progresso dos pedidos
- **Agrupamento inteligente** por processos
- Controle de status: aguardando, em andamento, completo
- Histórico detalhado de execução

## 🔑 Principais Endpoints da API

### Pedidos
- `GET api.php?action=get_pedidos` - Listar pedidos
- `POST api.php?action=add_pedido` - Criar pedido
- `PUT api.php?action=update_pedido&id=X` - Atualizar pedido
- `DELETE api.php?action=delete_pedido&id=X` - Excluir pedido

### Processos
- `GET api.php?action=get_processos` - Listar processos
- `POST api.php?action=add_processo` - Criar processo (com reorganização automática)
- `PUT api.php?action=update_processo&id=X` - Atualizar processo
- `GET api.php?action=get_processos_ordem` - Verificar ordem dos processos
- `POST api.php?action=corrigir_ordem_processos` - Corrigir numeração

### Itens
- `GET api.php?action=get_itens` - Listar itens
- `POST api.php?action=add_item` - Criar item
- `GET api.php?action=get_item_processos&item_id=X` - Processos do item

## 🛠️ Manutenção

### Limpeza de Logs
Os logs são automaticamente limpos após 30 dias. Para limpeza manual:
```php
cleanOldLogs(); // Função disponível no config.php
```

### Verificação de Integridade
```php
checkTableIntegrity(); // Verifica estrutura das tabelas
checkMySQLCompatibility(); // Verifica compatibilidade do MySQL
```

### Estatísticas do Sistema
```php
$stats = getSystemStats(); // Retorna estatísticas de uso
```

## 🔍 Troubleshooting

### Problemas Comuns

**1. Erro de conexão com banco:**
- Verifique credenciais no `config.php`
- Confirme se o MySQL está rodando
- Teste conectividade na porta 3306

**2. Erro de permissões:**
- Verifique permissões de escrita no diretório
- Configure permissões 755 para diretórios e 644 para arquivos

**3. Erro "JSON inválido":**
- Verifique se o PHP tem a extensão JSON ativada
- Confirme encoding UTF-8 nos arquivos

**4. Processos com ordem duplicada:**
- Use a função "Verificar Ordem" no painel de administração
- O sistema corrige automaticamente

### Logs para Debug

Em ambiente de desenvolvimento, ative logs detalhados:
```php
define('AMBIENTE', 'desenvolvimento');
```

Verifique os arquivos de log em:
- `logs/api_errors.log`
- `logs/sistema.log`

## 🔐 Segurança

### Recomendações de Produção

1. **Remover arquivos de instalação**:
```bash
rm setup.php
```

2. **Configurar ambiente**:
```php
define('AMBIENTE', 'producao');
```

3. **Backup regular**:
- Configure backups automáticos do banco
- Implemente rotação de logs

4. **Monitoramento**:
- Monitore logs de erro
- Verifique integridade das tabelas periodicamente

## 📞 Suporte

### Funcionalidades Principais Testadas
- ✅ Criação e gestão de pedidos
- ✅ Reorganização automática de processos
- ✅ Agrupamento inteligente por ordem global
- ✅ Compatibilidade com MySQL 5.0+
- ✅ Instalação automática
- ✅ Sistema de logs centralizado

### Compatibilidade
- **MySQL**: 5.0, 5.1, 5.5, 5.6, 5.7, 8.0+
- **MariaDB**: 10.0+
- **PHP**: 7.0, 7.1, 7.2, 7.3, 7.4, 8.0+

## 📝 Changelog v5.0

### 🔧 Refatoração
- Eliminada redundância de configurações entre `config.php` e `api.php`
- Centralizada conexão PDO para todo o sistema
- Otimizadas queries para compatibilidade com MySQL antigo
- Implementado sistema unificado de logs

### ✨ Novas Funcionalidades
- Instalador automático com interface web
- Verificação automática de requisitos do sistema
- Funções auxiliares para validação e formatação
- Sistema de backup automático
- Verificação de integridade das tabelas
- Estatísticas detalhadas do sistema

### 🛡️ Melhorias de Segurança
- Validação rigorosa de todas as entradas
- Sanitização automática de dados
- Headers de segurança configurados
- Logs de auditoria implementados

### 📊 Performance
- Queries otimizadas para MySQL antigo
- Gerenciamento melhorado de memória
- Cache de configurações
- Limpeza automática de logs

---

**Sistema de Controle de Produção v5.0**  
Desenvolvido para otimizar e modernizar seus processos produtivos com foco em performance, segurança e facilidade de manutenção.