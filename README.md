# Sistema de Controle de Produção v5.2

## 🏭 Sobre o Sistema

Sistema para controle e acompanhamento de processos produtivos, desenvolvido em PHP/MySQL com interface moderna e responsiva. 

**Versão 5.2** - API modularizada para melhor manutenção e organização do código.

## ⚡ Principais Melhorias v5.2

### 🔧 **API Modularizada**
- ✅ **API dividida em módulos** - Código organizado por funcionalidade
- ✅ **Facilita manutenção** - Arquivos menores e mais específicos
- ✅ **Melhor organização** - Cada módulo com responsabilidade única
- ✅ **Router centralizado** - api.php como ponto de entrada único
- ✅ **Proteção de acesso** - Diretório api/ protegido por .htaccess

### 📁 **Estrutura Modular**
```
api/
├── pedidos.php         # 📦 Gestão de Pedidos
├── itens.php          # 🏷️ Gestão de Itens
├── processos.php      # ⚙️ Gestão de Processos
├── receitas.php       # 📋 Receitas (Item-Processos)
├── acompanhamento.php # 📊 Acompanhamento e Status
└── .htaccess          # 🔒 Proteção de Acesso
```

### 🛡️ **Melhorias de Segurança**
- ✅ **Acesso protegido** - Módulos acessíveis apenas via include
- ✅ **Validação centralizada** - Verificações de segurança em cada módulo
- ✅ **Headers de segurança** - Proteção contra ataques comuns
- ✅ **Log de acesso** - Monitoramento de tentativas de acesso direto

### 🔧 **Melhorias Herdadas das Versões Anteriores**
- ✅ **Eliminada redundância** entre `config.php` e `api.php`
- ✅ **Conexão PDO centralizada** - uma única instância para todo o sistema
- ✅ **Configuração simplificada** - sem complexidade de ambientes
- ✅ **Compatibilidade MySQL 5.0+** - funciona com versões antigas
- ✅ **Instalador automático** (`setup.php`) com interface web
- ✅ **Sistema de logs organizado** em arquivos separados
- ✅ **Reorganização automática** de processos com correção de ordem

## 📋 Requisitos

- **PHP 7.0+** (testado até 8.0+)
- **MySQL 5.0+** (compatível com versões antigas)
- **Extensões**: PDO, PDO MySQL
- **Permissões**: Escrita no diretório da aplicação
- **Servidor Web**: Apache com suporte a .htaccess (recomendado)

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
prodcontrol/                  # 📦 Sistema de Controle de Produção
├── api/                      # 🔌 Módulos da API
│   ├── pedidos.php          # 📦 Gestão de Pedidos
│   ├── itens.php            # 🏷️ Gestão de Itens  
│   ├── processos.php        # ⚙️ Gestão de Processos
│   ├── receitas.php         # 📋 Receitas (Item-Processos)
│   ├── acompanhamento.php   # 📊 Acompanhamento e Status
│   └── .htaccess            # 🔒 Proteção de Acesso
├── logs/                     # 📝 Logs do Sistema
│   └── sistema.log
├── uploads/                  # 📁 Arquivos Enviados
├── .gitignore                # 🚫 Arquivos Ignorados pelo Git
├── adm.html                  # 👨‍💼 Painel Administrativo
├── api.php                   # 🌐 Router Principal da API
├── config.php                # ⚙️ Configuração Principal
├── index.html                # 🏠 Página Inicial
├── README.md                 # 📖 Documentação
├── script.js                 # 🖥️ JavaScript Principal
├── setup.php                 # 🛠️ Instalador do Sistema
└── style.css                 # 🎨 Estilos Visuais
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

### Módulo Pedidos
```php
GET  api.php?action=get_pedidos
POST api.php?action=add_pedido
PUT  api.php?action=update_pedido&id=X
DELETE api.php?action=delete_pedido&id=X
```

### Módulo Itens
```php
GET  api.php?action=get_itens
POST api.php?action=add_item
DELETE api.php?action=delete_item&id=X
```

### Módulo Processos  
```php
GET  api.php?action=get_processos
POST api.php?action=add_processo
PUT  api.php?action=update_processo&id=X
DELETE api.php?action=delete_processo&id=X
POST api.php?action=corrigir_ordem_processos
```

### Módulo Receitas (Item-Processos)
```php
GET  api.php?action=get_item_processos&item_id=X
POST api.php?action=add_item_processo
DELETE api.php?action=delete_item_processo&id=X
```

### Módulo Acompanhamento
```php
GET  api.php?action=get_pedido_detalhado&pedido_id=X
POST api.php?action=update_processo_status
POST api.php?action=add_item_to_pedido
DELETE api.php?action=remove_item_from_pedido&id=X
```

## 🔧 Arquitetura da API

### Router Principal (`api.php`)
- **Ponto de entrada único** para todas as requisições
- **Roteamento inteligente** baseado na ação solicitada
- **Tratamento de erros centralizado**
- **Headers de segurança** configurados

### Módulos Especializados
Cada módulo é responsável por uma área específica:

- **`pedidos.php`** - CRUD completo de pedidos
- **`itens.php`** - Gestão de itens e produtos
- **`processos.php`** - Controle de processos e ordem
- **`receitas.php`** - Receitas (quais processos cada item usa)
- **`acompanhamento.php`** - Status, progresso e relatórios

### Vantagens da Modularização
- ✅ **Código organizado** - Cada arquivo com responsabilidade única
- ✅ **Fácil manutenção** - Alterações isoladas por módulo
- ✅ **Desenvolvimento ágil** - Múltiplos desenvolvedores podem trabalhar simultaneamente
- ✅ **Debugging simplificado** - Erros localizados rapidamente
- ✅ **Escalabilidade** - Novos módulos podem ser adicionados facilmente

## 🔧 Manutenção

### **Logs**
- `logs/api_errors.log` - Erros da API
- `logs/sistema.log` - Logs gerais
- `logs/api_access.log` - Tentativas de acesso direto aos módulos

### **Estrutura de Desenvolvimento**
```php
// Cada módulo segue este padrão:
<?php
// Verificar se foi chamado corretamente
if (!defined('SISTEMA_VERSAO') || !isset($pdo)) {
    die('Acesso direto não permitido');
}

// Router das ações do módulo
switch ($action) {
    case 'acao_1':
        funcaoAcao1($pdo);
        break;
    // ...
}

// Funções específicas do módulo
function funcaoAcao1($pdo) {
    // Implementação
}
?>
```

### **Funções Úteis**
```php
cleanOldLogs();           // Limpar logs antigos
checkTableIntegrity();    // Verificar integridade
getSystemStats();         // Estatísticas básicas
getSystemInfo();          // Informações do sistema
```

## 🔍 Solução de Problemas

**Erro 403 ao acessar módulos diretamente:**
- ✅ **Normal** - Módulos protegidos por .htaccess
- Use sempre `api.php?action=...` como ponto de entrada

**Erro de conexão:**
- Verifique credenciais no `config.php`
- Confirme se MySQL está rodando

**Erro de permissões:**
- Configure permissões 755 para diretórios
- Configure permissões 644 para arquivos
- Diretório `api/` deve ter permissões corretas

**Processos com ordem duplicada:**
- Use "Gerenciar Processos → Verificar Ordem"
- Sistema corrige automaticamente

**Erro JSON inválido:**
- Verifique extensão PDO MySQL no PHP
- Confirme encoding UTF-8 dos arquivos

## 🔒 Segurança

### **Proteção dos Módulos:**
- 🔒 **Acesso direto bloqueado** via .htaccess
- 🔒 **Verificação de contexto** em cada módulo
- 🔒 **Headers de segurança** configurados
- 🔒 **Log de tentativas** de acesso não autorizado

### **Após Instalação:**
1. **Remova** o `setup.php`
2. **Configure** backup automático
3. **Monitore** os logs regularmente
4. **Verifique** permissões do diretório `api/`

### **Arquivos Sensíveis:**
- `config.php` - **NUNCA** commitar no Git
- `api/` - **Diretório protegido** por .htaccess
- `logs/` - Contém informações do sistema
- `.installed` - Marca sistema instalado

## 📊 Compatibilidade Testada

- ✅ **PHP**: 7.0, 7.1, 7.2, 7.3, 7.4, 8.0+
- ✅ **MySQL**: 5.0, 5.1, 5.5, 5.6, 5.7, 8.0+
- ✅ **MariaDB**: 10.0+
- ✅ **Servidores**: Apache (com .htaccess), Nginx (com configuração manual)

## 📝 Changelog v5.2

### ✨ **Adicionado**
- API modularizada em 5 módulos especializados
- Router centralizado com roteamento inteligente
- Proteção de acesso direto aos módulos via .htaccess
- Headers de segurança aprimorados
- Log de tentativas de acesso direto
- Função `str_starts_with()` para compatibilidade com PHP < 8.0
- Documentação detalhada da arquitetura modular

### 🔧 **Modificado**
- `api.php` transformado em router principal
- Código reorganizado em módulos especializados:
  - `api/pedidos.php` - Gestão de pedidos
  - `api/itens.php` - Gestão de itens
  - `api/processos.php` - Gestão de processos
  - `api/receitas.php` - Receitas (item-processos)
  - `api/acompanhamento.php` - Acompanhamento e status
- `config.php` atualizado para versão 5.2
- Estrutura de diretórios documentada

### 🛡️ **Segurança**
- Diretório `api/` protegido contra acesso direto
- Verificação de contexto em todos os módulos
- Headers de segurança configurados
- Monitoramento de acesso não autorizado

### 🎯 **Benefícios**
- **Manutenção simplificada** - Código organizado por funcionalidade
- **Desenvolvimento ágil** - Módulos independentes
- **Debugging facilitado** - Erros localizados rapidamente
- **Escalabilidade** - Estrutura preparada para crescimento
- **Segurança aprimorada** - Proteção multicamada

## 📝 Changelog v5.1

### 🐛 **Corrigido**
- Parse error crítico no `setup.php` linha 536
- Linhas duplicadas que causavam conflito de sintaxe
- Encoding de caracteres em alguns comentários
- Compatibilidade com interpretadores PHP mais restritivos

### 🔧 **Modificado**
- Versão atualizada para 5.1 em todos os arquivos
- Mensagens do instalador atualizadas
- Documentação corrigida e atualizada

### ✨ **Melhorado**
- Estabilidade do processo de instalação
- Mensagens de erro mais claras no instalador
- Compatibilidade com mais ambientes de hospedagem

---

**Sistema de Controle de Produção v5.2**  
*Modular, eficiente e escalável*