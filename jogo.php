<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Uno Local</title>
    <link rel="stylesheet" href="uno.css">
</head>
<body>

<div id="drag-layer"></div>
<div id="uno-overlay" class="escondido pointer-none"></div>
<div id="emoji-overlay" class="pointer-none"></div>

<div class="game-viewport">
    <div class="glass-card" id="tela-inicio">
        <h1>Uno Local 🃏</h1>
        <h2>Multiplayer via Wi-Fi</h2>
        <label class="label-jogador">Seu Nome:</label>
        <input type="text" id="nome-jogador" value="Léo" maxlength="15">
        
        <label class="label-jogador">Quantos Jogadores?</label>
        <select id="max-jogadores" class="input-select">
            <option value="2">2 Jogadores (Duelo)</option>
            <option value="3">3 Jogadores</option>
            <option value="4">4 Jogadores (Festa)</option>
        </select>

        <button class="btn-fenda" onclick="criarSala()">CRIAR SALA (Host) 🚀</button>
        <div class="divisor"><span>OU</span></div>
        <label class="label-jogador">Código da Sala:</label>
        <input type="text" id="codigo-sala" placeholder="1234" maxlength="4" style="text-align: center; letter-spacing: 5px; font-size: 1.2rem;">
        <button class="btn-fenda btn-secundario" onclick="entrarSala()">ENTRAR NA SALA 🚪</button>
    </div>

    <div class="glass-card escondido" id="tela-espera">
        <h1 id="espera-titulo">Sala Criada! ⏳</h1>
        <p class="subtext" id="espera-texto">Aguardando jogadores...</p>
        <div class="codigo-sala-display" id="display-codigo">----</div>
        <div class="loader"></div>
        <button class="btn-fenda btn-danger" onclick="sairDaSala()" style="margin-top: 20px;">Cancelar</button>
    </div>

    <div class="escondido" id="tela-jogo" style="width: 100%;">
        
        <div class="topo-jogo">
            <button class="btn-sair-mini" onclick="sairDaSala()">🚪 Desistir</button>
            <div id="aviso-sistema" class="aviso-sistema escondido"></div>
        </div>

        <div class="oponentes-container" id="oponentes-container"></div>

        <div class="mesa-central">
            <div class="monte-compra" id="monte-compra" onclick="comprarCarta()" title="Clique para comprar">
                <div class="carta-verso"></div>
                <span class="deck-count" id="deck-count">0</span>
            </div>
            <div class="monte-descarte" id="monte-descarte"></div>
        </div>

        <div class="meu-painel">
            <div class="barra-reacoes">
                <button class="btn-reacao" onclick="enviarReacao('😂')">😂</button>
                <button class="btn-reacao" onclick="enviarReacao('😡')">😡</button>
                <button class="btn-reacao" onclick="enviarReacao('😭')">😭</button>
                <button class="btn-reacao" onclick="enviarReacao('😎')">😎</button>
            </div>

            <div style="display:flex; gap:10px; align-items:center; justify-content:center; margin-bottom:10px;">
                <div class="aviso-turno" id="aviso-turno">Aguardando...</div>
                <button id="btn-passar" class="btn-passar escondido" onclick="passarVez()">Passar Vez ⏭️</button>
                <button id="btn-uno" class="btn-uno escondido" onclick="gritarUnoManual()">UNO! 🔥</button>
            </div>
            
            <div class="minha-mao-container">
                <div class="minha-mao" id="minha-mao"></div>
            </div>
        </div>
    </div>

    <div class="glass-card escondido" id="tela-vitoria" style="text-align: center;">
        <h1 id="titulo-vitoria" style="color: #fbbf24; font-size: 4rem;">🏆</h1>
        <h2 id="nome-vencedor" style="color: #fff;">Jogador Venceu!</h2>
        <button class="btn-fenda" onclick="location.reload()" style="margin-top: 20px;">Jogar Novamente 🔄</button>
    </div>
</div>

<div class="modal-overlay escondido" id="modal-cor">
    <div class="modal-content">
        <h3>Escolha a Cor:</h3>
        <div class="cores-grid">
            <button class="btn-cor cor-vermelho" onclick="escolherCor('vermelho')"></button>
            <button class="btn-cor cor-azul" onclick="escolherCor('azul')"></button>
            <button class="btn-cor cor-verde" onclick="escolherCor('verde')"></button>
            <button class="btn-cor cor-amarelo" onclick="escolherCor('amarelo')"></button>
        </div>
    </div>
</div>

<script>
let meuNome = "", codigoSala = "", pollingInterval = null, meuId = "", estadoJogoAtual = null;
let podeJogar = false, corPendente = null, indexPendente = null, dragCard = null;
let ultimoLogOffline = "";
let isDragging = false;
let lastUnoTime = 0;
let lastReacaoTime = 0;

function criarSala() {
    meuNome = document.getElementById('nome-jogador').value.trim() || 'Host';
    const maxJogadores = document.getElementById('max-jogadores').value;
    
    fetch(`api_sala.php?acao=criar&nome=${encodeURIComponent(meuNome)}&max=${maxJogadores}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                codigoSala = data.sala; meuId = "p1";
                document.getElementById('tela-inicio').classList.add('escondido');
                document.getElementById('tela-espera').classList.remove('escondido');
                document.getElementById('display-codigo').innerText = codigoSala;
                document.getElementById('espera-texto').innerText = `Aguardando ${maxJogadores - 1} jogador(es)...`;
                iniciarPolling();
            }
        });
}

function entrarSala() {
    meuNome = document.getElementById('nome-jogador').value.trim() || 'Convidado';
    codigoSala = document.getElementById('codigo-sala').value.trim();
    
    if (codigoSala.length !== 4) return alert('O código deve ter 4 dígitos!');
    
    fetch(`api_sala.php?acao=entrar&sala=${codigoSala}&nome=${encodeURIComponent(meuNome)}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                meuId = data.jogador;
                document.getElementById('tela-inicio').classList.add('escondido');
                document.getElementById('tela-espera').classList.remove('escondido');
                document.getElementById('display-codigo').innerText = codigoSala;
                document.getElementById('espera-titulo').innerText = "Conectado!";
                document.getElementById('espera-texto').innerText = "Aguardando o Host iniciar...";
                iniciarPolling();
            } else {
                alert(data.msg);
            }
        });
}

async function sairDaSala() {
    if(confirm('Tem certeza que deseja sair da partida?')) {
        clearInterval(pollingInterval);
        if (codigoSala && meuId) {
            try { await fetch(`api_sala.php?acao=sair&sala=${codigoSala}&jogador=${meuId}`); } catch(e) {}
        }
        location.reload();
    }
}

function iniciarPolling() {
    if(pollingInterval) clearInterval(pollingInterval);
    
    pollingInterval = setInterval(() => {
        if (isDragging) return;

        fetch(`api_sala.php?acao=sync&sala=${codigoSala}&jogador=${meuId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    estadoJogoAtual = data.dados;
                    
                    if (data.dados.status === 'jogando') {
                        if (!document.getElementById('tela-espera').classList.contains('escondido')) {
                            document.getElementById('tela-espera').classList.add('escondido');
                            document.getElementById('tela-jogo').classList.remove('escondido');
                        }
                        atualizarInterface(estadoJogoAtual);
                    }
                    
                    if (data.dados.status === 'fim') {
                        clearInterval(pollingInterval);
                        document.getElementById('tela-jogo').classList.add('escondido');
                        document.getElementById('tela-espera').classList.add('escondido');
                        document.getElementById('tela-vitoria').classList.remove('escondido');
                        
                        if(data.dados.vencedor && data.dados.jogadores[data.dados.vencedor]) {
                            document.getElementById('nome-vencedor').innerText = data.dados.jogadores[data.dados.vencedor].nome + " Venceu!";
                        } else {
                            document.getElementById('nome-vencedor').innerText = "Fim de Jogo!";
                        }
                    }
                }
            });
    }, 1500); 
}

function atualizarInterface(dados) {
    document.getElementById('drag-layer').innerHTML = '';

    if (dados.evento_global && dados.evento_global.tempo > lastUnoTime) {
        lastUnoTime = dados.evento_global.tempo;
        mostrarAnimacaoUno(dados.evento_global.nome);
    }

    if (dados.reacao && dados.reacao.tempo > lastReacaoTime) {
        lastReacaoTime = dados.reacao.tempo;
        mostrarAnimacaoEmoji(dados.reacao.emoji);
    }

    const avisoSistema = document.getElementById('aviso-sistema');
    if (dados.log_offline && dados.log_offline !== ultimoLogOffline) {
        ultimoLogOffline = dados.log_offline;
        avisoSistema.innerText = dados.log_offline;
        avisoSistema.classList.remove('escondido');
        setTimeout(() => avisoSistema.classList.add('escondido'), 4000);
    }

    const oponentesDiv = document.getElementById('oponentes-container');
    oponentesDiv.innerHTML = '';
    dados.ordem.forEach(id => {
        if (id !== meuId) {
            const op = dados.jogadores[id];
            const statusClass = op.online ? '' : 'offline';
            const statusIcon = op.online ? '🟢' : '🔴';
            oponentesDiv.innerHTML += `
                <div class="oponente-card ${statusClass} ${dados.turno_atual === id ? 'turno-ativo' : ''}">
                    ${statusIcon} <b>${op.nome}</b> (🂠 ${op.mao.length})
                </div>`;
        }
    });

    document.getElementById('deck-count').innerText = dados.monte_compra.length;
    const cartaTopo = dados.monte_descarte[dados.monte_descarte.length - 1];
    const descarteDiv = document.getElementById('monte-descarte');
    if (cartaTopo) {
        descarteDiv.innerHTML = `<div class="carta-uno cor-${cartaTopo.cor}"><span class="valor-centro">${traduzirValor(cartaTopo.valor)}</span></div>`;
        descarteDiv.style.boxShadow = `0 0 30px var(--cor-${dados.cor_atual})`;
    }

    podeJogar = (dados.turno_atual === meuId);
    const aviso = document.getElementById('aviso-turno');
    aviso.innerText = podeJogar ? "Sua Vez!" : "Aguardando...";
    aviso.className = `aviso-turno ${podeJogar ? 'bg-verde' : 'bg-vermelho'}`;

    const btnPassar = document.getElementById('btn-passar');
    if (podeJogar && dados.comprou_agora) {
        btnPassar.classList.remove('escondido');
    } else {
        btnPassar.classList.add('escondido');
    }

    const minhaMao = dados.jogadores[meuId].mao;
    const btnUno = document.getElementById('btn-uno');
    
    // Se a mão chegar a 1, o botão de Uno aparece, mas sem PUNIÇÃO no backend
    if (minhaMao.length === 1) {
        btnUno.classList.remove('escondido');
    } else {
        btnUno.classList.add('escondido');
    }

    const minhaMaoDiv = document.getElementById('minha-mao');
    minhaMaoDiv.innerHTML = '';
    minhaMao.forEach((carta, index) => {
        const cardEl = document.createElement('div');
        cardEl.className = `carta-uno cor-${carta.cor} ${!podeJogar ? 'bloqueada' : ''}`;
        cardEl.innerHTML = `<span class="valor-canto">${traduzirValor(carta.valor)}</span><span class="valor-centro">${traduzirValor(carta.valor)}</span>`;
        cardEl.addEventListener('pointerdown', (e) => startDrag(e, cardEl, index));
        minhaMaoDiv.appendChild(cardEl);
    });
}

function mostrarAnimacaoUno(nome) {
    const overlay = document.getElementById('uno-overlay');
    overlay.innerHTML = `<div class="uno-text-anim">UNO!<br><span class="uno-nome">${nome}</span></div>`;
    overlay.classList.remove('escondido');
    setTimeout(() => overlay.classList.add('escondido'), 2500);
}

function mostrarAnimacaoEmoji(emoji) {
    const overlay = document.getElementById('emoji-overlay');
    const el = document.createElement('div');
    el.className = 'emoji-flutuante';
    el.innerText = emoji;
    
    const x = Math.random() * (window.innerWidth - 100) + 50;
    el.style.left = `${x}px`;
    el.style.bottom = `20%`;
    
    overlay.appendChild(el);
    setTimeout(() => el.remove(), 2000);
}

function enviarReacao(emoji) {
    fetch(`api_sala.php?acao=reagir&sala=${codigoSala}&jogador=${meuId}&emoji=${emoji}`);
}

function gritarUnoManual() {
    const btn = document.getElementById('btn-uno');
    btn.style.transform = 'scale(1.5)';
    btn.innerText = 'GRITEI! 🗣️';
    setTimeout(() => {
        btn.style.transform = 'scale(1)';
        btn.innerText = 'UNO! 🔥';
    }, 800);
}

function passarVez() {
    document.getElementById('btn-passar').classList.add('escondido');
    fetch(`api_sala.php?acao=passar_vez&sala=${codigoSala}&jogador=${meuId}`)
        .then(() => fetch(`api_sala.php?acao=sync&sala=${codigoSala}&jogador=${meuId}`))
        .then(r => r.json()).then(d => {
            estadoJogoAtual = d.dados;
            atualizarInterface(estadoJogoAtual);
        });
}

function traduzirValor(valor) {
    if (valor === 'pular') return '🚫';
    if (valor === 'inverter') return '🔄';
    if (valor === '+2') return '+2';
    if (valor === 'coringa') return '🌈';
    if (valor === '+4') return '+4';
    return valor;
}

function comprarCarta() {
    if (!podeJogar) return;
    
    const cartaTopo = estadoJogoAtual.monte_descarte[estadoJogoAtual.monte_descarte.length - 1];
    if (cartaTopo && cartaTopo.valor === '+4') {
        const temResposta = estadoJogoAtual.jogadores[meuId].mao.some(c => c.valor === '+2' || c.valor === '+4');
        if (temResposta) {
            if(!confirm("Você tem um +2 ou +4! Tem certeza que quer comprar em vez de rebater?")) return;
        }
    }

    fetch(`api_sala.php?acao=comprar&sala=${codigoSala}&jogador=${meuId}`)
        .then(r => r.json()).then(data => {
            if (data.status === 'ok') {
                fetch(`api_sala.php?acao=sync&sala=${codigoSala}&jogador=${meuId}`)
                    .then(r => r.json()).then(d => {
                        estadoJogoAtual = d.dados;
                        atualizarInterface(estadoJogoAtual);
                    });
            }
        });
}

let dragStartX = 0, dragStartY = 0, dragIndex = -1;

function startDrag(e, element, index) {
    if (!podeJogar) return;
    e.preventDefault();
    e.stopPropagation();
    
    isDragging = true; dragCard = element; dragIndex = index;
    const rect = element.getBoundingClientRect();
    
    document.getElementById('drag-layer').appendChild(element);
    element.style.position = 'fixed';
    element.style.left = rect.left + 'px';
    element.style.top = rect.top + 'px';
    element.style.margin = '0';
    element.style.zIndex = 9999;
    element.style.transition = 'none';

    dragStartX = e.clientX; dragStartY = e.clientY;
    window.addEventListener('pointermove', moveDrag, { passive: false });
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);
}

function moveDrag(e) {
    if (!dragCard) return;
    e.preventDefault();
    const dx = e.clientX - dragStartX;
    const dy = e.clientY - dragStartY;
    
    dragCard.style.transform = `translate(${dx}px, ${dy}px) scale(1.1) rotate(${dx * 0.05}deg)`;
    dragCard.style.boxShadow = "0 30px 50px rgba(0,0,0,0.9)";

    if (dy < -50) {
        document.getElementById('monte-descarte').classList.add('zona-ativa');
    } else {
        document.getElementById('monte-descarte').classList.remove('zona-ativa');
    }
}

function endDrag(e) {
    if (!dragCard) return;
    window.removeEventListener('pointermove', moveDrag);
    window.removeEventListener('pointerup', endDrag);
    window.removeEventListener('pointercancel', endDrag);
    
    const dx = e.clientX - dragStartX;
    const dy = e.clientY - dragStartY;

    document.getElementById('monte-descarte').classList.remove('zona-ativa');

    const discardRect = document.getElementById('monte-descarte').getBoundingClientRect();
    const cardRect = dragCard.getBoundingClientRect();
    const isOverDiscard = !(cardRect.right < discardRect.left || cardRect.left > discardRect.right || cardRect.bottom < discardRect.top || cardRect.top > discardRect.bottom);
    const jogouParaCima = dy < -80;

    if (!isOverDiscard && !jogouParaCima) {
        dragCard.style.transition = 'transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        dragCard.style.transform = 'translate(0px, 0px) scale(1) rotate(0deg)';
        setTimeout(() => { 
            if(dragCard) dragCard.style = ''; 
            dragCard = null; 
            isDragging = false; 
            atualizarInterface(estadoJogoAtual); 
        }, 300);
        return;
    }

    const cartaInfo = estadoJogoAtual.jogadores[meuId].mao[dragIndex];
    if (cartaInfo && cartaInfo.cor === 'preto') {
        corPendente = dragCard; indexPendente = dragIndex;
        document.getElementById('modal-cor').classList.remove('escondido');
        corPendente.style.transition = 'transform 0.3s ease';
        corPendente.style.transform = 'translate(0px, 0px) scale(1)';
    } else {
        executarJogada(dragIndex, null);
    }
}

function escolherCor(cor) {
    document.getElementById('modal-cor').classList.add('escondido');
    executarJogada(indexPendente, cor);
    corPendente = null;
}

function executarJogada(index, cor) {
    fetch(`api_sala.php?acao=jogar&sala=${codigoSala}&jogador=${meuId}&index=${index}&cor=${cor}`)
        .then(r => r.json()).then(data => {
            isDragging = false; 
            
            if (data.status === 'erro') {
                if (dragCard) {
                    dragCard.classList.add('erro-shake');
                    dragCard.style.transition = 'transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    dragCard.style.transform = 'translate(0px, 0px) scale(1) rotate(0deg)';
                    setTimeout(() => {
                        if(dragCard) { dragCard.style = ''; dragCard.classList.remove('erro-shake'); }
                        dragCard = null;
                        fetch(`api_sala.php?acao=sync&sala=${codigoSala}&jogador=${meuId}`)
                            .then(r => r.json()).then(d => { estadoJogoAtual = d.dados; atualizarInterface(estadoJogoAtual); });
                    }, 400);
                }
            } else {
                if(dragCard) { dragCard.style = ''; }
                dragCard = null;
                fetch(`api_sala.php?acao=sync&sala=${codigoSala}&jogador=${meuId}`)
                    .then(r => r.json()).then(d => { estadoJogoAtual = d.dados; atualizarInterface(estadoJogoAtual); });
            }
        });
}
</script>
</body>
</html>