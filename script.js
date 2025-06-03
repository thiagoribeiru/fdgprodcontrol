// script.js - Sistema de Controle de Produção - Versão Corrigida

// Variáveis globais
let pedidoItens = [];
let itensDisponiveis = [];
let processosDisponiveis = [];

// Configuração da API
const API_BASE_URL = 'api.php';

// Inicialização da página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sistema iniciado');
    inicializarSistema();
});

async function inicializarSistema() {
    try {
        // Verificar se a API está funcionando
        await testarAPI();
        
        // Carregar dados iniciais se estivermos na página de administração
        if (document.getElementById('pedidosTableBody')) {
            await carregarPedidos();
        }
        
        // Configurar eventos
        configurarEventos();
        
        // Definir data de hoje como padrão
        const hoje = new Date().toISOString().split('T')[0];
        const dataEntrada = document.getElementById('dataEntrada');
        if (dataEntrada) {
            dataEntrada.value = hoje;
        }
        
        console.log('Sistema carregado com sucesso');
        
    } catch (error) {
        console.error('Erro ao inicializar sistema:', error);
        mostrarMensagem('Erro ao inicializar o sistema. Verifique a conexão.', 'error');
    }
}

// Testar se a API está funcionando
async function testarAPI() {
    try {
        const data = await apiRequest(`${API_BASE_URL}?action=test`);
        if (!data || data.error) {
            throw new Error('API não está respondendo');
        }
        console.log('API funcionando:', data);
    } catch (error) {
        console.error('Erro na API:', error);
        // Não bloquear a inicialização se a API falhar
    }
}

// Configurar eventos dos formulários
function configurarEventos() {
    // Formulário de adicionar pedido
    const formPedido = document.getElementById('addPedidoForm');
    if (formPedido) {
        formPedido.addEventListener('submit', function(e) {
            e.preventDefault();
            salvarPedido();
        });
    }
    
    // Formulário de adicionar item
    const formItem = document.getElementById('addItemForm');
    if (formItem) {
        formItem.addEventListener('submit', function(e) {
            e.preventDefault();
            salvarItem();
        });
    }
    
    // Formulário de adicionar item ao pedido
    const formItemPedido = document.getElementById('addItemToPedidoForm');
    if (formItemPedido) {
        formItemPedido.addEventListener('submit', function(e) {
            e.preventDefault();
            adicionarItemAoPedido();
        });
    }
    
    // Formulário de adicionar processo ao item
    const formItemProcesso = document.getElementById('addItemProcessoForm');
    if (formItemProcesso) {
        formItemProcesso.addEventListener('submit', function(e) {
            e.preventDefault();
            adicionarProcessoAoItem();
        });
    }
    
    // Formulário de adicionar processo
    const formProcesso = document.getElementById('addProcessoForm');
    if (formProcesso) {
        formProcesso.addEventListener('submit', function(e) {
            e.preventDefault();
            salvarProcesso();
        });
    }
    
    // Formulário de editar processo
    const formEditProcesso = document.getElementById('editProcessoForm');
    if (formEditProcesso) {
        formEditProcesso.addEventListener('submit', function(e) {
            e.preventDefault();
            atualizarProcesso();
        });
    }
}

// === FUNÇÕES DE MODAL ===

function openAddPedidoModal() {
    const modal = document.getElementById('addPedidoModal');
    if (modal) {
        modal.style.display = 'block';
        limparFormularioPedido();
    }
}

function closeAddPedidoModal() {
    const modal = document.getElementById('addPedidoModal');
    if (modal) {
        modal.style.display = 'none';
        pedidoItens = [];
        atualizarTabelaItensPedido();
    }
}

function openSelectItemModal() {
    const modal = document.getElementById('selectItemModal');
    if (modal) {
        modal.style.display = 'block';
        carregarItensParaSelecao();
    }
}

function closeSelectItemModal() {
    const modal = document.getElementById('selectItemModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openAddItemToPedidoModal(itemId, itemNome) {
    const modal = document.getElementById('addItemToPedidoModal');
    if (modal) {
        document.getElementById('selectedItemId').value = itemId;
        document.getElementById('selectedItemName').textContent = `Item: ${itemNome}`;
        modal.style.display = 'block';
    }
}

function closeAddItemToPedidoModal() {
    const modal = document.getElementById('addItemToPedidoModal');
    if (modal) {
        modal.style.display = 'none';
        const form = document.getElementById('addItemToPedidoForm');
        if (form) form.reset();
    }
}

function openItensModal() {
    const modal = document.getElementById('itensModal');
    if (modal) {
        modal.style.display = 'block';
        carregarItens();
        carregarProcessos();
    }
}

function closeItensModal() {
    const modal = document.getElementById('itensModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openProcessosModal() {
    const modal = document.getElementById('processosModal');
    if (modal) {
        modal.style.display = 'block';
        carregarProcessosList();
    }
}

function closeProcessosModal() {
    const modal = document.getElementById('processosModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openEditProcessoModal(processoId, nome, descricao, ordem) {
    const modal = document.getElementById('editProcessoModal');
    if (modal) {
        document.getElementById('editProcessoId').value = processoId;
        document.getElementById('editProcessoNome').value = nome;
        document.getElementById('editProcessoDescricao').value = descricao || '';
        document.getElementById('editProcessoOrdem').value = ordem;
        modal.style.display = 'block';
    }
}

function closeEditProcessoModal() {
    const modal = document.getElementById('editProcessoModal');
    if (modal) {
        modal.style.display = 'none';
        const form = document.getElementById('editProcessoForm');
        if (form) form.reset();
    }
}

function openItemProcessosModal(itemId, itemNome) {
    const modal = document.getElementById('itemProcessosModal');
    if (modal) {
        document.getElementById('currentItemId').value = itemId;
        document.getElementById('itemProcessosTitle').textContent = `Processos do Item: ${itemNome}`;
        modal.style.display = 'block';
        carregarProcessosDoItem(itemId);
    }
}

function closeItemProcessosModal() {
    const modal = document.getElementById('itemProcessosModal');
    if (modal) {
        modal.style.display = 'none';
        const form = document.getElementById('addItemProcessoForm');
        if (form) form.reset();
    }
}

// === FUNÇÕES DE TABS ===

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
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    // Ativar botão correspondente
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

// === FUNÇÕES DE API ===

async function apiRequest(url, options = {}) {
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
            const data = JSON.parse(responseText);
            return data;
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
        mostrarMensagem(error.message || 'Erro de comunicação com o servidor', 'error');
        return null;
    }
}

// === CARREGAR DADOS ===

async function carregarPedidos() {
    console.log('Carregando pedidos...');
    mostrarLoading('pedidosTableBody');
    
    const data = await apiRequest(`${API_BASE_URL}?action=get_pedidos`);
    
    if (data && Array.isArray(data)) {
        const tbody = document.getElementById('pedidosTableBody');
        if (tbody) {
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #666;">Nenhum pedido encontrado</td></tr>';
            } else {
                data.forEach(pedido => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatarData(pedido.data_entrada)}</td>
                        <td>${formatarData(pedido.data_entrega)}</td>
                        <td><strong>${pedido.codigo_pedido}</strong></td>
                        <td>${pedido.cliente}</td>
                        <td>
                            <span class="status-${pedido.processo_atual}" 
                                  onclick="alterarProcessoPedido(${pedido.id}, '${pedido.processo_atual}')" 
                                  title="Clique para avançar processo">
                                ${capitalizeFirst(pedido.processo_atual)}
                            </span>
                        </td>
                        <td>${pedido.total_itens} item(s)</td>
                        <td>
                            <button class="btn-edit" onclick="verItensPedido(${pedido.id})" title="Ver itens">👁️</button>
                            <button class="btn-delete" onclick="excluirPedido(${pedido.id})" title="Excluir">🗑️</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }
        console.log(`${data.length} pedidos carregados`);
    }
}

async function carregarItens() {
    console.log('Carregando itens...');
    const data = await apiRequest(`${API_BASE_URL}?action=get_itens`);
    
    if (data && Array.isArray(data)) {
        itensDisponiveis = data;
        const tbody = document.getElementById('itensTableBody');
        if (tbody) {
            tbody.innerHTML = '';
            
            data.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${item.nome}</strong></td>
                    <td>${item.descricao || '-'}</td>
                    <td>${item.total_processos} processo(s)</td>
                    <td>
                        <button class="btn-edit" onclick="openItemProcessosModal(${item.id}, '${escapeString(item.nome)}')" title="Configurar processos">⚙️</button>
                        <button class="btn-delete" onclick="excluirItem(${item.id})" title="Excluir item">🗑️</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        console.log(`${data.length} itens carregados`);
    }
}

async function carregarItensParaSelecao() {
    if (itensDisponiveis.length === 0) {
        await carregarItens();
    }
    
    const tbody = document.getElementById('selectItemBody');
    if (tbody) {
        tbody.innerHTML = '';
        
        itensDisponiveis.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${item.nome}</strong></td>
                <td>${item.descricao || '-'}</td>
                <td>
                    <button class="btn-save" onclick="openAddItemToPedidoModal(${item.id}, '${escapeString(item.nome)}')">Selecionar</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

async function carregarProcessos() {
    console.log('Carregando processos...');
    const data = await apiRequest(`${API_BASE_URL}?action=get_processos`);
    
    if (data && Array.isArray(data)) {
        processosDisponiveis = data;
        const select = document.getElementById('processoSelect');
        if (select) {
            select.innerHTML = '<option value="">Selecione o processo</option>';
            
            data.forEach(processo => {
                const option = document.createElement('option');
                option.value = processo.id;
                option.textContent = processo.nome;
                select.appendChild(option);
            });
        }
        console.log(`${data.length} processos carregados`);
    }
}

async function carregarProcessosList() {
    console.log('Carregando lista de processos...');
    const data = await apiRequest(`${API_BASE_URL}?action=get_processos`);
    
    if (data && Array.isArray(data)) {
        const tbody = document.getElementById('processosTableBody');
        if (tbody) {
            tbody.innerHTML = '';
            
            data.forEach(processo => {
                const processosEssenciais = ['corte', 'personalização', 'produção', 'expedição'];
                const isEssencial = processosEssenciais.includes(processo.nome.toLowerCase());
                const tipoLabel = isEssencial ? 
                    '<span style="color: #4CAF50; font-weight: bold;">Sistema</span>' : 
                    '<span style="color: #666;">Personalizado</span>';
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${processo.ordem}</strong></td>
                    <td>${processo.nome}</td>
                    <td>${processo.descricao || '-'}</td>
                    <td>${processo.total_usos} uso(s)</td>
                    <td>${tipoLabel}</td>
                    <td>
                        <button class="btn-edit" onclick="openEditProcessoModal(${processo.id}, '${escapeString(processo.nome)}', '${escapeString(processo.descricao || '')}', ${processo.ordem})" title="Editar processo">✏️</button>
                        ${!isEssencial ? `<button class="btn-delete" onclick="excluirProcesso(${processo.id})" title="Excluir processo">🗑️</button>` : '<span style="color: #ccc;" title="Processos do sistema não podem ser excluídos">🔒</span>'}
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        console.log(`${data.length} processos carregados`);
    }
}

async function carregarProcessosDoItem(itemId) {
    console.log('Carregando processos do item:', itemId);
    const data = await apiRequest(`${API_BASE_URL}?action=get_item_processos&item_id=${itemId}`);
    
    if (data && Array.isArray(data)) {
        const tbody = document.getElementById('itemProcessosBody');
        if (tbody) {
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">Nenhum processo configurado</td></tr>';
            } else {
                data.forEach(processo => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${processo.ordem}</strong></td>
                        <td>${processo.processo_nome}</td>
                        <td>${processo.observacoes || '-'}</td>
                        <td>
                            <button class="btn-delete" onclick="removerProcessoDoItem(${processo.id})" title="Remover processo">🗑️</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }
        console.log(`${data.length} processos do item carregados`);
    }
}

// === SALVAR DADOS ===

async function salvarPedido() {
    console.log('Salvando pedido...');
    const formData = new FormData(document.getElementById('addPedidoForm'));
    
    const pedidoData = {
        data_entrada: formData.get('dataEntrada'),
        data_entrega: formData.get('dataEntrega'),
        codigo_pedido: formData.get('codigoPedido'),
        cliente: formData.get('cliente'),
        processo_atual: formData.get('processoAtual'),
        itens: pedidoItens
    };
    
    console.log('Dados do pedido:', pedidoData);
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=add_pedido`, {
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
    console.log('Salvando item...');
    const formData = new FormData(document.getElementById('addItemForm'));
    
    const itemData = {
        nome: formData.get('nome'),
        descricao: formData.get('descricao')
    };
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=add_item`, {
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

async function salvarProcesso() {
    console.log('Salvando processo...');
    const formData = new FormData(document.getElementById('addProcessoForm'));
    
    const processoData = {
        nome: formData.get('nome'),
        descricao: formData.get('descricao'),
        ordem: formData.get('ordem') ? parseInt(formData.get('ordem')) : null
    };
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=add_processo`, {
        method: 'POST',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Processo criado com sucesso!', 'success');
        document.getElementById('addProcessoForm').reset();
        carregarProcessosList();
        carregarProcessos(); // Atualizar select também
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao criar processo', 'error');
    }
}

async function atualizarProcesso() {
    console.log('Atualizando processo...');
    const processoId = document.getElementById('editProcessoId').value;
    const formData = new FormData(document.getElementById('editProcessoForm'));
    
    const processoData = {
        nome: formData.get('nome'),
        descricao: formData.get('descricao'),
        ordem: parseInt(formData.get('ordem'))
    };
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=update_processo&id=${processoId}`, {
        method: 'PUT',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Processo atualizado com sucesso!', 'success');
        closeEditProcessoModal();
        carregarProcessosList();
        carregarProcessos(); // Atualizar select também
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao atualizar processo', 'error');
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
    
    console.log('Item adicionado ao pedido:', novoItem);
}

async function adicionarProcessoAoItem() {
    console.log('Adicionando processo ao item...');
    const formData = new FormData(document.getElementById('addItemProcessoForm'));
    const itemId = document.getElementById('currentItemId').value;
    
    const processoData = {
        item_id: parseInt(itemId),
        processo_id: parseInt(formData.get('processo_id')),
        ordem: parseInt(formData.get('ordem')),
        observacoes: formData.get('observacoes')
    };
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=add_item_processo`, {
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

// === EXCLUSÕES ===

async function excluirPedido(pedidoId) {
    if (!confirm('Tem certeza que deseja excluir este pedido? Todos os itens associados também serão removidos.')) {
        return;
    }
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=delete_pedido&id=${pedidoId}`, {
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
    if (!confirm('Tem certeza que deseja excluir este item?\n\nAtenção: Itens que estão sendo usados em pedidos não podem ser excluídos.\nTodos os processos (receitas) associados também serão removidos.')) {
        return;
    }
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=delete_item&id=${itemId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        let mensagem = 'Item excluído com sucesso!';
        if (resultado.processos_removidos > 0) {
            mensagem += `\n${resultado.processos_removidos} processo(s) da receita também foram removidos.`;
        }
        mostrarMensagem(mensagem, 'success');
        carregarItens();
    } else if (resultado && resultado.error) {
        let mensagem = resultado.error;
        if (resultado.details) {
            mensagem += `\n\n${resultado.details}`;
        }
        if (resultado.count) {
            mensagem += `\n\nTotal de usos: ${resultado.count}`;
        }
        mostrarMensagem(mensagem, 'error');
    } else {
        mostrarMensagem('Erro ao excluir item', 'error');
    }
}

async function excluirProcesso(processoId) {
    if (!confirm('Tem certeza que deseja excluir este processo?\n\nAtenção: Processos que estão sendo usados em receitas de itens ou pedidos não podem ser excluídos.')) {
        return;
    }
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=delete_processo&id=${processoId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Processo excluído com sucesso!', 'success');
        carregarProcessosList();
        carregarProcessos(); // Atualizar select também
    } else if (resultado && resultado.error) {
        let mensagem = resultado.error;
        if (resultado.details) {
            mensagem += `\n\n${resultado.details}`;
        }
        if (resultado.count) {
            mensagem += `\n\nTotal de usos: ${resultado.count}`;
        }
        mostrarMensagem(mensagem, 'error');
    } else {
        mostrarMensagem('Erro ao excluir processo', 'error');
    }
}

// === FUNÇÕES AUXILIARES ===

function atualizarTabelaItensPedido() {
    const tbody = document.getElementById('pedidoItensBody');
    if (tbody) {
        tbody.innerHTML = '';
        
        if (pedidoItens.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">Nenhum item adicionado</td></tr>';
        } else {
            pedidoItens.forEach((item, index) => {
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
}

function removerItemDoPedido(index) {
    pedidoItens.splice(index, 1);
    atualizarTabelaItensPedido();
    console.log('Item removido do pedido, restam:', pedidoItens.length);
}

async function removerProcessoDoItem(processoId) {
    if (!confirm('Tem certeza que deseja remover este processo?')) {
        return;
    }
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=delete_item_processo&id=${processoId}`, {
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

async function verItensPedido(pedidoId) {
    console.log('Visualizando itens do pedido:', pedidoId);
    const data = await apiRequest(`${API_BASE_URL}?action=get_pedido_itens&pedido_id=${pedidoId}`);
    
    if (data && Array.isArray(data)) {
        const modalHtml = `
            <div id="viewItensModal" class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Itens do Pedido</h2>
                        <span class="close" onclick="closeViewItensModal()">&times;</span>
                    </div>
                    <div style="padding: 25px;">
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
                                ${data.length > 0 ? data.map(item => `
                                    <tr>
                                        <td><strong>${item.item_nome}</strong></td>
                                        <td>${item.item_descricao || '-'}</td>
                                        <td>${item.quantidade}</td>
                                        <td>${item.observacoes || '-'}</td>
                                    </tr>
                                `).join('') : '<tr><td colspan="4" style="text-align: center; padding: 20px;">Nenhum item encontrado</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
}

function closeViewItensModal() {
    const modal = document.getElementById('viewItensModal');
    if (modal) {
        modal.remove();
    }
}

async function alterarProcessoPedido(pedidoId, processoAtual) {
    const processos = ['corte', 'personalização', 'produção', 'expedição'];
    const processoAtualIndex = processos.indexOf(processoAtual);
    
    if (processoAtualIndex < processos.length - 1) {
        const novoProcesso = processos[processoAtualIndex + 1];
        
        if (confirm(`Avançar processo de "${capitalizeFirst(processoAtual)}" para "${capitalizeFirst(novoProcesso)}"?`)) {
            const resultado = await apiRequest(`${API_BASE_URL}?action=update_processo_pedido&id=${pedidoId}`, {
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
    const form = document.getElementById('addPedidoForm');
    if (form) {
        form.reset();
        pedidoItens = [];
        atualizarTabelaItensPedido();
        
        // Definir data de hoje como padrão
        const hoje = new Date().toISOString().split('T')[0];
        const dataEntrada = document.getElementById('dataEntrada');
        if (dataEntrada) {
            dataEntrada.value = hoje;
        }
    }
}

function mostrarLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<tr><td colspan="100%" class="loading">⏳ Carregando...</td></tr>';
    }
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
    if (mensagem.includes('\n')) {
        div.className += ' detailed';
    }
    div.textContent = mensagem;
    
    // Inserir no topo da página
    document.body.insertBefore(div, document.body.firstChild);
    
    // Remover após 5 segundos
    setTimeout(() => {
        if (div.parentNode) {
            div.remove();
        }
    }, 5000);
    
    console.log(`Mensagem ${tipo}:`, mensagem);
}

function formatarData(dataString) {
    if (!dataString) return '-';
    const data = new Date(dataString + 'T00:00:00');
    return data.toLocaleDateString('pt-BR');
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeString(str) {
    if (!str) return '';
    return str.replace(/'/g, '\\\'').replace(/"/g, '&quot;');
}

// Event listeners para fechar modais clicando fora
window.onclick = function(event) {
    const modais = document.querySelectorAll('.modal');
    modais.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
};

// Adicionar suporte para ESC fechar modais
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modaisAbertos = document.querySelectorAll('.modal[style*="block"]');
        modaisAbertos.forEach(modal => {
            modal.style.display = 'none';
        });
    }
});

console.log('Script carregado - Sistema de Controle de Produção v2.0');