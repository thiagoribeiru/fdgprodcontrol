// script.js - Funcionalidades principais do sistema

// Variáveis globais
let pedidoItens = [];
let itensDisponiveis = [];
let processosDisponiveis = [];

// Inicialização da página
document.addEventListener('DOMContentLoaded', function() {
    carregarPedidos();
    configurarEventos();
    
    // Definir data de hoje como padrão
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('dataEntrada').value = hoje;
});

// Configurar eventos dos formulários
function configurarEventos() {
    // Formulário de adicionar pedido
    document.getElementById('addPedidoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        salvarPedido();
    });
    
    // Formulário de adicionar item
    document.getElementById('addItemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        salvarItem();
    });
    
    // Formulário de adicionar item ao pedido
    document.getElementById('addItemToPedidoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        adicionarItemAoPedido();
    });
    
    // Formulário de adicionar processo ao item
    document.getElementById('addItemProcessoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        adicionarProcessoAoItem();
    });
}

// Funções de Modal
function openAddPedidoModal() {
    document.getElementById('addPedidoModal').style.display = 'block';
    limparFormularioPedido();
}

function closeAddPedidoModal() {
    document.getElementById('addPedidoModal').style.display = 'none';
    pedidoItens = [];
    atualizarTabelaItensPedido();
}

function openSelectItemModal() {
    document.getElementById('selectItemModal').style.display = 'block';
    carregarItensParaSelecao();
}

function closeSelectItemModal() {
    document.getElementById('selectItemModal').style.display = 'none';
}

function openAddItemToPedidoModal(itemId, itemNome) {
    document.getElementById('selectedItemId').value = itemId;
    document.getElementById('selectedItemName').textContent = `Item: ${itemNome}`;
    document.getElementById('addItemToPedidoModal').style.display = 'block';
}

function closeAddItemToPedidoModal() {
    document.getElementById('addItemToPedidoModal').style.display = 'none';
    document.getElementById('addItemToPedidoForm').reset();
}

function openItensModal() {
    document.getElementById('itensModal').style.display = 'block';
    carregarItens();
    carregarProcessos();
}

function closeItensModal() {
    document.getElementById('itensModal').style.display = 'none';
}

function openItemProcessosModal(itemId, itemNome) {
    document.getElementById('currentItemId').value = itemId;
    document.getElementById('itemProcessosTitle').textContent = `Processos do Item: ${itemNome}`;
    document.getElementById('itemProcessosModal').style.display = 'block';
    carregarProcessosDoItem(itemId);
}

function closeItemProcessosModal() {
    document.getElementById('itemProcessosModal').style.display = 'none';
    document.getElementById('addItemProcessoForm').reset();
}

// Funções de Tabs
function showTab(tabName) {
    // Esconder todas as tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remover classe active de todos os botões
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Mostrar tab selecionada
    document.getElementById(tabName).classList.add('active');
    
    // Ativar botão correspondente
    event.target.classList.add('active');
}

// Funções de API com melhor tratamento de erros
async function apiRequest(url, options = {}) {
    try {
        console.log('Fazendo requisição para:', url);
        console.log('Opções:', options);
        
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        console.log('Status da resposta:', response.status);
        
        // Verificar se a resposta é válida
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Obter o texto da resposta primeiro
        const responseText = await response.text();
        console.log('Resposta bruta:', responseText);
        
        // Verificar se é JSON válido
        if (!responseText.trim()) {
            throw new Error('Resposta vazia do servidor');
        }
        
        try {
            const data = JSON.parse(responseText);
            console.log('Dados parseados:', data);
            return data;
        } catch (parseError) {
            console.error('Erro ao parsear JSON:', parseError);
            console.error('Resposta recebida:', responseText);
            
            // Se não é JSON, pode ser uma página de erro do PHP
            if (responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                throw new Error('Servidor retornou HTML em vez de JSON. Verifique os logs do PHP.');
            }
            
            throw new Error('Resposta inválida do servidor');
        }
        
    } catch (error) {
        console.error('Erro na API:', error);
        
        // Mostrar erro mais específico
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            mostrarMensagem('Erro de conexão. Verifique se o servidor está rodando.', 'error');
        } else if (error.message.includes('HTML')) {
            mostrarMensagem('Erro no servidor PHP. Verifique a configuração do banco de dados.', 'error');
        } else {
            mostrarMensagem(error.message || 'Erro de comunicação com o servidor', 'error');
        }
        
        return null;
    }
}

// Carregar dados
async function carregarPedidos() {
    mostrarLoading('pedidosTableBody');
    
    const data = await apiRequest('api.php?action=get_pedidos');
    
    if (data) {
        const tbody = document.getElementById('pedidosTableBody');
        tbody.innerHTML = '';
        
        data.forEach(pedido => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${formatarData(pedido.data_entrada)}</td>
                <td>${formatarData(pedido.data_entrega)}</td>
                <td>${pedido.codigo_pedido}</td>
                <td>${pedido.cliente}</td>
                <td>
                    <span class="status-${pedido.processo_atual}" onclick="alterarProcessoPedido(${pedido.id}, '${pedido.processo_atual}')" style="cursor: pointer;" title="Clique para avançar processo">
                        ${capitalizeFirst(pedido.processo_atual)}
                    </span>
                </td>
                <td>${pedido.total_itens} item(s)</td>
                <td>
                    <button class="btn-edit" onclick="verItensPedido(${pedido.id})">Ver Itens</button>
                    <button class="btn-delete" onclick="excluirPedido(${pedido.id})">Excluir</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

async function carregarItens() {
    const data = await apiRequest('api.php?action=get_itens');
    
    if (data) {
        itensDisponiveis = data;
        const tbody = document.getElementById('itensTableBody');
        tbody.innerHTML = '';
        
        data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.nome}</td>
                <td>${item.descricao || '-'}</td>
                <td>
                    <button class="btn-edit" onclick="openItemProcessosModal(${item.id}, '${item.nome}')">Configurar</button>
                </td>
                <td>
                    <button class="btn-delete" onclick="excluirItem(${item.id})">Excluir</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

async function carregarItensParaSelecao() {
    if (itensDisponiveis.length === 0) {
        await carregarItens();
    }
    
    const tbody = document.getElementById('selectItemBody');
    tbody.innerHTML = '';
    
    itensDisponiveis.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.nome}</td>
            <td>${item.descricao || '-'}</td>
            <td>
                <button class="btn-save" onclick="openAddItemToPedidoModal(${item.id}, '${item.nome}')">Selecionar</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

async function carregarProcessos() {
    const data = await apiRequest('api.php?action=get_processos');
    
    if (data) {
        processosDisponiveis = data;
        const select = document.getElementById('processoSelect');
        select.innerHTML = '<option value="">Selecione o processo</option>';
        
        data.forEach(processo => {
            const option = document.createElement('option');
            option.value = processo.id;
            option.textContent = processo.nome;
            select.appendChild(option);
        });
    }
}

async function carregarProcessosDoItem(itemId) {
    const data = await apiRequest(`api.php?action=get_item_processos&item_id=${itemId}`);
    
    if (data) {
        const tbody = document.getElementById('itemProcessosBody');
        tbody.innerHTML = '';
        
        data.forEach(processo => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${processo.ordem}</td>
                <td>${processo.processo_nome}</td>
                <td>${processo.observacoes || '-'}</td>
                <td>
                    <button class="btn-delete" onclick="removerProcessoDoItem(${processo.id})">Remover</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

// Salvar dados
async function salvarPedido() {
    const formData = new FormData(document.getElementById('addPedidoForm'));
    
    const pedidoData = {
        data_entrada: formData.get('dataEntrada'),
        data_entrega: formData.get('dataEntrega'),
        codigo_pedido: formData.get('codigoPedido'),
        cliente: formData.get('cliente'),
        processo_atual: formData.get('processoAtual'),
        itens: pedidoItens
    };
    
    const resultado = await apiRequest('api.php?action=add_pedido', {
        method: 'POST',
        body: JSON.stringify(pedidoData)
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Pedido salvo com sucesso!', 'success');
        closeAddPedidoModal();
        carregarPedidos();
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao salvar pedido', 'error');
    }
}

async function salvarItem() {
    const formData = new FormData(document.getElementById('addItemForm'));
    
    const itemData = {
        nome: formData.get('nome'),
        descricao: formData.get('descricao')
    };
    
    const resultado = await apiRequest('api.php?action=add_item', {
        method: 'POST',
        body: JSON.stringify(itemData)
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Item salvo com sucesso!', 'success');
        document.getElementById('addItemForm').reset();
        carregarItens();
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao salvar item', 'error');
    }
}

function adicionarItemAoPedido() {
    const formData = new FormData(document.getElementById('addItemToPedidoForm'));
    const itemId = document.getElementById('selectedItemId').value;
    const itemNome = document.getElementById('selectedItemName').textContent.replace('Item: ', '');
    
    const novoItem = {
        item_id: parseInt(itemId),
        item_nome: itemNome,
        quantidade: parseInt(formData.get('quantidade')),
        observacoes: formData.get('observacoes')
    };
    
    // Verificar se o item já foi adicionado
    const itemExistente = pedidoItens.find(item => item.item_id === novoItem.item_id);
    if (itemExistente) {
        mostrarMensagem('Este item já foi adicionado ao pedido', 'error');
        return;
    }
    
    pedidoItens.push(novoItem);
    atualizarTabelaItensPedido();
    closeAddItemToPedidoModal();
    closeSelectItemModal();
}

async function adicionarProcessoAoItem() {
    const formData = new FormData(document.getElementById('addItemProcessoForm'));
    const itemId = document.getElementById('currentItemId').value;
    
    const processoData = {
        item_id: parseInt(itemId),
        processo_id: parseInt(formData.get('processo_id')),
        ordem: parseInt(formData.get('ordem')),
        observacoes: formData.get('observacoes')
    };
    
    const resultado = await apiRequest('api.php?action=add_item_processo', {
        method: 'POST',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Processo adicionado com sucesso!', 'success');
        document.getElementById('addItemProcessoForm').reset();
        carregarProcessosDoItem(itemId);
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao adicionar processo', 'error');
    }
}

// Funções auxiliares
function atualizarTabelaItensPedido() {
    const tbody = document.getElementById('pedidoItensBody');
    tbody.innerHTML = '';
    
    pedidoItens.forEach((item, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.item_nome}</td>
            <td>${item.quantidade}</td>
            <td>${item.observacoes || '-'}</td>
            <td>
                <button class="btn-delete" onclick="removerItemDoPedido(${index})">Remover</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function removerItemDoPedido(index) {
    pedidoItens.splice(index, 1);
    atualizarTabelaItensPedido();
}

async function removerProcessoDoItem(processoId) {
    if (!confirm('Tem certeza que deseja remover este processo?')) {
        return;
    }
    
    const resultado = await apiRequest(`api.php?action=delete_item_processo&id=${processoId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Processo removido com sucesso!', 'success');
        const itemId = document.getElementById('currentItemId').value;
        carregarProcessosDoItem(itemId);
    } else {
        mostrarMensagem('Erro ao remover processo', 'error');
    }
}

async function excluirPedido(pedidoId) {
    if (!confirm('Tem certeza que deseja excluir este pedido? Todos os itens associados também serão removidos.')) {
        return;
    }
    
    const resultado = await apiRequest(`api.php?action=delete_pedido&id=${pedidoId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Pedido excluído com sucesso!', 'success');
        carregarPedidos();
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao excluir pedido', 'error');
    }
}

async function excluirItem(itemId) {
    if (!confirm('Tem certeza que deseja excluir este item? Todos os processos associados também serão removidos.')) {
        return;
    }
    
    const resultado = await apiRequest(`api.php?action=delete_item&id=${itemId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Item excluído com sucesso!', 'success');
        carregarItens();
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao excluir item', 'error');
    }
}

async function verItensPedido(pedidoId) {
    const data = await apiRequest(`api.php?action=get_pedido_itens&pedido_id=${pedidoId}`);
    
    if (data) {
        // Criar modal para mostrar itens do pedido
        const modalHtml = `
            <div id="viewItensModal" class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Itens do Pedido</h2>
                        <span class="close" onclick="closeViewItensModal()">&times;</span>
                    </div>
                    <table class="tablePedidos">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Descrição</th>
                                <th>Quantidade</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>${item.item_nome}</td>
                                    <td>${item.item_descricao || '-'}</td>
                                    <td>${item.quantidade}</td>
                                    <td>${item.observacoes || '-'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    ${data.length === 0 ? '<p style="text-align: center; padding: 20px;">Nenhum item encontrado para este pedido.</p>' : ''}
                </div>
            </div>
        `;
        
        // Inserir modal no body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
}

function closeViewItensModal() {
    const modal = document.getElementById('viewItensModal');
    if (modal) {
        modal.remove();
    }
}

// Função para alterar processo do pedido rapidamente
async function alterarProcessoPedido(pedidoId, processoAtual) {
    const processos = ['corte', 'personalização', 'produção', 'expedição'];
    const processoAtualIndex = processos.indexOf(processoAtual);
    
    if (processoAtualIndex < processos.length - 1) {
        const novoProcesso = processos[processoAtualIndex + 1];
        
        if (confirm(`Avançar processo de "${capitalizeFirst(processoAtual)}" para "${capitalizeFirst(novoProcesso)}"?`)) {
            const resultado = await apiRequest(`api.php?action=update_processo_pedido&id=${pedidoId}`, {
                method: 'PUT',
                body: JSON.stringify({
                    processo_atual: novoProcesso
                })
            });
            
            if (resultado && resultado.success) {
                mostrarMensagem('Processo atualizado com sucesso!', 'success');
                carregarPedidos();
            } else {
                mostrarMensagem(resultado?.error || 'Erro ao atualizar processo', 'error');
            }
        }
    } else {
        mostrarMensagem('Este pedido já está no último processo!', 'error');
    }
}

function limparFormularioPedido() {
    document.getElementById('addPedidoForm').reset();
    pedidoItens = [];
    atualizarTabelaItensPedido();
    
    // Definir data de hoje como padrão
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('dataEntrada').value = hoje;
}

function mostrarLoading(elementId) {
    document.getElementById(elementId).innerHTML = '<tr><td colspan="100%" class="loading">Carregando...</td></tr>';
}

function mostrarMensagem(mensagem, tipo) {
    // Remover mensagens anteriores
    const mensagemAnterior = document.querySelector('.message');
    if (mensagemAnterior) {
        mensagemAnterior.remove();
    }
    
    // Criar nova mensagem
    const div = document.createElement('div');
    div.className = `message ${tipo}`;
    div.textContent = mensagem;
    
    // Inserir no topo da página
    document.body.insertBefore(div, document.body.firstChild);
    
    // Remover após 5 segundos
    setTimeout(() => {
        div.remove();
    }, 5000);
}

function formatarData(dataString) {
    const data = new Date(dataString + 'T00:00:00');
    return data.toLocaleDateString('pt-BR');
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Event listeners para fechar modais clicando fora
window.onclick = function(event) {
    const modais = document.querySelectorAll('.modal');
    modais.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}