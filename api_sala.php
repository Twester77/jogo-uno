<?php
header('Content-Type: application/json');
$pasta_salas = __DIR__ . '/salas/';

if (!is_dir($pasta_salas)) mkdir($pasta_salas, 0777, true);
$acao = $_GET['acao'] ?? '';

foreach (glob($pasta_salas . "sala_*.json") as $file) {
    if (filemtime($file) < time() - 7200) unlink($file);
}

function gerarBaralhoUno() {
    $baralho = [];
    $cores = ['vermelho', 'azul', 'verde', 'amarelo'];
    foreach ($cores as $cor) {
        $baralho[] = ['cor' => $cor, 'valor' => '0'];
        for ($i = 1; $i <= 9; $i++) {
            $baralho[] = ['cor' => $cor, 'valor' => (string)$i];
            $baralho[] = ['cor' => $cor, 'valor' => (string)$i];
        }
        foreach (['pular', 'inverter', '+2'] as $acao_esp) {
            $baralho[] = ['cor' => $cor, 'valor' => $acao_esp];
            $baralho[] = ['cor' => $cor, 'valor' => $acao_esp];
        }
    }
    for ($i = 0; $i < 4; $i++) {
        $baralho[] = ['cor' => 'preto', 'valor' => 'coringa'];
        $baralho[] = ['cor' => 'preto', 'valor' => '+4'];
    }
    shuffle($baralho);
    return $baralho;
}

function getProximoJogador($dados, $atual_id, $pulos = 0) {
    $ordem = $dados['ordem'];
    $total = count($ordem);
    if ($total === 0) return null;
    
    $index = array_search($atual_id, $ordem);
    if ($index === false) $index = 0;

    $dir = $dados['direcao'];
    $passos = 1 + $pulos;
    $novo_index = $index + ($passos * $dir);
    
    while ($novo_index >= $total) $novo_index -= $total;
    while ($novo_index < 0) $novo_index += $total;
    
    return $ordem[$novo_index];
}

function comprarCartaSegura(&$dados, $jogador_id) {
    if (empty($dados['monte_compra'])) {
        if (count($dados['monte_descarte']) > 1) {
            $ultima = array_pop($dados['monte_descarte']);
            $dados['monte_compra'] = $dados['monte_descarte'];
            shuffle($dados['monte_compra']);
            $dados['monte_descarte'] = [$ultima];
        }
    }
    if (!empty($dados['monte_compra'])) {
        $dados['jogadores'][$jogador_id]['mao'][] = array_pop($dados['monte_compra']);
    }
}

if ($acao === 'criar') {
    $codigo = rand(1000, 9999);
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';
    while (file_exists($arquivo)) {
        $codigo = rand(1000, 9999);
        $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';
    }

    $estado_inicial = [
        'id' => $codigo, 'status' => 'aguardando', 'max_jogadores' => intval($_GET['max'] ?? 2),
        'jogadores' => [
            'p1' => ['nome' => $_GET['nome'] ?? 'Jogador 1', 'mao' => [], 'last_seen' => time()]
        ],
        'ordem' => ['p1'],
        'turno_atual' => 'p1', 'direcao' => 1,
        'monte_compra' => [], 'monte_descarte' => [], 'cor_atual' => 'vermelho',
        'vencedor' => null, 'ultima_acao' => time(), 'log_offline' => '', 'comprou_agora' => false,
        'evento_global' => null, 'reacao' => null
    ];
    file_put_contents($arquivo, json_encode($estado_inicial));
    echo json_encode(['status' => 'ok', 'sala' => $codigo]);
    exit;
}

if ($acao === 'entrar') {
    $codigo = $_GET['sala'] ?? '';
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';

    if (!file_exists($arquivo)) { echo json_encode(['status' => 'erro', 'msg' => 'Sala não encontrada.']); exit; }
    $dados = json_decode(file_get_contents($arquivo), true);
    
    if (count($dados['jogadores']) >= $dados['max_jogadores']) { echo json_encode(['status' => 'erro', 'msg' => 'Sala cheia.']); exit; }

    $novo_id = 'p' . (count($dados['jogadores']) + 1);
    $dados['jogadores'][$novo_id] = ['nome' => $_GET['nome'] ?? 'Jogador', 'mao' => [], 'last_seen' => time()];
    $dados['ordem'][] = $novo_id;

    if (count($dados['jogadores']) === $dados['max_jogadores']) {
        $baralho = gerarBaralhoUno();
        foreach ($dados['ordem'] as $id) {
            $dados['jogadores'][$id]['mao'] = array_splice($baralho, 0, 7);
        }
        $primeira_carta = array_pop($baralho);
        while ($primeira_carta['cor'] == 'preto') { array_unshift($baralho, $primeira_carta); $primeira_carta = array_pop($baralho); }
        
        $dados['monte_compra'] = $baralho;
        $dados['monte_descarte'] = [$primeira_carta];
        $dados['cor_atual'] = $primeira_carta['cor'];
        $dados['status'] = 'jogando';
    }

    file_put_contents($arquivo, json_encode($dados));
    echo json_encode(['status' => 'ok', 'sala' => $codigo, 'jogador' => $novo_id]);
    exit;
}

if ($acao === 'comprar') {
    $codigo = $_GET['sala'] ?? '';
    $quem_jogou = $_GET['jogador'] ?? 'p1';
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';

    if (!file_exists($arquivo)) { echo json_encode(['status' => 'erro']); exit; }
    $dados = json_decode(file_get_contents($arquivo), true);

    if ($dados['turno_atual'] !== $quem_jogou || $dados['status'] !== 'jogando' || $dados['comprou_agora']) { echo json_encode(['status' => 'erro']); exit; }

    comprarCartaSegura($dados, $quem_jogou);
    $dados['comprou_agora'] = true;
    $dados['ultima_acao'] = time();

    file_put_contents($arquivo, json_encode($dados));
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($acao === 'passar_vez') {
    $codigo = $_GET['sala'] ?? '';
    $quem_jogou = $_GET['jogador'] ?? 'p1';
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';

    if (!file_exists($arquivo)) { echo json_encode(['status' => 'erro']); exit; }
    $dados = json_decode(file_get_contents($arquivo), true);

    if ($dados['turno_atual'] !== $quem_jogou || $dados['status'] !== 'jogando' || !$dados['comprou_agora']) { echo json_encode(['status' => 'erro']); exit; }

    $dados['turno_atual'] = getProximoJogador($dados, $quem_jogou, 0);
    $dados['comprou_agora'] = false;
    $dados['ultima_acao'] = time();

    file_put_contents($arquivo, json_encode($dados));
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($acao === 'reagir') {
    $codigo = $_GET['sala'] ?? '';
    $quem = $_GET['jogador'] ?? '';
    $emoji = $_GET['emoji'] ?? '😂';
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';

    if (file_exists($arquivo)) {
        $dados = json_decode(file_get_contents($arquivo), true);
        $dados['reacao'] = ['quem' => $quem, 'emoji' => $emoji, 'tempo' => time()];
        file_put_contents($arquivo, json_encode($dados));
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($acao === 'sair') {
    $codigo = $_GET['sala'] ?? '';
    $quem_saiu = $_GET['jogador'] ?? 'p1';
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';

    if (file_exists($arquivo)) {
        $dados = json_decode(file_get_contents($arquivo), true);
        $dados['ordem'] = array_values(array_diff($dados['ordem'], [$quem_saiu]));
        unset($dados['jogadores'][$quem_saiu]);

        if (count($dados['ordem']) < 2) {
            $dados['status'] = 'fim';
            $dados['vencedor'] = count($dados['ordem']) === 1 ? $dados['ordem'][0] : null;
        } else if ($dados['turno_atual'] === $quem_saiu) {
            $dados['turno_atual'] = $dados['ordem'][0]; 
            $dados['comprou_agora'] = false;
        }

        file_put_contents($arquivo, json_encode($dados));
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($acao === 'jogar') {
    $codigo = $_GET['sala'] ?? '';
    $quem_jogou = $_GET['jogador'] ?? 'p1';
    $index_carta = intval($_GET['index'] ?? -1);
    $cor_escolhida = $_GET['cor'] ?? null;
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';

    if (!file_exists($arquivo)) { echo json_encode(['status' => 'erro']); exit; }
    $dados = json_decode(file_get_contents($arquivo), true);

    if ($dados['turno_atual'] !== $quem_jogou || $dados['status'] !== 'jogando') { echo json_encode(['status' => 'erro']); exit; }

    $carta_jogada = $dados['jogadores'][$quem_jogou]['mao'][$index_carta] ?? null;
    if (!$carta_jogada) { echo json_encode(['status' => 'erro']); exit; }
    
    $carta_topo = end($dados['monte_descarte']);

    $pode_jogar = false;
    if ($carta_topo['valor'] === '+4' && ($carta_jogada['valor'] === '+2' || $carta_jogada['valor'] === '+4')) $pode_jogar = true;
    elseif ($carta_topo['valor'] === '+2' && $carta_jogada['valor'] === '+2') $pode_jogar = true;
    elseif ($carta_jogada['cor'] === 'preto') $pode_jogar = true; 
    elseif ($carta_jogada['cor'] === $dados['cor_atual']) $pode_jogar = true; 
    elseif ($carta_jogada['valor'] === $carta_topo['valor']) $pode_jogar = true; 

    if (!$pode_jogar) { echo json_encode(['status' => 'erro']); exit; }

    array_splice($dados['jogadores'][$quem_jogou]['mao'], $index_carta, 1);
    $dados['monte_descarte'][] = $carta_jogada;
    
    if ($carta_jogada['cor'] === 'preto' && $cor_escolhida) $dados['cor_atual'] = $cor_escolhida;
    else if ($carta_jogada['cor'] !== 'preto') $dados['cor_atual'] = $carta_jogada['cor'];

    $dados['comprou_agora'] = false;

    // Se zerou a mão, ganhou
    if (count($dados['jogadores'][$quem_jogou]['mao']) === 0) {
        $dados['status'] = 'fim';
        $dados['vencedor'] = $quem_jogou;
        file_put_contents($arquivo, json_encode($dados));
        echo json_encode(['status' => 'ok']); exit;
    }

    // 🔥 GATILHO VISUAL DO UNO (Apenas avisa o frontend para fazer a animação, sem punição)
    if (count($dados['jogadores'][$quem_jogou]['mao']) === 1) {
        $dados['evento_global'] = ['tipo' => 'uno', 'nome' => $dados['jogadores'][$quem_jogou]['nome'], 'tempo' => time()];
    }

    $total_jogadores = count($dados['ordem']);
    
    if ($carta_jogada['valor'] === 'inverter') {
        if ($total_jogadores > 2) $dados['direcao'] *= -1;
        else $carta_jogada['valor'] = 'pular';
    }

    $proximo_jogador = getProximoJogador($dados, $quem_jogou, 0);
    if ($carta_jogada['valor'] === 'pular') $proximo_jogador = getProximoJogador($dados, $proximo_jogador, 0);

    $cartas_para_comprar = 0;
    if ($carta_jogada['valor'] === '+2') $cartas_para_comprar = 2;
    if ($carta_jogada['valor'] === '+4') $cartas_para_comprar = 4;

    if ($cartas_para_comprar > 0) {
        for($i=0; $i<$cartas_para_comprar; $i++) {
            comprarCartaSegura($dados, $proximo_jogador);
        }
        $proximo_jogador = getProximoJogador($dados, $proximo_jogador, 0);
    }

    $dados['turno_atual'] = $proximo_jogador;
    $dados['ultima_acao'] = time();

    file_put_contents($arquivo, json_encode($dados));
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($acao === 'sync') {
    $codigo = $_GET['sala'] ?? '';
    $meu_id = $_GET['jogador'] ?? '';
    $arquivo = $pasta_salas . 'sala_' . $codigo . '.json';
    
    if (file_exists($arquivo)) {
        $dados = json_decode(file_get_contents($arquivo), true);
        $mudou = false;

        if (isset($dados['jogadores'][$meu_id])) {
            $dados['jogadores'][$meu_id]['last_seen'] = time();
            $mudou = true;
        }

        if (isset($dados['evento_global']) && time() - $dados['evento_global']['tempo'] > 3) {
            unset($dados['evento_global']);
            $mudou = true;
        }

        if (isset($dados['reacao']) && time() - $dados['reacao']['tempo'] > 3) {
            unset($dados['reacao']);
            $mudou = true;
        }

        if ($dados['status'] === 'jogando' && isset($dados['jogadores'][$dados['turno_atual']])) {
            $jogador_da_vez = $dados['turno_atual'];
            $last_seen = $dados['jogadores'][$jogador_da_vez]['last_seen'] ?? 0;
            
            if (time() - $last_seen > 20) {
                $nome_caido = $dados['jogadores'][$jogador_da_vez]['nome'];
                comprarCartaSegura($dados, $jogador_da_vez);
                $dados['turno_atual'] = getProximoJogador($dados, $jogador_da_vez, 0);
                $dados['log_offline'] = "⏳ O tempo de $nome_caido esgotou. Turno pulado.";
                $dados['comprou_agora'] = false;
                $mudou = true;
            }
        }

        if ($mudou) file_put_contents($arquivo, json_encode($dados));
        
        foreach ($dados['jogadores'] as $id => &$j) {
            $j['online'] = (time() - ($j['last_seen'] ?? 0)) <= 20;
        }

        echo json_encode(['status' => 'ok', 'dados' => $dados]);
    } else {
        echo json_encode(['status' => 'erro']);
    }
    exit;
}