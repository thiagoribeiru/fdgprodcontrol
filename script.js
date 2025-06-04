// script.js - Sistema de Controle de Produção - Versão Otimizada

// Variáveis globais
let pedidoItens = [];
let itensDisponiveis = [];
let processosDisponiveis = [];

// Configuração da API
const API_BASE_URL = 'api.php';

// ===============================
// INICIALIZAÇÃO DO SISTEMA
// ===============================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Sistema iniciado');
    inicializarSistema();
});

async function inicializarSistema() {
    try {
        await testarAPI();
        
        if (document.getElementById('pedidosTableBody')) {
            await carregarPedidos();
        }
        
        configurarEventos();
        configurarDataPadrao();
        
        console.log('Sistema carregado com sucesso');
        
    } catch (error) {
        console.error('Erro ao inicializar sistema:', error);
        mostrarMensagem('Erro ao inicializar o sistema. Verifique a conexão.', 'error');
    }
}

async function testarAPI() {
    try {
        const data = await apiRequest(`${API_BASE_URL}?action=test`);
        if (!data || data.error) {
            throw new Error('API não está respondendo');
        }
        console.log('API funcionando:', data);
    } catch (error) {
        console.error('Erro na API:', error);
    }
}

function configurarDataPadrao() {
    const hoje = new Date().toISOString().split('T')[0];
    const dataEntrada = document.getElementById('dataEntrada');
    if (dataEntrada) {
        dataEntrada.value = hoje;
    }
}

// ===============================
// CONFIGURAÇÃO DE EVENTOS
// ===============================

function configurarEventos() {
    const formularios = [
        { id: 'addPedidoForm', handler: salvarPedido },
        { id: 'addItemForm', handler: salvarItem },
        { id: 'addItemToPedidoForm', handler: adicionarItemAoPedido },
        { id: 'addItemProcessoForm', handler: adicionarProcessoAoItem },
        { id: 'addProcessoForm', handler: salvarProcesso },
        { id: 'editProcessoForm', handler: atualizarProcesso }
    ];

    formularios.forEach(form => {
        const element = document.getElementById(form.id);
        if (element) {
            element.addEventListener('submit', function(e) {
                e.preventDefault();
                form.handler();
            });
        }
    });

    // Configurar eventos específicos
    setTimeout(() => {
        configurarEventosOrdem();
    }, 1000);
}

function configurarEventosOrdem() {
    const ordemInputs = [
        { id: 'processoOrdem', feedbackId: 'ordemFeedback', isEdit: false },
        { id: 'editProcessoOrdem', feedbackId: 'editOrdemFeedback', isEdit: true }
    ];

    ordemInputs.forEach(input => {
        const element = document.getElementById(input.id);
        if (element && !element.dataset.configured) {
            element.dataset.configured = 'true';
            element.addEventListener('input', () => {
                verificarOrdemDisponivel(element, input.feedbackId, input.isEdit);
            });
        }
    });
}

// ===============================
// GERENCIAMENTO DE MODAIS
// ===============================

const modais = {
    addPedido: { element: 'addPedidoModal', onOpen: limparFormularioPedido },
    selectItem: { element: 'selectItemModal', onOpen: carregarItensParaSelecao },
    itens: { element: 'itensModal', onOpen: () => { carregarItens(); carregarProcessos(); } },
    processos: { element: 'processosModal', onOpen: carregarProcessosList },
    itemProcessos: { element: 'itemProcessosModal' }
};

function openModal(modalKey, ...args) {
    const modal = modais[modalKey];
    if (!modal) return;

    const modalElement = document.getElementById(modal.element);
    if (!modalElement) return;

    modalElement.style.display = 'block';
    
    if (modal.onOpen) {
        modal.onOpen(...args);
    }
}

function closeModal(modalKey) {
    const modal = modais[modalKey];
    if (!modal) return;

    const modalElement = document.getElementById(modal.element);
    if (modalElement) {
        modalElement.style.display = 'none';
    }

    // Limpeza específica
    if (modalKey === 'addPedido') {
        pedidoItens = [];
        atualizarTabelaItensPedido();
    }
}

// Funções específicas de modal (mantidas para compatibilidade)
function openAddPedidoModal() { openModal('addPedido'); }
function closeAddPedidoModal() { closeModal('addPedido'); }
function openSelectItemModal() { openModal('selectItem'); }
function closeSelectItemModal() { closeModal('selectItem'); }
function openItensModal() { openModal('itens'); }
function closeItensModal() { closeModal('itens'); }
function openProcessosModal() { openModal('processos'); }
function closeProcessosModal() { closeModal('processos'); }

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
        document.getElementById('editProcessoForm').reset();
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
        document.getElementById('addItemProcessoForm').reset();
    }
}

// ===============================
// GERENCIAMENTO DE TABS
// ===============================

function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

// ===============================
// FUNÇÕES DE API
// ===============================

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
        mostrarMensagem(error.message || 'Erro de comunicação com o servidor', 'error');
        return null;
    }
}

// ===============================
// CARREGAR DADOS
// ===============================

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
                            <button class="btn-edit" onclick="verItensPedido(${pedido.id})" title="Ver detalhes">👁️</button>
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
        processosDisponiveis = data;
        const tbody = document.getElementById('processosTableBody');
        if (tbody) {
            tbody.innerHTML = '';
            
            // Verificar ordens duplicadas
            const ordemsDuplicadas = verificarOrdensDuplicadas(data);
            
            // Adicionar alerta se houver problemas
            if (ordemsDuplicadas.length > 0) {
                adicionarAlertaOrdem(tbody, ordemsDuplicadas);
            }
            
            // Carregar processos
            data.forEach(processo => {
                const row = criarLinhaProcesso(processo, ordemsDuplicadas);
                tbody.appendChild(row);
            });
            
            const statusMsg = ordemsDuplicadas.length > 0 ? ' (com problemas de ordem)' : '';
            console.log(`${data.length} processos carregados${statusMsg}`);
        }
        
        // Configurar eventos de ordem após carregar
        configurarEventosOrdem();
    }
}

function verificarOrdensDuplicadas(processos) {
    const ordens = processos.map(p => p.ordem);
    const ordensContagem = {};
    
    ordens.forEach(ordem => {
        ordensContagem[ordem] = (ordensContagem[ordem] || 0) + 1;
    });
    
    return Object.keys(ordensContagem)
        .filter(ordem => ordensContagem[ordem] > 1)
        .map(ordem => parseInt(ordem));
}

function adicionarAlertaOrdem(tbody, ordemsDuplicadas) {
    const alertRow = document.createElement('tr');
    alertRow.innerHTML = `
        <td colspan="6" class="alert-ordem-duplicada">
            ⚠️ <strong>Atenção:</strong> Foram detectadas ordens duplicadas nos processos!
            <button class="btn-edit" onclick="verificarOrdemProcessos()" style="margin-left: 10px;">
                🔧 Verificar e Corrigir
            </button>
        </td>
    `;
    tbody.appendChild(alertRow);
}

function criarLinhaProcesso(processo, ordemsDuplicadas) {
    const processosEssenciais = ['corte', 'personalização', 'produção', 'expedição'];
    const isEssencial = processosEssenciais.includes(processo.nome.toLowerCase());
    const tipoLabel = isEssencial ? 
        '<span class="tipo-sistema">Sistema</span>' : 
        '<span class="tipo-personalizado">Personalizado</span>';
    
    const ordemClass = ordemsDuplicadas.includes(processo.ordem) ? 
        'class="ordem-duplicada"' : '';
    
    const row = document.createElement('tr');
    row.innerHTML = `
        <td ${ordemClass}><strong>${processo.ordem}</strong>${ordemsDuplicadas.includes(processo.ordem) ? ' ⚠️' : ''}</td>
        <td>${processo.nome}</td>
        <td>${processo.descricao || '-'}</td>
        <td>${processo.total_usos} uso(s)</td>
        <td>${tipoLabel}</td>
        <td>
            <button class="btn-edit" onclick="openEditProcessoModal(${processo.id}, '${escapeString(processo.nome)}', '${escapeString(processo.descricao || '')}', ${processo.ordem})" title="Editar processo">✏️</button>
            ${!isEssencial ? `<button class="btn-delete" onclick="excluirProcesso(${processo.id})" title="Excluir processo">🗑️</button>` : '<span class="processo-protegido" title="Processos do sistema não podem ser excluídos">🔒</span>'}
        </td>
    `;
    return row;
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
                        <td><strong>${processo.ordem_global || processo.ordem}</strong> <span class="ordem-global-label">(Global)</span></td>
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

// ===============================
// FUNÇÕES DE SALVAMENTO
// ===============================

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
    
    if (!validarProcesso(processoData)) return;
    
    if (processoData.ordem !== null && processosDisponiveis.length > 0) {
        const ordemExiste = processosDisponiveis.some(p => p.ordem === processoData.ordem);
        if (ordemExiste && !confirmarReorganizacao(processoData.ordem)) {
            return;
        }
    }
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=add_processo`, {
        method: 'POST',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        await processarResultadoProcesso(resultado, 'criado');
        document.getElementById('addProcessoForm').reset();
        limparFeedbackOrdem('ordemFeedback');
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
    
    if (!validarProcesso(processoData, true)) return;
    
    const processoAtual = processosDisponiveis.find(p => p.id == processoId);
    if (processoAtual && processoData.ordem !== processoAtual.ordem) {
        const ordemExiste = processosDisponiveis.some(p => p.ordem === processoData.ordem && p.id != processoId);
        if (ordemExiste && !confirmarReorganizacaoEdicao(processoAtual.ordem, processoData.ordem)) {
            return;
        }
    }
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=update_processo&id=${processoId}`, {
        method: 'PUT',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        await processarResultadoProcesso(resultado, 'atualizado');
        closeEditProcessoModal();
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao atualizar processo', 'error');
    }
}

function validarProcesso(processoData, isEdit = false) {
    if (!processoData.nome || processoData.nome.trim() === '') {
        mostrarMensagem('Nome do processo é obrigatório', 'error');
        return false;
    }
    
    if (isEdit || processoData.ordem !== null) {
        if (processoData.ordem < 1 || !Number.isInteger(processoData.ordem)) {
            mostrarMensagem('Ordem deve ser um número inteiro positivo', 'error');
            return false;
        }
    }
    
    return true;
}

function confirmarReorganizacao(ordem) {
    return confirm(
        `A ordem ${ordem} já está sendo usada por outro processo.\n\n` +
        `Deseja continuar? Os processos com ordem ${ordem} ou superior serão automaticamente renumerados.`
    );
}

function confirmarReorganizacaoEdicao(ordemAtual, novaOrdem) {
    return confirm(
        `A ordem ${novaOrdem} já está sendo usada por outro processo.\n\n` +
        `Deseja continuar? Os processos entre as ordens ${ordemAtual} e ${novaOrdem} serão automaticamente reorganizados.`
    );
}

async function processarResultadoProcesso(resultado, acao) {
    let mensagem = resultado.message || `Processo ${acao} com sucesso!`;
    
    if (resultado.reorganizacao && resultado.processos_movidos > 0) {
        console.log(`Reorganização no ${acao}:`, resultado);
    }
    
    mostrarMensagem(mensagem, 'success');
    
    await carregarProcessosList();
    await carregarProcessos();
    
    if (resultado.reorganizacao) {
        setTimeout(() => {
            mostrarMensagemReorganizacao(resultado);
        }, 2000);
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
        observacoes: formData.get('observacoes') || ''
    };
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=add_item_processo`, {
        method: 'POST',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem(resultado.message || 'Processo adicionado com sucesso!', 'success');
        document.getElementById('addItemProcessoForm').reset();
        carregarProcessosDoItem(itemId);
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao adicionar processo', 'error');
    }
}

// ===============================
// VERIFICAÇÃO DE ORDEM
// ===============================

function verificarOrdemDisponivel(input, feedbackId, isEdit = false) {
    const feedbackElement = document.getElementById(feedbackId);
    if (!feedbackElement) return;
    
    if (!input.value || !processosDisponiveis || processosDisponiveis.length === 0) {
        limparFeedbackOrdem(feedbackId);
        return;
    }
    
    const ordemEscolhida = parseInt(input.value);
    let mensagem = '';
    let tipo = 'info';
    
    if (isEdit) {
        const processoId = document.getElementById('editProcessoId').value;
        const processoAtual = processosDisponiveis.find(p => p.id == processoId);
        const processoConflito = processosDisponiveis.find(p => p.ordem === ordemEscolhida && p.id != processoId);
        
        if (processoConflito) {
            mensagem = `⚠️ Ordem ${ordemEscolhida} ocupada por "${processoConflito.nome}". Processos serão reorganizados.`;
            tipo = 'warning';
        } else if (processoAtual && processoAtual.ordem === ordemEscolhida) {
            mensagem = `✅ Mantendo ordem atual (${ordemEscolhida}).`;
            tipo = 'success';
        } else {
            mensagem = `✅ Ordem ${ordemEscolhida} disponível.`;
            tipo = 'success';
        }
    } else {
        const processoConflito = processosDisponiveis.find(p => p.ordem === ordemEscolhida);
        
        if (processoConflito) {
            mensagem = `⚠️ Ordem ${ordemEscolhida} ocupada por "${processoConflito.nome}". Processos serão reorganizados.`;
            tipo = 'warning';
        } else {
            mensagem = `✅ Ordem ${ordemEscolhida} disponível.`;
            tipo = 'success';
        }
    }
    
    mostrarFeedbackOrdem(feedbackElement, mensagem, tipo);
}

function mostrarFeedbackOrdem(element, mensagem, tipo) {
    element.innerHTML = `<div class="feedback-${tipo}">${mensagem}</div>`;
    element.style.display = 'block';
}

function limparFeedbackOrdem(feedbackId) {
    const element = document.getElementById(feedbackId);
    if (element) {
        element.innerHTML = '';
        element.style.display = 'none';
    }
}

async function verificarOrdemProcessos() {
    console.log('Verificando ordem dos processos...');
    
    try {
        const data = await apiRequest(`${API_BASE_URL}?action=get_processos`);
        
        if (data && Array.isArray(data)) {
            const ordemsDuplicadas = verificarOrdensDuplicadas(data);
            
            if (ordemsDuplicadas.length > 0) {
                const confirmacao = confirm(
                    `⚠️ Foram detectadas ordens duplicadas nos processos!\n\n` +
                    `Ordens com problema: ${ordemsDuplicadas.join(', ')}\n\n` +
                    `Deseja corrigir automaticamente a numeração?`
                );
                
                if (confirmacao) {
                    await corrigirOrdemProcessos();
                }
            } else {
                mostrarMensagem('✅ Ordem dos processos está correta!', 'success');
                atualizarResultadoVerificacao('✅ Ordem dos processos está correta! Nenhum problema encontrado.');
            }
        }
    } catch (error) {
        console.error('Erro ao verificar ordem:', error);
        mostrarMensagem('Erro ao verificar ordem dos processos', 'error');
    }
}

async function corrigirOrdemProcessos() {
    console.log('Corrigindo ordem dos processos...');
    
    try {
        const data = await apiRequest(`${API_BASE_URL}?action=get_processos`);
        
        if (data && Array.isArray(data)) {
            const processosOrdenados = data.sort((a, b) => {
                if (a.ordem !== b.ordem) {
                    return a.ordem - b.ordem;
                }
                return a.nome.localeCompare(b.nome);
            });
            
            let processosCorrigidos = 0;
            const promises = [];
            
            processosOrdenados.forEach((processo, index) => {
                const novaOrdem = index + 1;
                if (processo.ordem !== novaOrdem) {
                    const updatePromise = apiRequest(`${API_BASE_URL}?action=update_processo&id=${processo.id}`, {
                        method: 'PUT',
                        body: JSON.stringify({
                            nome: processo.nome,
                            descricao: processo.descricao || '',
                            ordem: novaOrdem
                        })
                    });
                    promises.push(updatePromise);
                    processosCorrigidos++;
                }
            });
            
            if (promises.length > 0) {
                await Promise.all(promises);
                
                const mensagem = `🔧 Correção concluída!\n\n${processosCorrigidos} processo(s) tiveram sua ordem ajustada.\nNova sequência: 1 a ${processosOrdenados.length}`;
                mostrarMensagem(mensagem, 'success');
                atualizarResultadoVerificacao(`✅ ${mensagem}`);
                
                await carregarProcessosList();
                await carregarProcessos();
                
            } else {
                const mensagem = '✅ Numeração já estava correta. Nenhum ajuste necessário.';
                mostrarMensagem(mensagem, 'success');
                atualizarResultadoVerificacao(mensagem);
            }
        }
    } catch (error) {
        console.error('Erro ao corrigir ordem:', error);
        mostrarMensagem('Erro ao corrigir ordem dos processos', 'error');
    }
}

function atualizarResultadoVerificacao(mensagem) {
    const elemento = document.getElementById('resultadoVerificacao');
    if (elemento) {
        elemento.innerHTML = `<div class="verification-result-content">${mensagem}</div>`;
        elemento.style.display = 'block';
    }
}

// ===============================
// MODAL DE REORGANIZAÇÃO
// ===============================

function mostrarMensagemReorganizacao(resultado) {
    const modalHtml = `
        <div id="reorganizacaoModal" class="modal" style="display: block;">
            <div class="modal-content small">
                <div class="modal-header" style="background: linear-gradient(135deg, #2196F3, #1976D2);">
                    <h2>🔄 Reorganização Automática</h2>
                    <span class="close" onclick="closeReorganizacaoModal()">&times;</span>
                </div>
                
                <div style="padding: 25px;">
                    <div class="info-box success-info">
                        <p style="margin: 0; font-weight: 600;">✅ Reorganização realizada com sucesso!</p>
                    </div>
                    
                    <div class="reorganizacao-details">
                        <p><strong>📊 Processos reorganizados:</strong> ${resultado.processos_movidos}</p>
                        <p><strong>📍 Nova ordem:</strong> ${resultado.ordem_final}</p>
                        ${resultado.ordem_anterior ? `<p><strong>🔄 Movido de:</strong> ${resultado.ordem_anterior} → ${resultado.ordem_final}</p>` : ''}
                    </div>
                    
                    <div class="info-box">
                        <p style="margin: 0;">
                            <strong>ℹ️ Como funciona:</strong> Quando você escolhe uma ordem já ocupada, 
                            todos os processos com ordem igual ou superior são automaticamente 
                            renumerados para manter a sequência correta.
                        </p>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button class="btn-save" onclick="closeReorganizacaoModal()">Entendi</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closeReorganizacaoModal() {
    const modal = document.getElementById('reorganizacaoModal');
    if (modal) {
        modal.remove();
    }
}

// ===============================
// VISUALIZAÇÃO DE DETALHES
// ===============================

async function verItensPedido(pedidoId) {
    console.log('Visualizando detalhes do pedido:', pedidoId);
    const data = await apiRequest(`${API_BASE_URL}?action=get_pedido_detalhado&pedido_id=${pedidoId}`);
    
    if (data && data.pedido) {
        criarModalDetalhePedido(data);
    }
}

async function criarModalDetalhePedido(data) {
    const pedido = data.pedido;
    const processos = data.processos || [];
    
    const processosAgrupados = await agruparProcessosPorOrdemGlobal(processos);
    
    const modalHtml = `
        <div id="viewDetalhePedidoModal" class="modal" style="display: block;">
            <div class="modal-content large">
                <div class="modal-header">
                    <h2>Detalhes do Pedido: ${pedido.codigo_pedido}</h2>
                    <span class="close" onclick="closeViewDetalhePedidoModal()">&times;</span>
                </div>
                
                <div class="pedido-info-compact">
                    <div class="info-row">
                        <div class="info-card">
                            <label>Cliente</label>
                            <span>${pedido.cliente}</span>
                        </div>
                        <div class="info-card">
                            <label>Entrada</label>
                            <span>${formatarData(pedido.data_entrada)}</span>
                        </div>
                        <div class="info-card">
                            <label>Entrega</label>
                            <span>${formatarData(pedido.data_entrega)}</span>
                        </div>
                        <div class="info-card">
                            <label>Status</label>
                            <span class="status-${pedido.processo_atual}">${capitalizeFirst(pedido.processo_atual)}</span>
                        </div>
                    </div>
                    
                    <div class="progress-row">
                        <div class="progress-container">
                            <label>Progresso Geral</label>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${pedido.progresso_geral || 0}%"></div>
                                <span class="progress-text">${Math.round(pedido.progresso_geral || 0)}%</span>
                            </div>
                            <div class="progress-info">
                                ${data.processos_completos || 0} de ${data.total_processos || 0} processos concluídos
                                ${data.processo_atual_auto ? `• Próximo: ${data.processo_atual_auto}` : ''}
                            </div>
                        </div>
                        <div class="actions-compact">
                            <button class="btn-edit" onclick="editarPedido(${pedido.id})">✏️ Editar</button>
                            <button class="btn-edit" onclick="adicionarItemAoPedidoExistente(${pedido.id})">➕ Item</button>
                        </div>
                    </div>
                </div>
                
                <div class="processos-acompanhamento">
                    <h3>Processos na Ordem Global</h3>
                    <div class="ordem-info">
                        <small>🌐 <strong>Ordem Global:</strong> Processos ordenados conforme a sequência padrão da empresa. Itens que não passam por determinado processo não aparecem naquele grupo.</small>
                    </div>
                    <div class="processos-lista">
                        ${renderizarProcessosAgrupados(processosAgrupados)}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function renderizarProcessosAgrupados(processosAgrupados) {
    if (processosAgrupados.length === 0) {
        return '<p style="text-align: center; padding: 40px; color: #666;">Nenhum processo encontrado para este pedido</p>';
    }
    
    return processosAgrupados.map(grupo => `
        <div class="processo-grupo-item ${grupo.status_geral}" data-ordem="${grupo.ordem_global}">
            <div class="processo-numero">
                <span class="ordem-badge">${grupo.ordem_global}</span>
            </div>
            <div class="processo-detalhes">
                <div class="processo-titulo">
                    <span class="processo-nome">${grupo.processo_nome}</span>
                    <div class="itens-agrupados">
                        ${grupo.itens.map(item => `
                            <span class="item-badge">${item.item_nome} (${item.quantidade}x)</span>
                        `).join('')}
                    </div>
                </div>
                <div class="processo-status-line">
                    <select class="status-select" onchange="updateGrupoProcessoStatus(${JSON.stringify(grupo.processos_ids).replace(/"/g, '&quot;')}, this.value)">
                        <option value="aguardando" ${grupo.status_geral === 'aguardando' ? 'selected' : ''}>⏳ Aguardando</option>
                        <option value="em_andamento" ${grupo.status_geral === 'em_andamento' ? 'selected' : ''}>🔄 Em Andamento</option>
                        <option value="completo" ${grupo.status_geral === 'completo' ? 'selected' : ''}>✅ Completo</option>
                    </select>
                    <span class="grupo-count">${grupo.total_processos} item(s)</span>
                </div>
                ${grupo.data_inicio ? `<div class="processo-dates">📅 Início: ${formatarDataHora(grupo.data_inicio)}</div>` : ''}
                ${grupo.data_conclusao ? `<div class="processo-dates">✅ Conclusão: ${formatarDataHora(grupo.data_conclusao)}</div>` : ''}
                ${grupo.observacoes ? `<div class="processo-observacoes">💬 ${grupo.observacoes}</div>` : ''}
            </div>
            <div class="processo-status-icon">
                ${getStatusIcon(grupo.status_geral)}
            </div>
        </div>
    `).join('');
}

async function agruparProcessosPorOrdemGlobal(processos) {
    try {
        const processosGlobais = await apiRequest(`${API_BASE_URL}?action=get_processos`);
        
        if (!processosGlobais || !Array.isArray(processosGlobais)) {
            console.error('Erro ao buscar processos globais');
            return [];
        }
        
        const ordemGlobalMap = {};
        processosGlobais.forEach(processo => {
            ordemGlobalMap[processo.id] = processo.ordem;
        });
        
        const grupos = {};
        
        processos.forEach(processo => {
            const processoId = processo.processo_id;
            const ordemGlobal = ordemGlobalMap[processoId] || 999;
            
            if (!grupos[processoId]) {
                grupos[processoId] = {
                    processo_id: processoId,
                    processo_nome: processo.processo_nome,
                    ordem_global: ordemGlobal,
                    itens: [],
                    processos_ids: [],
                    status_list: [],
                    data_inicio: null,
                    data_conclusao: null,
                    observacoes: null,
                    total_processos: 0
                };
            }
            
            const grupo = grupos[processoId];
            
            grupo.itens.push({
                item_nome: processo.item_nome,
                quantidade: processo.quantidade,
                pedido_item_id: processo.pedido_item_id
            });
            
            grupo.processos_ids.push({
                pedido_item_id: processo.pedido_item_id,
                processo_id: processo.processo_id
            });
            
            grupo.status_list.push(processo.status);
            grupo.total_processos++;
            
            if (processo.data_inicio && (!grupo.data_inicio || processo.data_inicio < grupo.data_inicio)) {
                grupo.data_inicio = processo.data_inicio;
            }
            
            if (processo.data_conclusao && (!grupo.data_conclusao || processo.data_conclusao > grupo.data_conclusao)) {
                grupo.data_conclusao = processo.data_conclusao;
            }
            
            if (processo.observacoes && !grupo.observacoes) {
                grupo.observacoes = processo.observacoes;
            }
        });
        
        Object.values(grupos).forEach(grupo => {
            const status = grupo.status_list;
            const completos = status.filter(s => s === 'completo').length;
            const emAndamento = status.filter(s => s === 'em_andamento').length;
            
            if (completos === status.length) {
                grupo.status_geral = 'completo';
            } else if (emAndamento > 0 || completos > 0) {
                grupo.status_geral = 'em_andamento';
            } else {
                grupo.status_geral = 'aguardando';
            }
        });
        
        return Object.values(grupos).sort((a, b) => a.ordem_global - b.ordem_global);
        
    } catch (error) {
        console.error('Erro no agrupamento por ordem global:', error);
        return [];
    }
}

function closeViewDetalhePedidoModal() {
    const modal = document.getElementById('viewDetalhePedidoModal');
    if (modal) {
        modal.remove();
    }
}

async function updateGrupoProcessoStatus(processosIds, novoStatus) {
    try {
        const promises = processosIds.map(processo => 
            apiRequest(`${API_BASE_URL}?action=update_processo_status`, {
                method: 'POST',
                body: JSON.stringify({
                    pedido_item_id: processo.pedido_item_id,
                    processo_id: processo.processo_id,
                    status: novoStatus,
                    usuario_responsavel: 'Sistema'
                })
            })
        );
        
        const resultados = await Promise.all(promises);
        const sucessos = resultados.filter(r => r && r.success).length;
        
        if (sucessos === processosIds.length) {
            mostrarMensagem(`Grupo de processos atualizado para "${getStatusLabel(novoStatus)}"`, 'success');
            
            // Recarregar o modal
            const modal = document.getElementById('viewDetalhePedidoModal');
            if (modal) {
                const codigoPedido = modal.querySelector('.modal-header h2').textContent.match(/Detalhes do Pedido: (.+)/)?.[1];
                if (codigoPedido) {
                    const pedidoRows = document.querySelectorAll('#pedidosTableBody tr');
                    for (let row of pedidoRows) {
                        if (row.querySelector('td:nth-child(3)')?.textContent === codigoPedido) {
                            const onclickAttr = row.querySelector('.btn-edit[onclick*="verItensPedido"]')?.getAttribute('onclick');
                            const pedidoId = onclickAttr?.match(/verItensPedido\((\d+)\)/)?.[1];
                            if (pedidoId) {
                                modal.remove();
                                setTimeout(() => verItensPedido(pedidoId), 300);
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            mostrarMensagem(`Apenas ${sucessos} de ${processosIds.length} processos foram atualizados`, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao atualizar grupo de processos:', error);
        mostrarMensagem('Erro ao atualizar grupo de processos', 'error');
    }
}

// ===============================
// ADIÇÃO DE ITENS A PEDIDOS EXISTENTES
// ===============================

async function adicionarItemAoPedidoExistente(pedidoId) {
    closeViewDetalhePedidoModal();
    
    const modal = document.getElementById('selectItemModal');
    if (modal) {
        modal.style.display = 'block';
        await carregarItensParaSelecao();
        
        window.tempPedidoId = pedidoId;
        window.originalOpenAddItemToPedidoModal = window.openAddItemToPedidoModal;
        window.openAddItemToPedidoModal = function(itemId, itemNome) {
            openAddItemToPedidoExistenteModal(itemId, itemNome, pedidoId);
        };
    }
}

function openAddItemToPedidoExistenteModal(itemId, itemNome, pedidoId) {
    const modalHtml = `
        <div id="addItemToPedidoExistenteModal" class="modal" style="display: block;">
            <div class="modal-content small">
                <div class="modal-header">
                    <h2>Adicionar Item ao Pedido</h2>
                    <span class="close" onclick="closeAddItemToPedidoExistenteModal()">&times;</span>
                </div>
                
                <form id="addItemToPedidoExistenteForm">
                    <input type="hidden" id="existentePedidoId" value="${pedidoId}">
                    <input type="hidden" id="existenteItemId" value="${itemId}">
                    <div class="form-group">
                        <label>Item: ${itemNome}</label>
                    </div>
                    <div class="form-group">
                        <label for="existenteItemQuantidade">Quantidade:</label>
                        <input type="number" id="existenteItemQuantidade" name="quantidade" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="existenteItemObservacoes">Observações:</label>
                        <textarea id="existenteItemObservacoes" name="observacoes" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeAddItemToPedidoExistenteModal()">Cancelar</button>
                        <button type="submit" class="btn-save">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    document.getElementById('addItemToPedidoExistenteForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        await salvarItemEmPedidoExistente();
    });
}

function closeAddItemToPedidoExistenteModal() {
    const modal = document.getElementById('addItemToPedidoExistenteModal');
    if (modal) {
        modal.remove();
    }
    
    if (window.originalOpenAddItemToPedidoModal) {
        window.openAddItemToPedidoModal = window.originalOpenAddItemToPedidoModal;
        delete window.originalOpenAddItemToPedidoModal;
        delete window.tempPedidoId;
    }
    
    closeSelectItemModal();
}

async function salvarItemEmPedidoExistente() {
    const pedidoId = document.getElementById('existentePedidoId').value;
    const itemId = document.getElementById('existenteItemId').value;
    const quantidade = document.getElementById('existenteItemQuantidade').value;
    const observacoes = document.getElementById('existenteItemObservacoes').value;
    
    const resultado = await apiRequest(`${API_BASE_URL}?action=add_item_to_pedido`, {
        method: 'POST',
        body: JSON.stringify({
            pedido_id: parseInt(pedidoId),
            item_id: parseInt(itemId),
            quantidade: parseInt(quantidade),
            observacoes: observacoes
        })
    });
    
    if (resultado && resultado.success) {
        mostrarMensagem('Item adicionado ao pedido com sucesso!', 'success');
        closeAddItemToPedidoExistenteModal();
        setTimeout(() => verItensPedido(pedidoId), 500);
    } else {
        mostrarMensagem(resultado?.error || 'Erro ao adicionar item', 'error');
    }
}

// ===============================
// FUNÇÕES DE EXCLUSÃO
// ===============================

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
        carregarProcessos();
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

// ===============================
// FUNÇÕES AUXILIARES
// ===============================

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

function editarPedido(pedidoId) {
    mostrarMensagem('Funcionalidade de edição em desenvolvimento', 'error');
}

function limparFormularioPedido() {
    const form = document.getElementById('addPedidoForm');
    if (form) {
        form.reset();
        pedidoItens = [];
        atualizarTabelaItensPedido();
        configurarDataPadrao();
    }
}

function mostrarLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<tr><td colspan="100%" class="loading">⏳ Carregando...</td></tr>';
    }
}

function mostrarMensagem(mensagem, tipo) {
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
}

// ===============================
// FUNÇÕES DE FORMATAÇÃO
// ===============================

function formatarData(dataString) {
    if (!dataString) return '-';
    const data = new Date(dataString + 'T00:00:00');
    return data.toLocaleDateString('pt-BR');
}

function formatarDataHora(dataString) {
    if (!dataString) return '-';
    const data = new Date(dataString);
    return data.toLocaleString('pt-BR');
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeString(str) {
    if (!str) return '';
    return str.replace(/'/g, '\\\'').replace(/"/g, '&quot;');
}

function getStatusLabel(status) {
    const labels = {
        'aguardando': 'Aguardando',
        'em_andamento': 'Em Andamento',
        'completo': 'Completo'
    };
    return labels[status] || status;
}

function getStatusIcon(status) {
    const icons = {
        'aguardando': '<span class="status-icon waiting">⏳</span>',
        'em_andamento': '<span class="status-icon progress">🔄</span>',
        'completo': '<span class="status-icon complete">✅</span>'
    };
    return icons[status] || icons['aguardando'];
}

// ===============================
// EVENT LISTENERS GLOBAIS
// ===============================

window.onclick = function(event) {
    const modais = document.querySelectorAll('.modal');
    modais.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
};

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modaisAbertos = document.querySelectorAll('.modal[style*="block"]');
        modaisAbertos.forEach(modal => {
            modal.style.display = 'none';
        });
    }
});

console.log('Script carregado - Sistema de Controle de Produção v5.0 - Versão Otimizada');