// js/forms.js - Módulo de Salvamento e Formulários v5.3

// === FUNÇÕES DE SALVAMENTO ===
async function salvarPedido() {
    console.log('Salvando pedido...');
    const formData = new FormData(document.getElementById('addPedidoForm'));
    
    const pedidoData = {
        data_entrada: formData.get('dataEntrada'),
        data_entrega: formData.get('dataEntrega'),
        codigo_pedido: formData.get('codigoPedido'),
        cliente: formData.get('cliente'),
        processo_atual: formData.get('processoAtual'),
        itens: window.pedidoItens
    };
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=add_pedido`, {
        method: 'POST',
        body: JSON.stringify(pedidoData)
    });
    
    if (resultado && resultado.success) {
        window.mostrarMensagem('Pedido salvo com sucesso!', 'success');
        window.closeAddPedidoModal();
        window.carregarPedidos();
    } else {
        window.mostrarMensagem(resultado?.error || 'Erro ao salvar pedido', 'error');
    }
}

async function salvarItem() {
    console.log('Salvando item...');
    const formData = new FormData(document.getElementById('addItemForm'));
    
    const itemData = {
        nome: formData.get('nome'),
        descricao: formData.get('descricao')
    };
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=add_item`, {
        method: 'POST',
        body: JSON.stringify(itemData)
    });
    
    if (resultado && resultado.success) {
        window.mostrarMensagem('Item salvo com sucesso!', 'success');
        document.getElementById('addItemForm').reset();
        window.carregarItens();
    } else {
        window.mostrarMensagem(resultado?.error || 'Erro ao salvar item', 'error');
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
    
    if (processoData.ordem !== null && window.processosDisponiveis.length > 0) {
        const ordemExiste = window.processosDisponiveis.some(p => p.ordem === processoData.ordem);
        if (ordemExiste && !confirmarReorganizacao(processoData.ordem)) {
            return;
        }
    }
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=add_processo`, {
        method: 'POST',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        await processarResultadoProcesso(resultado, 'criado');
        document.getElementById('addProcessoForm').reset();
        if (typeof window.limparFeedbackOrdem === 'function') {
            window.limparFeedbackOrdem('ordemFeedback');
        }
    } else {
        window.mostrarMensagem(resultado?.error || 'Erro ao criar processo', 'error');
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
    
    const processoAtual = window.processosDisponiveis.find(p => p.id == processoId);
    if (processoAtual && processoData.ordem !== processoAtual.ordem) {
        const ordemExiste = window.processosDisponiveis.some(p => p.ordem === processoData.ordem && p.id != processoId);
        if (ordemExiste && !confirmarReorganizacaoEdicao(processoAtual.ordem, processoData.ordem)) {
            return;
        }
    }
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=update_processo&id=${processoId}`, {
        method: 'PUT',
        body: JSON.stringify(processoData)
    });
    
    if (resultado && resultado.success) {
        await processarResultadoProcesso(resultado, 'atualizado');
        window.closeEditProcessoModal();
    } else {
        window.mostrarMensagem(resultado?.error || 'Erro ao atualizar processo', 'error');
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

// === VALIDAÇÕES ===
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

// === FUNÇÕES AUXILIARES PARA FORMULÁRIOS ===
function limparFormularioPedido() {
    const form = document.getElementById('addPedidoForm');
    if (form) {
        form.reset();
        pedidoItens = [];
        atualizarTabelaItensPedido();
        configurarDataPadrao();
    }
}

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

console.log('Módulo Forms carregado - Salvamento e formulários v5.3');