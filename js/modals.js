// js/modals.js - Módulo de Gerenciamento de Modais v0.5.4

// === GERENCIAMENTO DE MODAIS ===
const modais = {
    addPedido: { 
        element: 'addPedidoModal', 
        onOpen: () => {
            if (typeof window.limparFormularioPedido === 'function') {
                window.limparFormularioPedido();
            }
        }
    },
    editPedido: { 
        element: 'editPedidoModal' 
    },
    selectItem: { 
        element: 'selectItemModal', 
        onOpen: () => {
            if (typeof window.carregarItensParaSelecao === 'function') {
                window.carregarItensParaSelecao();
            }
        }
    },
    itens: { 
        element: 'itensModal', 
        onOpen: () => { 
            if (typeof window.carregarItens === 'function') window.carregarItens(); 
            if (typeof window.carregarProcessos === 'function') window.carregarProcessos(); 
        }
    },
    processos: { 
        element: 'processosModal', 
        onOpen: () => {
            if (typeof window.carregarProcessosList === 'function') {
                window.carregarProcessosList();
            }
        }
    },
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
        if (typeof window.pedidoItens !== 'undefined') {
            window.pedidoItens = [];
        }
        if (typeof window.atualizarTabelaItensPedido === 'function') {
            window.atualizarTabelaItensPedido();
        }
    }
}

// === FUNÇÕES ESPECÍFICAS DE MODAL (para compatibilidade) ===
function openAddPedidoModal() { openModal('addPedido'); }
function closeAddPedidoModal() { closeModal('addPedido'); }
function openSelectItemModal() { openModal('selectItem'); }
function closeSelectItemModal() { closeModal('selectItem'); }
function openItensModal() { openModal('itens'); }
function closeItensModal() { closeModal('itens'); }
function openProcessosModal() { openModal('processos'); }
function closeProcessosModal() { closeModal('processos'); }

// === MODAL DE EDIÇÃO DE PEDIDO ===
async function openEditPedidoModal(pedido) {
    // Fechar modal de detalhes se estiver aberto
    const detalhesModal = document.getElementById('viewDetalhePedidoModal');
    if (detalhesModal) {
        detalhesModal.remove();
    }
    
    const modal = document.getElementById('editPedidoModal');
    if (modal) {
        // Preencher os campos do formulário
        document.getElementById('editPedidoId').value = pedido.id;
        document.getElementById('editDataEntrada').value = pedido.data_entrada;
        document.getElementById('editDataEntrega').value = pedido.data_entrega;
        document.getElementById('editCodigoPedido').value = pedido.codigo_pedido;
        document.getElementById('editCliente').value = pedido.cliente;
        document.getElementById('editProcessoAtual').value = pedido.processo_atual;
        
        // Carregar itens do pedido
        await window.carregarItensPedidoEdit(pedido.id);
        
        modal.style.display = 'block';
    }
}

function closeEditPedidoModal() {
    const modal = document.getElementById('editPedidoModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('editPedidoForm').reset();
        
        // Limpar tabela de itens
        const tbody = document.getElementById('editPedidoItensBody');
        if (tbody) {
            tbody.innerHTML = '';
        }
    }
}

// === MODAL DE SELEÇÃO DE ITENS PARA EDIÇÃO ===
function openSelectItemModalEdit() {
    window.isEditMode = true;
    window.originalAddItemToPedido = window.adicionarItemAoPedido;
    window.adicionarItemAoPedido = window.adicionarItemAoPedidoEdit;
    openSelectItemModal();
}

// === MODAL DE EDIÇÃO DE ITEM DO PEDIDO ===
function openEditItemPedidoModal(pedidoItemId, itemNome, quantidade, observacoes) {
    const modal = document.getElementById('editItemPedidoModal');
    if (modal) {
        document.getElementById('editItemPedidoId').value = pedidoItemId;
        document.getElementById('editItemPedidoNome').textContent = `Item: ${itemNome}`;
        document.getElementById('editItemQuantidade').value = quantidade;
        document.getElementById('editItemObservacoes').value = observacoes || '';
        modal.style.display = 'block';
    }
}

function closeEditItemPedidoModal() {
    const modal = document.getElementById('editItemPedidoModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('editItemPedidoForm').reset();
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
        
        // Restaurar função original se estava em modo de edição
        if (window.isEditMode && window.originalAddItemToPedido) {
            window.adicionarItemAoPedido = window.originalAddItemToPedido;
            delete window.originalAddItemToPedido;
            delete window.isEditMode;
        }
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
        window.carregarProcessosDoItem(itemId);
    }
}

function closeItemProcessosModal() {
    const modal = document.getElementById('itemProcessosModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('addItemProcessoForm').reset();
    }
}

// === MODAL DE REORGANIZAÇÃO ===
function mostrarMensagemReorganizacao(resultado) {
    const modalHtml = `
        <div id="reorganizacaoModal" class="modal" style="display: block; z-index: 1050;">
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

// === MODAL DE DETALHES DO PEDIDO ===
async function criarModalDetalhePedido(data) {
    const pedido = data.pedido;
    const processos = data.processos || [];
    
    const processosAgrupados = await window.agruparProcessosPorOrdemGlobal(processos);
    
    const modalHtml = `
        <div id="viewDetalhePedidoModal" class="modal" style="display: block; z-index: 1010;">
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
                            <span>${window.formatarData(pedido.data_entrada)}</span>
                        </div>
                        <div class="info-card">
                            <label>Entrega</label>
                            <span>${window.formatarData(pedido.data_entrega)}</span>
                        </div>
                        <div class="info-card">
                            <label>Status</label>
                            <span class="status-${pedido.processo_atual}">${window.capitalizeFirst(pedido.processo_atual)}</span>
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
                            <button class="btn-edit" onclick="editarPedidoFromDetails(${pedido.id})">✏️ Editar Pedido</button>
                        </div>
                    </div>
                </div>
                
                <div class="processos-acompanhamento">
                    <h3>Processos na Ordem Global</h3>
                    <div class="ordem-info">
                        <small>🌐 <strong>Ordem Global:</strong> Processos ordenados conforme a sequência padrão da empresa. Itens que não passam por determinado processo não aparecem naquele grupo.</small>
                    </div>
                    <div class="processos-lista">
                        ${window.renderizarProcessosAgrupados(processosAgrupados)}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closeViewDetalhePedidoModal() {
    const modal = document.getElementById('viewDetalhePedidoModal');
    if (modal) {
        modal.remove();
    }
}

// === FUNÇÃO ESPECÍFICA PARA EDITAR PEDIDO A PARTIR DOS DETALHES ===
async function editarPedidoFromDetails(pedidoId) {
    console.log('Editando pedido a partir dos detalhes:', pedidoId);
    
    // Buscar dados do pedido
    const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_pedidos`);
    
    if (data && Array.isArray(data)) {
        const pedido = data.find(p => p.id == pedidoId);
        
        if (pedido) {
            await window.openEditPedidoModal(pedido);
        } else {
            window.mostrarMensagem('Pedido não encontrado', 'error');
        }
    } else {
        window.mostrarMensagem('Erro ao carregar dados do pedido', 'error');
    }
}

// === MODAIS DE PEDIDOS EXISTENTES ===
function openAddItemToPedidoExistenteModal(itemId, itemNome, pedidoId) {
    const modalHtml = `
        <div id="addItemToPedidoExistenteModal" class="modal" style="display: block; z-index: 1040;">
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
        await window.salvarItemEmPedidoExistente();
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
}

// === DISPONIBILIZAR FUNÇÕES GLOBALMENTE ===
window.openModal = openModal;
window.closeModal = closeModal;
window.openAddPedidoModal = openAddPedidoModal;
window.closeAddPedidoModal = closeAddPedidoModal;
window.openEditPedidoModal = openEditPedidoModal;
window.closeEditPedidoModal = closeEditPedidoModal;
window.openSelectItemModal = openSelectItemModal;
window.openSelectItemModalEdit = openSelectItemModalEdit;
window.closeSelectItemModal = closeSelectItemModal;
window.openEditItemPedidoModal = openEditItemPedidoModal;
window.closeEditItemPedidoModal = closeEditItemPedidoModal;
window.openItensModal = openItensModal;
window.closeItensModal = closeItensModal;
window.openProcessosModal = openProcessosModal;
window.closeProcessosModal = closeProcessosModal;
window.openAddItemToPedidoModal = openAddItemToPedidoModal;
window.closeAddItemToPedidoModal = closeAddItemToPedidoModal;
window.openEditProcessoModal = openEditProcessoModal;
window.closeEditProcessoModal = closeEditProcessoModal;
window.openItemProcessosModal = openItemProcessosModal;
window.closeItemProcessosModal = closeItemProcessosModal;
window.mostrarMensagemReorganizacao = mostrarMensagemReorganizacao;
window.closeReorganizacaoModal = closeReorganizacaoModal;
window.criarModalDetalhePedido = criarModalDetalhePedido;
window.closeViewDetalhePedidoModal = closeViewDetalhePedidoModal;
window.editarPedidoFromDetails = editarPedidoFromDetails;
window.openAddItemToPedidoExistenteModal = openAddItemToPedidoExistenteModal;
window.closeAddItemToPedidoExistenteModal = closeAddItemToPedidoExistenteModal;

console.log('Módulo Modals carregado - Gerenciamento de modais v0.5.4');