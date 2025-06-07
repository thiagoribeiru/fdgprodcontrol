// js/details.js - Módulo de Visualização de Detalhes v5.3

// === VISUALIZAÇÃO DE DETALHES ===
async function verItensPedido(pedidoId) {
    console.log('Visualizando detalhes do pedido:', pedidoId);
    const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_pedido_detalhado&pedido_id=${pedidoId}`);
    
    if (data && data.pedido) {
        window.criarModalDetalhePedido(data);
    }
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
                ${grupo.data_inicio ? `<div class="processo-dates">📅 Início: ${window.formatarDataHora(grupo.data_inicio)}</div>` : ''}
                ${grupo.data_conclusao ? `<div class="processo-dates">✅ Conclusão: ${window.formatarDataHora(grupo.data_conclusao)}</div>` : ''}
                ${grupo.observacoes ? `<div class="processo-observacoes">💬 ${grupo.observacoes}</div>` : ''}
            </div>
            <div class="processo-status-icon">
                ${window.getStatusIcon(grupo.status_geral)}
            </div>
        </div>
    `).join('');
}

async function agruparProcessosPorOrdemGlobal(processos) {
    try {
        const processosGlobais = await window.apiRequest(`${window.API_BASE_URL}?action=get_processos`);
        
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

async function updateGrupoProcessoStatus(processosIds, novoStatus) {
    try {
        const promises = processosIds.map(processo => 
            window.apiRequest(`${window.API_BASE_URL}?action=update_processo_status`, {
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
            window.mostrarMensagem(`Grupo de processos atualizado para "${window.getStatusLabel(novoStatus)}"`, 'success');
            
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
            window.mostrarMensagem(`Apenas ${sucessos} de ${processosIds.length} processos foram atualizados`, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao atualizar grupo de processos:', error);
        window.mostrarMensagem('Erro ao atualizar grupo de processos', 'error');
    }
}

// === DISPONIBILIZAR FUNÇÕES GLOBALMENTE ===
window.verItensPedido = verItensPedido;
window.renderizarProcessosAgrupados = renderizarProcessosAgrupados;
window.agruparProcessosPorOrdemGlobal = agruparProcessosPorOrdemGlobal;
window.updateGrupoProcessoStatus = updateGrupoProcessoStatus;

console.log('Módulo Details carregado - Visualização de detalhes v5.3');