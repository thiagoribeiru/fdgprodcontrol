// js/core.js - Módulo Principal do Sistema de Controle de Produção v5.3

async function inicializarSistema() {
    try {
        console.log('🔄 Inicializando sistema...');
        
        await testarAPI();
        
        // Aguardar um pouco para garantir que todos os módulos estejam prontos
        await new Promise(resolve => setTimeout(resolve, 100));
        
        if (document.getElementById('pedidosTableBody')) {
            console.log('📊 Carregando pedidos...');
            if (typeof window.carregarPedidos === 'function') {
                await window.carregarPedidos();
            } else {
                console.warn('⚠️ Função carregarPedidos não disponível ainda');
            }
        }
        
        configurarEventos();
        
        if (typeof window.configurarDataPadrao === 'function') {
            window.configurarDataPadrao();
        }
        
        console.log('✅ Sistema carregado com sucesso - JS Modular');
        
    } catch (error) {
        console.error('❌ Erro ao inicializar sistema:', error);
        window.mostrarMensagem('Erro ao inicializar o sistema. Verifique a conexão.', 'error');
    }
}

async function testarAPI() {
    try {
        const data = await window.apiRequest(`${window.API_BASE_URL}?action=test`);
        if (!data || data.error) {
            throw new Error('API não está respondendo');
        }
        console.log('API funcionando:', data);
    } catch (error) {
        console.error('Erro na API:', error);
    }
}

// === CONFIGURAÇÃO DE EVENTOS ===
function configurarEventos() {
    const formularios = [
        { id: 'addPedidoForm', handler: window.salvarPedido },
        { id: 'addItemForm', handler: window.salvarItem },
        { id: 'addItemToPedidoForm', handler: window.adicionarItemAoPedido },
        { id: 'addItemProcessoForm', handler: window.adicionarProcessoAoItem },
        { id: 'addProcessoForm', handler: window.salvarProcesso },
        { id: 'editProcessoForm', handler: window.atualizarProcesso }
    ];

    formularios.forEach(form => {
        const element = document.getElementById(form.id);
        if (element) {
            element.addEventListener('submit', function(e) {
                e.preventDefault();
                if (typeof form.handler === 'function') {
                    form.handler();
                }
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
                if (typeof window.verificarOrdemDisponivel === 'function') {
                    window.verificarOrdemDisponivel(element, input.feedbackId, input.isEdit);
                }
            });
        }
    });
}

// === EVENT LISTENERS GLOBAIS ===
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

// === DISPONIBILIZAR FUNÇÕES GLOBALMENTE ===
window.inicializarSistema = inicializarSistema;
window.testarAPI = testarAPI;
window.configurarEventos = configurarEventos;
window.configurarEventosOrdem = configurarEventosOrdem;

console.log('Módulo Core carregado - Sistema v5.3 Modular');