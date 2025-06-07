// js/actions.js - Módulo de Ações e Manipulações v5.3

// === GERENCIAMENTO DE TABS ===
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

// === FUNÇÕES DE EXCLUSÃO ===
async function excluirPedido(pedidoId) {
    if (!confirm('Tem certeza que deseja excluir este pedido? Todos os itens associados também serão removidos.')) {
        return;
    }
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=delete_pedido&id=${pedidoId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        window.mostrarMensagem('Pedido excluído com sucesso!', 'success');
        window.carregarPedidos();
    } else {
        window.mostrarMensagem(resultado?.error || 'Erro ao excluir pedido', 'error');
    }
}

async function excluirItem(itemId) {
    if (!confirm('Tem certeza que deseja excluir este item?\n\nAtenção: Itens que estão sendo usados em pedidos não podem ser excluídos.\nTodos os processos (receitas) associados também serão removidos.')) {
        return;
    }
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=delete_item&id=${itemId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        let mensagem = 'Item excluído com sucesso!';
        if (resultado.processos_removidos > 0) {
            mensagem += `\n${resultado.processos_removidos} processo(s) da receita também foram removidos.`;
        }
        window.mostrarMensagem(mensagem, 'success');
        window.carregarItens();
    } else if (resultado && resultado.error) {
        let mensagem = resultado.error;
        if (resultado.details) {
            mensagem += `\n\n${resultado.details}`;
        }
        if (resultado.count) {
            mensagem += `\n\nTotal de usos: ${resultado.count}`;
        }
        window.mostrarMensagem(mensagem, 'error');
    } else {
        window.mostrarMensagem('Erro ao excluir item', 'error');
    }
}

async function excluirProcesso(processoId) {
    if (!confirm('Tem certeza que deseja excluir este processo?\n\nAtenção: Processos que estão sendo usados em receitas de itens ou pedidos não podem ser excluídos.')) {
        return;
    }
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=delete_processo&id=${processoId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        window.mostrarMensagem('Processo excluído com sucesso!', 'success');
        window.carregarProcessosList();
        window.carregarProcessos();
    } else if (resultado && resultado.error) {
        let mensagem = resultado.error;
        if (resultado.details) {
            mensagem += `\n\n${resultado.details}`;
        }
        if (resultado.count) {
            mensagem += `\n\nTotal de usos: ${resultado.count}`;
        }
        window.mostrarMensagem(mensagem, 'error');
    } else {
        window.mostrarMensagem('Erro ao excluir processo', 'error');
    }
}

async function removerProcessoDoItem(processoId) {
    if (!confirm('Tem certeza que deseja remover este processo?')) {
        return;
    }
    
    const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=delete_item_processo&id=${processoId}`, {
        method: 'DELETE'
    });
    
    if (resultado && resultado.success) {
        window.mostrarMensagem('Processo removido com sucesso!', 'success');
        const itemId = document.getElementById('currentItemId').value;
        window.carregarProcessosDoItem(itemId);
    } else {
        window.mostrarMensagem('Erro ao remover processo', 'error');
    }
}

// === AÇÕES DE PEDIDOS ===
async function alterarProcessoPedido(pedidoId, processoAtual) {
    const processos = ['corte', 'personalização', 'produção', 'expedição'];
    const processoAtualIndex = processos.indexOf(processoAtual);
    
    if (processoAtualIndex < processos.length - 1) {
        const novoProcesso = processos[processoAtualIndex + 1];
        
        if (confirm(`Avançar processo de "${window.capitalizeFirst(processoAtual)}" para "${window.capitalizeFirst(novoProcesso)}"?`)) {
            const resultado = await window.apiRequest(`${window.API_BASE_URL}?action=update_processo_pedido&id=${pedidoId}`, {
                method: 'PUT',
                body: JSON.stringify({
                    processo_atual: novoProcesso
                })
            });
            
            if (resultado && resultado.success) {
                window.mostrarMensagem('Processo atualizado com sucesso!', 'success');
                window.carregarPedidos();
            } else {
                window.mostrarMensagem(resultado?.error || 'Erro ao atualizar processo', 'error');
            }
        }
    } else {
        window.mostrarMensagem('Este pedido já está no último processo!', 'error');
    }
}

function editarPedido(pedidoId) {
    window.mostrarMensagem('Funcionalidade de edição em desenvolvimento', 'error');
}

// === ADIÇÃO DE ITENS A PEDIDOS EXISTENTES ===
async function adicionarItemAoPedidoExistente(pedidoId) {
    window.closeViewDetalhePedidoModal();
    
    const modal = document.getElementById('selectItemModal');
    if (modal) {
        modal.style.display = 'block';
        await window.carregarItensParaSelecao();
        
        window.tempPedidoId = pedidoId;
        window.originalOpenAddItemToPedidoModal = window.openAddItemToPedidoModal;
        window.openAddItemToPedidoModal = function(itemId, itemNome) {
            window.openAddItemToPedidoExistenteModal(itemId, itemNome, pedidoId);
        };
    }
}

// === VERIFICAÇÃO DE ORDEM ===
function verificarOrdemDisponivel(input, feedbackId, isEdit = false) {
    const feedbackElement = document.getElementById(feedbackId);
    if (!feedbackElement) return;
    
    if (!input.value || !window.processosDisponiveis || window.processosDisponiveis.length === 0) {
        limparFeedbackOrdem(feedbackId);
        return;
    }
    
    const ordemEscolhida = parseInt(input.value);
    let mensagem = '';
    let tipo = 'info';
    
    if (isEdit) {
        const processoId = document.getElementById('editProcessoId').value;
        const processoAtual = window.processosDisponiveis.find(p => p.id == processoId);
        const processoConflito = window.processosDisponiveis.find(p => p.ordem === ordemEscolhida && p.id != processoId);
        
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
        const processoConflito = window.processosDisponiveis.find(p => p.ordem === ordemEscolhida);
        
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
        const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_processos`);
        
        if (data && Array.isArray(data)) {
            const ordemsDuplicadas = window.verificarOrdensDuplicadas(data);
            
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
                window.mostrarMensagem('✅ Ordem dos processos está correta!', 'success');
                atualizarResultadoVerificacao('✅ Ordem dos processos está correta! Nenhum problema encontrado.');
            }
        }
    } catch (error) {
        console.error('Erro ao verificar ordem:', error);
        window.mostrarMensagem('Erro ao verificar ordem dos processos', 'error');
    }
}

async function corrigirOrdemProcessos() {
    console.log('Corrigindo ordem dos processos...');
    
    try {
        const data = await window.apiRequest(`${window.API_BASE_URL}?action=get_processos`);
        
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
                    const updatePromise = window.apiRequest(`${window.API_BASE_URL}?action=update_processo&id=${processo.id}`, {
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
                window.mostrarMensagem(mensagem, 'success');
                atualizarResultadoVerificacao(`✅ ${mensagem}`);
                
                await window.carregarProcessosList();
                await window.carregarProcessos();
                
            } else {
                const mensagem = '✅ Numeração já estava correta. Nenhum ajuste necessário.';
                window.mostrarMensagem(mensagem, 'success');
                atualizarResultadoVerificacao(mensagem);
            }
        }
    } catch (error) {
        console.error('Erro ao corrigir ordem:', error);
        window.mostrarMensagem('Erro ao corrigir ordem dos processos', 'error');
    }
}

function atualizarResultadoVerificacao(mensagem) {
    const elemento = document.getElementById('resultadoVerificacao');
    if (elemento) {
        elemento.innerHTML = `<div class="verification-result-content">${mensagem}</div>`;
        elemento.style.display = 'block';
    }
}

// === DISPONIBILIZAR FUNÇÕES GLOBALMENTE ===
window.showTab = showTab;
window.excluirPedido = excluirPedido;
window.excluirItem = excluirItem;
window.excluirProcesso = excluirProcesso;
window.removerProcessoDoItem = removerProcessoDoItem;
window.alterarProcessoPedido = alterarProcessoPedido;
window.editarPedido = editarPedido;
window.adicionarItemAoPedidoExistente = adicionarItemAoPedidoExistente;
window.verificarOrdemDisponivel = verificarOrdemDisponivel;
window.mostrarFeedbackOrdem = mostrarFeedbackOrdem;
window.limparFeedbackOrdem = limparFeedbackOrdem;
window.verificarOrdemProcessos = verificarOrdemProcessos;
window.corrigirOrdemProcessos = corrigirOrdemProcessos;
window.atualizarResultadoVerificacao = atualizarResultadoVerificacao;

console.log('Módulo Actions carregado - Ações e manipulações v5.3');