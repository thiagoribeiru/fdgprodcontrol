// script.js - Sistema de Controle de Produção v0.5.4 - Versão Modular

// === NOVO SISTEMA MODULAR ===
// Este arquivo agora serve como carregador dos módulos

console.log('🚀 Iniciando Sistema de Controle de Produção v0.5.4 - Arquitetura Modular');

// === CONFIGURAÇÃO DOS MÓDULOS ===
const MODULOS = [
    'js/globals.js',     // Variáveis globais e funções compartilhadas (PRIMEIRO)
    'js/core.js',        // Módulo principal - inicialização, eventos
    'js/data.js',        // Carregamento de dados - pedidos, itens, processos
    'js/modals.js',      // Gerenciamento de modais
    'js/forms.js',       // Salvamento e formulários
    'js/actions.js',     // Ações e manipulações
    'js/details.js'      // Visualização de detalhes
];

// === FUNÇÃO DE CARREGAMENTO SEQUENCIAL ===
async function carregarModulos() {
    console.log('📦 Carregando módulos do sistema...');
    
    // Inicializar variáveis globais primeiro
    window.pedidoItens = window.pedidoItens || [];
    window.itensDisponiveis = window.itensDisponiveis || [];
    window.processosDisponiveis = window.processosDisponiveis || [];
    
    for (const modulo of MODULOS) {
        try {
            await carregarModulo(modulo);
            console.log(`✅ Módulo carregado: ${modulo}`);
            
            // Pequena pausa para garantir que o módulo seja processado
            await new Promise(resolve => setTimeout(resolve, 50));
            
        } catch (error) {
            console.error(`❌ Erro ao carregar módulo ${modulo}:`, error);
            // Mostrar fallback se o carregamento modular falhar
            mostrarFallbackModular();
            return;
        }
    }
    
    console.log('🎉 Todos os módulos carregados com sucesso!');
    console.log('📊 Arquitetura: JavaScript Modular v0.5.4');
    
    // Verificar se funções essenciais estão disponíveis
    verificarFuncoesEssenciais();
}

// === FUNÇÃO PARA CARREGAR UM MÓDULO ===
function carregarModulo(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = () => reject(new Error(`Falha ao carregar ${src}`));
        document.head.appendChild(script);
    });
}

// === FALLBACK PARA COMPATIBILIDADE ===
function mostrarFallbackModular() {
    console.warn('⚠️ Fallback ativado - usando sistema legado');
    
    // Mostrar mensagem na interface
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message warning';
    messageDiv.innerHTML = `
        <strong>⚠️ Modo de Compatibilidade</strong><br>
        O sistema está rodando em modo de compatibilidade. 
        Para melhor performance, certifique-se que todos os arquivos do diretório 'js/' estão disponíveis.
    `;
    document.body.insertBefore(messageDiv, document.body.firstChild);
    
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 10000);
}

// === VERIFICAÇÃO DE FUNÇÕES ESSENCIAIS ===
function verificarFuncoesEssenciais() {
    const funcoesEssenciais = [
        'inicializarSistema',
        'apiRequest', 
        'carregarPedidos',
        'mostrarMensagem',
        'atualizarTabelaItensPedido'
    ];
    
    const funcoesFaltando = funcoesEssenciais.filter(funcao => typeof window[funcao] !== 'function');
    
    if (funcoesFaltando.length > 0) {
        console.warn('⚠️ Funções não encontradas:', funcoesFaltando);
    } else {
        console.log('✅ Todas as funções essenciais estão disponíveis');
    }
}

// === VERIFICAR SE SISTEMA MODULAR ESTÁ DISPONÍVEL ===
function verificarSistemaModular() {
    // Verificar se o diretório js/ existe testando um arquivo
    return fetch('js/core.js', { method: 'HEAD' })
        .then(response => response.ok)
        .catch(() => false);
}

// === INICIALIZAÇÃO ===
document.addEventListener('DOMContentLoaded', async function() {
    console.log('🔧 Verificando sistema modular...');
    
    const sistemaModularDisponivel = await verificarSistemaModular();
    
    if (sistemaModularDisponivel) {
        console.log('✅ Sistema modular disponível - carregando módulos');
        await carregarModulos();
        
        // Inicializar o sistema APÓS todos os módulos carregados
        console.log('🚀 Inicializando sistema...');
        if (typeof window.inicializarSistema === 'function') {
            await window.inicializarSistema();
        } else {
            console.error('❌ Função inicializarSistema não encontrada');
        }
        
    } else {
        console.log('ℹ️ Sistema modular não disponível - usando fallback legado');
        mostrarFallbackModular();
        
        // Aqui você pode incluir o código legado ou uma versão simplificada
        // Por ora, vamos apenas mostrar uma mensagem
        console.log('📝 Para usar o sistema modular, certifique-se que:');
        console.log('   1. O diretório js/ existe');
        console.log('   2. Todos os módulos estão presentes');
        console.log('   3. O servidor permite acesso aos arquivos .js');
    }
});

// === INFORMAÇÕES DO SISTEMA ===
console.log(`
╔══════════════════════════════════════════════════════════════╗
║              SISTEMA DE CONTROLE DE PRODUÇÃO                ║
║                     Versão v0.5.4 Modular                     ║
╠══════════════════════════════════════════════════════════════╣
║ 🏗️ Arquitetura: JavaScript Modular                          ║
║ 📦 Módulos: 6 especializados                                ║
║ 🔧 Manutenção: Simplificada                                 ║
║ 📱 Compatibilidade: Moderna + Fallback                      ║
╚══════════════════════════════════════════════════════════════╝
`);