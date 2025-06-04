# Sistema de Controle de Produção v5.0

## 🏭 Sobre o Sistema

Sistema para controle e acompanhamento de processos produtivos, desenvolvido em PHP/MySQL com interface moderna e responsiva. 

**Versão 5.0** - Completamente refatorada para eliminar redundâncias e simplificar a manutenção.

## ⚡ Principais Melhorias v5.0

### 🔧 **Refatoração Completa**
- ✅ **Eliminada redundância** entre `config.php` e `api.php`
- ✅ **Conexão PDO centralizada** - uma única instância para todo o sistema
- ✅ **Configuração simplificada** - sem complexidade de ambientes
- ✅ **Compatibilidade MySQL 5.0+** - funciona com versões antigas

### ✨ **Novas Funcionalidades**
- ✅ **Instalador automático** (`setup.php`) com interface web
- ✅ **Verificação de requisitos** do sistema durante instalação
- ✅ **Sistema de logs organizado** em arquivos separados
- ✅ **Reorganização automática** de processos com correção de ordem
- ✅ **Funções auxiliares** para validação e formatação

## 📋 Requisitos

- **PHP 7.0+** (testado até 8.0+)
- **MySQL 5.0+** (compatível com versões antigas)
- **Extensões**: PDO, PDO MySQL
- **Permissões**: Escrita no diretório da aplicação

## 🚀 Instalação

### Método Rápido (Recomendado)

1. **Upload dos arquivos** para seu servidor
2. **Acesse**: `http://seusite.com/setup.php`
3. **Configure** os dados do MySQL
4. **Clique** em "Instalar Sistema"
5. **Remova** o `setup.php` por segurança

### Configuração Manual

Edite o `config.php`:
```php
$config_database = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'controle_producao',
    'username' => 'seu_usuario',
    'password' => 'sua_senha'
];
```

## 📁 Estrutura dos Arquivos

```
sistema-controle-producao/
├── index.html      # Página inicial
├── adm.html        # Administração
├── config.php      # Configurações centralizadas
├── api.php         # API refatorada
├── setup.php       # Instalador (remover após uso)
├── script.js       # JavaScript
├── style.css       # Estilos
└── logs/           # Logs automáticos
```

## 🎯 Funcionalidades Principais

### 📦 **Pedidos**
- Criar, editar e excluir pedidos
- Acompanhar status (corte → personalização → produção → expedição)
- Visualizar progresso detalhado
- Gerenciar itens por pedido

### 🛠️ **Processos** 
- 4 processos padrão: Corte, Personalização, Produção, Expedição
- **Reorganização automática** quando há conflitos de ordem
- Adicionar processos personalizados
- **Correção automática** de numeração duplicada

### 📋 **Itens**
- Cadastrar produtos/itens
- Definir **receitas** (quais processos cada item passa)
- Ordem automática baseada na sequência global

### 📊 **Acompanhamento**
- Dashboard com progresso visual
- Status: Aguardando / Em Andamento / Completo
- **Agrupamento inteligente** por processo

## 🔗 API Principais

```php
// Pedidos
GET  api.php?action=get_pedidos
POST api.php?action=add_pedido
PUT  api.php?action=update_pedido&id=X
DELETE api.php?action=delete_pedido&id=X

// Processos  
GET  api.php?action=get_processos
POST api.php?action=add_processo
PUT  api.php?action=update_processo&id=X
POST api.php?action=corrigir_ordem_processos

// Itens
GET  api.php?action=get_itens
POST api.php?action=add_item
GET  api.php?action=get_item_processos&item_id=X
```

## 🔧 Manutenção

### **Logs**
- `logs/api_errors.log` - Erros da API
- `logs/sistema.log` - Logs gerais

### **Funções Úteis**
```php
cleanOldLogs();           // Limpar logs antigos
checkTableIntegrity();    // Verificar integridade
getSystemStats();         // Estatísticas básicas
```

## 🔍 Solução de Problemas

**Erro de conexão:**
- Verifique credenciais no `config.php`
- Confirme se MySQL está rodando

**Erro de permissões:**
- Configure permissões 755 para diretórios
- Configure permissões 644 para arquivos

**Processos com ordem duplicada:**
- Use "Gerenciar Processos → Verificar Ordem"
- Sistema corrige automaticamente

**Erro JSON inválido:**
- Verifique extensão PDO MySQL no PHP
- Confirme encoding UTF-8 dos arquivos

## 🔒 Segurança

### **Após Instalação:**
1. **Remova** o `setup.php`
2. **Configure** backup automático
3. **Monitore** os logs regularmente

### **Arquivos Sensíveis:**
- `config.php` - **NUNCA** commitar no Git
- `logs/` - Contém informações do sistema
- `.installed` - Marca sistema instalado

## 📊 Compatibilidade Testada

- ✅ **PHP**: 7.0, 7.1, 7.2, 7.3, 7.4, 8.0+
- ✅ **MySQL**: 5.0, 5.1, 5.5, 5.6, 5.7, 8.0+
- ✅ **MariaDB**: 10.0+

## 📝 Changelog v5.0

### ✨ **Adicionado**
- Instalador automático com verificação de requisitos
- Sistema de logs centralizado e organizado
- Reorganização automática de processos
- Funções auxiliares para validação/formatação
- Compatibilidade com MySQL 5.0+

### 🔧 **Modificado**
- Configurações centralizadas no `config.php`
- API refatorada para usar conexão única
- Queries otimizadas para MySQL antigo
- Interface simplificada sem complexidade de ambientes

### 🗑️ **Removido**
- Sistema complexo de ambientes (desenvolvimento/produção)
- Redundância entre config.php e api.php
- Dependências desnecessárias

---

**Sistema de Controle de Produção v5.0**  
*Simples, eficiente e compatível*