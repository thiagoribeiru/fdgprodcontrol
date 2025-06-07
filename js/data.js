// js/data.js - Módulo de Carregamento de Dados v5.3

// === CARREGAMENTO DE DADOS ===
async function carregarPedidos() {
    console.log('Carregando pedidos...');
    window.mostrarLoading('pedidosTableBody');
    
    const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_pedidos`);
    
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
                        <td>${window.formatarData(pedido.data_entrada)}</td>
                        <td>${window.formatarData(pedido.data_entrega)}</td>
                        <td><strong>${pedido.codigo_pedido}</strong></td>
                        <td>${pedido.cliente}</td>
                        <td>
                            <span class="status-${pedido.processo_atual}" 
                                  onclick="alterarProcessoPedido(${pedido.id}, '${pedido.processo_atual}')" 
                                  title="Clique para avançar processo">
                                ${window.capitalizeFirst(pedido.processo_atual)}
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
    const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_itens`);
    
    if (data && Array.isArray(data)) {
        window.itensDisponiveis = data;
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
                        <button class="btn-edit" onclick="openItemProcessosModal(${item.id}, '${window.escapeString(item.nome)}')" title="Configurar processos">⚙️</button>
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
    if (window.itensDisponiveis.length === 0) {
        await carregarItens();
    }
    
    const tbody = document.getElementById('selectItemBody');
    if (tbody) {
        tbody.innerHTML = '';
        
        window.itensDisponiveis.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${item.nome}</strong></td>
                <td>${item.descricao || '-'}</td>
                <td>
                    <button class="btn-save" onclick="openAddItemToPedidoModal(${item.id}, '${window.escapeString(item.nome)}')">Selecionar</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

async function carregarProcessos() {
    console.log('Carregando processos...');
    const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_processos`);
    
    if (data && Array.isArray(data)) {
        window.processosDisponiveis = data;
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
    const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_processos`);
    
    if (data && Array.isArray(data)) {
        window.processosDisponiveis = data;
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
        if (typeof window.configurarEventosOrdem === 'function') {
            window.configurarEventosOrdem();
        }
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
            <button class="btn-edit" onclick="openEditProcessoModal(${processo.id}, '${window.escapeString(processo.nome)}', '${window.escapeString(processo.descricao || '')}', ${processo.ordem})" title="Editar processo">✏️</button>
            ${!isEssencial ? `<button class="btn-delete" onclick="excluirProcesso(${processo.id})" title="Excluir processo">🗑️</button>` : '<span class="processo-protegido" title="Processos do sistema não podem ser excluídos">🔒</span>'}
        </td>
    `;
    return row;
}

async function carregarProcessosDoItem(itemId) {
    console.log('Carregando processos do item:', itemId);
    const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_item_processos&item_id=${itemId}`);
    
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

// === DISPONIBILIZAR FUNÇÕES GLOBALMENTE ===
window.carregarPedidos = carregarPedidos;
window.carregarItens = carregarItens;
window.carregarItensParaSelecao = carregarItensParaSelecao;
window.carregarProcessos = carregarProcessos;
window.carregarProcessosList = carregarProcessosList;
window.verificarOrdensDuplicadas = verificarOrdensDuplicadas;
window.adicionarAlertaOrdem = adicionarAlertaOrdem;
window.criarLinhaProcesso = criarLinhaProcesso;
window.carregarProcessosDoItem = carregarProcessosDoItem;

console.log('Módulo Data carregado - Carregamento de dados v5.3');