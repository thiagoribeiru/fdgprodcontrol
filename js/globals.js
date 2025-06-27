// js/globals.js - Variáveis Globais e Funções Compartilhadas v0.5.4

// === VARIÁVEIS GLOBAIS ===
window.pedidoItens = window.pedidoItens || [];
window.itensDisponiveis = window.itensDisponiveis || [];
window.processosDisponiveis = window.processosDisponiveis || [];

// === CONFIGURAÇÃO DA API ===
window.API_BASE_URL = 'api.php';

// === FUNÇÕES AUXILIARES COMPARTILHADAS ===
window.mostrarMensagem = function(mensagem, tipo) {
    const mensagemAnterior = document.querySelector('.message');
    if (mensagemAnterior) {
        mensagemAnterior.remove();
    }
    
    const div = document.createElement('div');
    div.className = `message ${tipo}`;
    if (mensagem.includes('\n')) {
        div.className += ' detailed';
    }
    div.textContent = mensagem;
    
    document.body.insertBefore(div, document.body.firstChild);
    
    setTimeout(() => {
        if (div.parentNode) {
            div.remove();
        }
    }, 5000);
    
    console.log(`Mensagem ${tipo}:`, mensagem);
};

window.mostrarLoading = function(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<tr><td colspan="100%" class="loading">⏳ Carregando...</td></tr>';
    }
};

// === FUNÇÕES DE FORMATAÇÃO ===
window.formatarData = function(dataString) {
    if (!dataString) return '-';
    const data = new Date(dataString + 'T00:00:00');
    return data.toLocaleDateString('pt-BR');
};

window.formatarDataHora = function(dataString) {
    if (!dataString) return '-';
    const data = new Date(dataString);
    return data.toLocaleString('pt-BR');
};

window.capitalizeFirst = function(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
};

window.escapeString = function(str) {
    if (!str) return '';
    return str.replace(/'/g, '\\\'').replace(/"/g, '&quot;');
};

window.getStatusLabel = function(status) {
    const labels = {
        'aguardando': 'Aguardando',
        'em_andamento': 'Em Andamento',
        'completo': 'Completo'
    };
    return labels[status] || status;
};

window.getStatusIcon = function(status) {
    const icons = {
        'aguardando': '<span class="status-icon waiting">⏳</span>',
        'em_andamento': '<span class="status-icon progress">🔄</span>',
        'completo': '<span class="status-icon complete">✅</span>'
    };
    return icons[status] || icons['aguardando'];
};

// === FUNÇÃO DE API ===
window.apiRequest = async function(url, options = {}) {
    try {
        console.log('Fazendo requisição para:', url);
        
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        
        if (!responseText.trim()) {
            throw new Error('Resposta vazia do servidor');
        }
        
        try {
            return JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao parsear JSON:', parseError);
            console.error('Resposta:', responseText.substring(0, 500));
            
            if (responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                throw new Error('Servidor retornou HTML. Verifique a configuração do PHP.');
            }
            
            throw new Error('Resposta inválida do servidor');
        }
        
    } catch (error) {
        console.error('Erro na API:', error);
        window.mostrarMensagem(error.message || 'Erro de comunicação com o servidor', 'error');
        return null;
    }
};

// === FUNÇÃO DE CONFIGURAÇÃO DE DATA PADRÃO ===
window.configurarDataPadrao = function() {
    const hoje = new Date().toISOString().split('T')[0];
    const dataEntrada = document.getElementById('dataEntrada');
    if (dataEntrada) {
        dataEntrada.value = hoje;
    }
};

// === FUNÇÃO DE ATUALIZAÇÃO DA TABELA DE ITENS ===
window.atualizarTabelaItensPedido = function() {
    const tbody = document.getElementById('pedidoItensBody');
    if (tbody) {
        tbody.innerHTML = '';
        
        if (!window.pedidoItens || window.pedidoItens.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">Nenhum item adicionado</td></tr>';
        } else {
            window.pedidoItens.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${item.item_nome}</strong></td>
                    <td>${item.quantidade}</td>
                    <td>${item.observacoes || '-'}</td>
                    <td>
                        <button class="btn-delete" onclick="removerItemDoPedido(${index})" title="Remover item">🗑️</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    }
};

window.removerItemDoPedido = function(index) {
    if (window.pedidoItens && Array.isArray(window.pedidoItens)) {
        window.pedidoItens.splice(index, 1);
        window.atualizarTabelaItensPedido();
        console.log('Item removido do pedido, restam:', window.pedidoItens.length);
    }
};

// === FUNÇÃO LIMPAR FORMULÁRIO PEDIDO ===
window.limparFormularioPedido = function() {
    const form = document.getElementById('addPedidoForm');
    if (form) {
        form.reset();
        window.pedidoItens = [];
        window.atualizarTabelaItensPedido();
        window.configurarDataPadrao();
    }
};

console.log('Módulo Globals carregado - Variáveis globais e funções compartilhadas v0.5.4');