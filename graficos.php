<?php

// --- 1. CONFIGURAÇÃO ---
$fileName = "tabela.xlsx - Planilha1.csv"; 

// --- 2. LEITURA E PROCESSAMENTO DO CSV ---
$allData = []; 
$filterOptions = [ 
    'empresa' => [],
    'mes' => [],
    'observacoes' => [],
    'local' => []
];

try {
    if (($handle = fopen($fileName, "r")) === FALSE) {
        throw new Exception("Não foi possível abrir o arquivo CSV: $fileName");
    }

    $header_raw = fgetcsv($handle, 1000, ";"); 
    if ($header_raw === FALSE) {
        throw new Exception("Arquivo CSV está vazio ou inválido.");
    }
    
    // --- LÓGICA DE LIMPEZA DO CABEÇALHO ---
    $header_raw[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header_raw[0]);
    $header_searchable = [];
    $header_original = [];
    foreach ($header_raw as $col) {
        $cleaned_col = trim($col);
        $header_searchable[] = mb_strtolower($cleaned_col, 'UTF-8'); 
        $header_original[] = $cleaned_col;
    }
    // --- FIM DA LÓGICA DE LIMPEZA ---
    
    // --- (v9) Encontra o índice das NOVAS colunas ---
    $colEmpresa = array_search('empresa', $header_searchable);
    $colMes = array_search('mês', $header_searchable); 
    $colObservacoes = array_search('observacoes', $header_searchable);
    $colLocal = array_search('local', $header_searchable);
    $colValor = array_search('valor', $header_searchable);

    // Verificação de colunas
    if ($colEmpresa === false || $colMes === false || $colObservacoes === false || $colLocal === false || $colValor === false) {
        $missing = [];
        if ($colEmpresa === false) $missing[] = 'empresa';
        if ($colMes === false) $missing[] = 'mês';
        if ($colObservacoes === false) $missing[] = 'observacoes';
        if ($colLocal === false) $missing[] = 'local';
        if ($colValor === false) $missing[] = 'valor';
        throw new Exception("Colunas necessárias não encontradas: [" . implode(', ', $missing) . "]. Cabeçalho lido: " . implode(', ', $header_original));
    }

    // Processa linha por linha
    while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
        if (count($row) <= max($colEmpresa, $colMes, $colObservacoes, $colLocal, $colValor)) {
            continue; 
        }

        // Pega os dados da linha
        $empresa = $row[$colEmpresa];
        $mes = $row[$colMes];
        $observacoes = $row[$colObservacoes];
        $local = $row[$colLocal];
        $valorBruto = $row[$colValor]; 
        
        // --- (v9) LÓGICA DE LIMPEZA DE VALOR (ex: "42044,02") ---
        $valorLimpo = str_replace(',', '.', $valorBruto); 
        $valor = (float)$valorLimpo;
        // --- FIM DA LÓGICA ---

        if ($valor == 0) continue;
        
        // Adiciona a linha limpa ao array de dados brutos
        $allData[] = [
            'empresa' => $empresa,
            'mes' => $mes,
            'observacoes' => $observacoes,
            'local' => $local,
            'valor' => $valor
        ];
        
        // Adiciona aos filtros
        $filterOptions['empresa'][$empresa] = true;
        $filterOptions['mes'][$mes] = true;
        $filterOptions['observacoes'][$observacoes] = true;
        $filterOptions['local'][$local] = true;
    }
    fclose($handle);
    
    // Converte os dados brutos para JSON
    $json_allData = json_encode($allData);

} catch (Exception $e) {
    die("Erro ao processar o arquivo: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Análise (v12)</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7f6; }
        h1, h2 { text-align: center; color: #333; }
        
        /* (v10) Estilo dos KPIs */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            width: 90%; max-width: 1800px; margin: 20px auto;
        }
        .kpi-box {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 20px;
            text-align: center;
        }
        .kpi-title {
            font-size: 1rem;
            font-weight: 600;
            color: #555;
            margin: 0;
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a73e8;
            margin: 10px 0 0 0;
        }
        
        .filter-container {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 15px;
            width: 90%; max-width: 1800px; margin: 20px auto; padding: 15px;
            background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 5px; }
        .filter-group select {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;
            background-color: #fafafa; font-size: 0.9rem; min-width: 200px;
        }
        
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            width: 90%; max-width: 1800px; margin: 20px auto;
        }
        .chart-box {
            background-color: #ffffff; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px;
            min-height: 400px;
        }
        .chart-box-horizontal {
            min-height: 450px;
        }
        
        /* (v10) Estilo da Tabela */
        .table-box {
            width: 90%; max-width: 1800px; margin: 20px auto;
            background-color: #ffffff; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px;
            overflow-x: auto;
        }
        #data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        #data-table th, #data-table td {
            border: 1px solid #ddd; padding: 8px 12px; text-align: left;
            font-size: 0.9rem; white-space: nowrap;
        }
        #data-table th { background-color: #f4f7f6; font-weight: 600; position: sticky; top: 0; }
        #data-table td:first-child { font-weight: bold; background-color: #f9f9f9; }
        #data-table td:not(:first-child) { text-align: right; }
    </style>
</head>
<body>

    <h1>Dashboard de Despesas</h1>

    <div class="kpi-container">
        <div class="kpi-box">
            <h2 class="kpi-title">Valor Total (Filtrado)</h2>
            <p class="kpi-value" id="kpi-total-valor">R$ 0</p>
        </div>
        <div class="kpi-box" id="kpi-empresa-construcao">
            <h2 class="kpi-title">Total Construção</h2>
            <p class="kpi-value" id="kpi-valor-construcao">R$ 0</p>
        </div>
        <div class="kpi-box" id="kpi-empresa-locacao">
            <h2 class="kpi-title">Total Locação</h2>
            <p class="kpi-value" id="kpi-valor-locacao">R$ 0</p>
        </div>
    </div>

    <div class="filter-container">
        <div class="filter-group">
            <label for="filter-empresa">Filtrar por Empresa</label>
            <select id="filter-empresa">
                <option value="todos">Todas as Empresas</option>
                <?php foreach (array_keys($filterOptions['empresa']) as $item) : ?>
                    <option value="<?php echo htmlspecialchars($item); ?>"><?php echo htmlspecialchars($item); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter-mes">Filtrar por Mês</label>
            <select id="filter-mes">
                <option value="todos">Todos os Meses</option>
                <?php 
                $meses = array_keys($filterOptions['mes']);
                // Você pode adicionar uma lógica de ordenação de meses aqui se precisar
                foreach ($meses as $item) : ?>
                    <option value="<?php echo htmlspecialchars($item); ?>"><?php echo htmlspecialchars($item); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
         <div class="filter-group">
            <label for="filter-local">Filtrar por Local</label>
            <select id="filter-local">
                <option value="todos">Todos os Locais</option>
                <?php foreach (array_keys($filterOptions['local']) as $item) : ?>
                    <option value="<?php echo htmlspecialchars($item); ?>"><?php echo htmlspecialchars($item); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter-observacoes">Filtrar por Observação</label>
            <select id="filter-observacoes">
                <option value="todos">Todas as Observações</option>
                <?php foreach (array_keys($filterOptions['observacoes']) as $item) : ?>
                    <option value="<?php echo htmlspecialchars($item); ?>"><?php echo htmlspecialchars($item); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="chart-box">
            <canvas id="chartEmpresa"></canvas>
        </div>
        <div class="chart-box">
            <canvas id="chartLocal"></canvas>
        </div>
        <div class="chart-box">
            <canvas id="chartMes"></canvas>
        </div>
        <div class="chart-box chart-box-horizontal">
            <canvas id="chartObservacoes"></canvas>
        </div>
        <div class="chart-box">
            <canvas id="chartMesStacked"></canvas>
        </div>
        <div class="chart-box">
            <canvas id="chartLocalStacked"></canvas>
        </div>
        <div class="chart-box chart-box-horizontal">
            <canvas id="chartObservacoesComparativo"></canvas>
        </div>
    </div>
    
    <div class="table-box">
        <h2>Tabela de Resumo (Observação vs. Local)</h2>
        <div id="data-table-container">
            </div>
    </div>


    <script>
        // Registra o plugin de data labels
        Chart.register(ChartDataLabels);

        // --- Formatadores de Números ---
        const numberFormatter = new Intl.NumberFormat('pt-BR', {
            style: 'currency', currency: 'BRL',
            minimumFractionDigits: 0, maximumFractionDigits: 0
        });
        const compactFormatter = new Intl.NumberFormat('pt-BR', {
            notation: 'compact', compactDisplay: 'short'
        });
        
        // Ordem correta dos meses 
        const monthOrder = {
            'JANEIRO': 1, 'FEVEREIRO': 2, 'MARÇO': 3, 'ABRIL': 4, 'MAIO': 5, 'JUNHO': 6,
            'JULHO': 7, 'AGOSTO': 8, 'SETEMBRO': 9, 'OUTUBRO': 10, 'NOVEMBRO': 11, 'DEZEMBRO': 12
        };
        
        // --- Armazena os Dados Brutos do PHP ---
        const rawData = <?php echo $json_allData; ?>;
        
        // --- (v10) Referências dos Filtros e KPIs ---
        const filterEmpresa = document.getElementById('filter-empresa');
        const filterMes = document.getElementById('filter-mes');
        const filterLocal = document.getElementById('filter-local');
        const filterObservacoes = document.getElementById('filter-observacoes');
        
        const kpiTotalValor = document.getElementById('kpi-total-valor');
        const kpiValorConstrucao = document.getElementById('kpi-valor-construcao');
        const kpiValorLocacao = document.getElementById('kpi-valor-locacao');
        const kpiBoxConstrucao = document.getElementById('kpi-empresa-construcao');
        const kpiBoxLocacao = document.getElementById('kpi-empresa-locacao');
        
        // --- (v12) Variáveis globais dos Gráficos ---
        let chartEmpresa, chartMes, chartLocal, chartObservacoes, chartMesStacked, chartLocalStacked, chartObservacoesComparativo;
        
        // --- (v10) Gera Cores para os gráficos empilhados ---
        const observacoesColors = {};
        <?php foreach (array_keys($filterOptions['observacoes']) as $item) : ?>
            observacoesColors['<?php echo addslashes($item); ?>'] = 'rgba(<?php echo rand(0,255); ?>, <?php echo rand(0,255); ?>, <?php echo rand(0,255); ?>, 0.7)';
        <?php endforeach; ?>
        
        const empresaColors = {};
        <?php foreach (array_keys($filterOptions['empresa']) as $item) : ?>
            empresaColors['<?php echo addslashes($item); ?>'] = 'rgba(<?php echo rand(0,255); ?>, <?php echo rand(0,255); ?>, <?php echo rand(0,255); ?>, 0.7)';
        <?php endforeach; ?>
        
        // --- (v11) Gera Cores para os meses ---
        const mesColors = {};
        <?php foreach (array_keys($filterOptions['mes']) as $item) : ?>
            mesColors['<?php echo addslashes($item); ?>'] = 'rgba(<?php echo rand(0,255); ?>, <?php echo rand(0,255); ?>, <?php echo rand(0,255); ?>, 0.7)';
        <?php endforeach; ?>

        
        /**
         * (v11) Função principal de cálculo
         */
        function calculateChartData(data) {
            let aggEmpresa = {};
            let aggMes = {};
            let aggLocal = {};
            let aggObservacoes = {};
            let aggMesEmpresa = {}; // Para chart 5
            let aggLocalObservacoes = {}; // Para chart 6 e Tabela
            let aggObservacoesMes = {}; // Para chart 7
            let totalValor = 0;
            
            let uniqueMeses = new Set();
            let uniqueEmpresas = new Set();
            let uniqueLocais = new Set();
            let uniqueObservacoes = new Set();

            data.forEach(row => {
                totalValor += row.valor;

                // Agregadores Simples
                if (!aggEmpresa[row.empresa]) aggEmpresa[row.empresa] = 0;
                aggEmpresa[row.empresa] += row.valor;

                if (!aggMes[row.mes]) aggMes[row.mes] = 0;
                aggMes[row.mes] += row.valor;

                if (!aggLocal[row.local]) aggLocal[row.local] = 0;
                aggLocal[row.local] += row.valor;
                
                if (!aggObservacoes[row.observacoes]) aggObservacoes[row.observacoes] = 0;
                aggObservacoes[row.observacoes] += row.valor;
                
                // Agregadores Empilhados (Chart 5)
                if (!aggMesEmpresa[row.mes]) aggMesEmpresa[row.mes] = {};
                if (!aggMesEmpresa[row.mes][row.empresa]) aggMesEmpresa[row.mes][row.empresa] = 0;
                aggMesEmpresa[row.mes][row.empresa] += row.valor;
                
                // Agregadores Empilhados (Chart 6 e Tabela)
                if (!aggLocalObservacoes[row.local]) aggLocalObservacoes[row.local] = {};
                if (!aggLocalObservacoes[row.local][row.observacoes]) aggLocalObservacoes[row.local][row.observacoes] = 0;
                aggLocalObservacoes[row.local][row.observacoes] += row.valor;
                
                // (v11): Agregador para Chart 7
                if (!aggObservacoesMes[row.observacoes]) aggObservacoesMes[row.observacoes] = {};
                if (!aggObservacoesMes[row.observacoes][row.mes]) aggObservacoesMes[row.observacoes][row.mes] = 0;
                aggObservacoesMes[row.observacoes][row.mes] += row.valor;

                // Guarda rótulos únicos
                uniqueMeses.add(row.mes);
                uniqueEmpresas.add(row.empresa);
                uniqueLocais.add(row.local);
                uniqueObservacoes.add(row.observacoes);
            });

            // --- Prepara Gráfico Empresa (Pizza) ---
            const chartEmpresaData = {
                labels: Object.keys(aggEmpresa),
                datasets: [{ label: 'Distribuição por Empresa', data: Object.values(aggEmpresa) }]
            };
            
            // --- Prepara Gráfico Mês (Barra Ordenada) ---
            let sortedMes = Object.entries(aggMes).sort((a, b) => (monthOrder[a[0]] || 0) - (monthOrder[b[0]] || 0));
            const chartMesData = {
                labels: sortedMes.map(item => item[0]),
                datasets: [{
                    label: 'Valor Total por Mês',
                    data: sortedMes.map(item => item[1]),
                    backgroundColor: 'rgba(255, 159, 64, 0.7)'
                }]
            };

            // --- Prepara Gráfico Local (Barra) ---
            const chartLocalData = {
                labels: Object.keys(aggLocal),
                datasets: [{
                    label: 'Valor Total por Local',
                    data: Object.values(aggLocal),
                    backgroundColor: 'rgba(54, 162, 235, 0.7)'
                }]
            };

            // --- Prepara Gráfico Observações (Barra Horizontal Ordenada) ---
            let sortedObservacoes = Object.entries(aggObservacoes).sort((a, b) => b[1] - a[1]);
            const chartObservacoesData = {
                labels: sortedObservacoes.map(item => item[0]),
                datasets: [{
                    label: 'Valor Total por Observação',
                    data: sortedObservacoes.map(item => item[1]),
                    backgroundColor: 'rgba(255, 99, 132, 0.7)'
                }]
            };
            
            // --- Prepara Gráfico Mês Empilhado por Empresa (Chart 5) ---
            const labelsMeses = Array.from(uniqueMeses).sort((a, b) => (monthOrder[a] || 0) - (monthOrder[b] || 0));
            const labelsEmpresas = Array.from(uniqueEmpresas);
            const datasets5 = labelsEmpresas.map(empresa => {
                const data = labelsMeses.map(mes => {
                    return aggMesEmpresa[mes] ? (aggMesEmpresa[mes][empresa] || 0) : 0;
                });
                return { label: empresa, data: data, backgroundColor: empresaColors[empresa] };
            });
            const chartMesStackedData = { labels: labelsMeses, datasets: datasets5 };

            // --- Prepara Gráfico Local Empilhado por Observação (Chart 6) ---
            const labelsLocais = Array.from(uniqueLocais);
            const labelsObservacoes = Array.from(uniqueObservacoes);
            const datasets6 = labelsObservacoes.map(obs => {
                const data = labelsLocais.map(local => {
                    return aggLocalObservacoes[local] ? (aggLocalObservacoes[local][obs] || 0) : 0;
                });
                return { label: obs, data: data, backgroundColor: observacoesColors[obs] };
            });
            const chartLocalStackedData = { labels: labelsLocais, datasets: datasets6 };
            
            // --- (v11): Prepara Gráfico Observações COMPARATIVO por Mês (Chart 7) ---
            const labelsObsSorted = sortedObservacoes.map(item => item[0]);
            const datasets7 = labelsMeses.map(mes => {
                const data = labelsObsSorted.map(obs => {
                    return aggObservacoesMes[obs] ? (aggObservacoesMes[obs][mes] || 0) : 0;
                });
                return {
                    label: mes,
                    data: data,
                    backgroundColor: mesColors[mes] || 'rgba(150, 150, 150, 0.7)'
                };
            });
            // (v12) Nome da variável mantido, mas o gráfico será configurado como AGRUPADO
            const chartObservacoesComparativoData = { labels: labelsObsSorted, datasets: datasets7 };


            return { 
                totalValor, aggEmpresa, // Para KPIs
                chartEmpresaData, chartMesData, chartLocalData, chartObservacoesData, // Gráficos simples
                chartMesStackedData, chartLocalStackedData, chartObservacoesComparativoData, // Gráficos complexos
                aggLocalObservacoes, labelsLocais, labelsObservacoes // Para Tabela
            };
        }
        
        /**
         * (v10) Função para atualizar os KPIs
         */
        function updateKPIs(total, aggEmpresa) {
            kpiTotalValor.textContent = numberFormatter.format(total);
            
            let valConstrucao = 0;
            let valLocacao = 0;
            
            for (const key in aggEmpresa) {
                if (key.toUpperCase().includes('CONSTRUÇÃO')) {
                    valConstrucao += aggEmpresa[key];
                }
                if (key.toUpperCase().includes('LOCAÇÃO')) {
                    valLocacao += aggEmpresa[key];
                }
            }

            kpiValorConstrucao.textContent = numberFormatter.format(valConstrucao);
            kpiValorLocacao.textContent = numberFormatter.format(valLocacao);
            
            kpiBoxConstrucao.style.display = (valConstrucao > 0 || total === 0) ? 'block' : 'none';
            kpiBoxLocacao.style.display = (valLocacao > 0 || total === 0) ? 'block' : 'none';
        }
        
        
        /**
         * (v10) Função para desenhar a tabela de resumo
         */
        function updateTable(aggData, labelsLinhas, labelsColunas) {
            const container = document.getElementById('data-table-container');
            let html = '<table id="data-table"><thead><tr><th>Observação / Local</th>';

            labelsColunas.forEach(col => { html += `<th>${col}</th>`; });
            html += '</tr></thead><tbody>';

            labelsLinhas.forEach(linha => {
                html += `<tr><td>${linha}</td>`;
                labelsColunas.forEach(col => {
                    const valor = (aggData[col] && aggData[col][linha]) ? aggData[col][linha] : 0;
                    html += `<td>${numberFormatter.format(valor)}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }


        /**
         * (v12) Função chamada quando qualquer filtro muda.
         */
        function updateCharts() {
            // 1. Pega os valores dos filtros
            const selectedEmpresa = filterEmpresa.value;
            const selectedMes = filterMes.value;
            const selectedLocal = filterLocal.value;
            const selectedObservacoes = filterObservacoes.value;

            // 2. Filtra os dados brutos
            const filteredData = rawData.filter(row => {
                const passEmpresa = (selectedEmpresa === 'todos') || (row.empresa === selectedEmpresa);
                const passMes = (selectedMes === 'todos') || (row.mes === selectedMes);
                const passLocal = (selectedLocal === 'todos') || (row.local === selectedLocal);
                const passObservacoes = (selectedObservacoes === 'todos') || (row.observacoes === selectedObservacoes);
                return passEmpresa && passMes && passLocal && passObservacoes;
            });

            // 3. Calcula os novos dados de gráfico
            const newData = calculateChartData(filteredData);
            
            // 4. Atualiza os KPIs
            updateKPIs(newData.totalValor, newData.aggEmpresa);
            
            // 5. Atualiza os 7 gráficos
            chartEmpresa.data = newData.chartEmpresaData;
            chartEmpresa.update();
            
            chartMes.data = newData.chartMesData;
            chartMes.update();
            
            chartLocal.data = newData.chartLocalData;
            chartLocal.update();
            
            chartObservacoes.data = newData.chartObservacoesData;
            chartObservacoes.update();
            
            chartMesStacked.data = newData.chartMesStackedData;
            chartMesStacked.update();
            
            chartLocalStacked.data = newData.chartLocalStackedData;
            chartLocalStacked.update();
            
            chartObservacoesComparativo.data = newData.chartObservacoesComparativoData; // (v12)
            chartObservacoesComparativo.update(); // (v12)
            
            // 6. Atualiza a tabela
            updateTable(newData.aggLocalObservacoes, newData.labelsObservacoes, newData.labelsLocais);
        }
        
        /**
         * (v12) Função de inicialização
         */
        function init() {
            // Adiciona os event listeners
            filterEmpresa.addEventListener('change', updateCharts);
            filterMes.addEventListener('change', updateCharts);
            filterLocal.addEventListener('change', updateCharts);
            filterObservacoes.addEventListener('change', updateCharts);
            
            // Calcula os dados iniciais (com todos os dados)
            const initialData = calculateChartData(rawData);

            // --- Cria Gráfico 1: Empresa (Pizza) ---
            chartEmpresa = new Chart(document.getElementById('chartEmpresa'), {
                type: 'pie', data: initialData.chartEmpresaData,
                options: { responsive: true, maintainAspectRatio: false, plugins: {
                    title: { display: true, text: 'Visão por Empresa', font: { size: 16 } },
                    legend: { position: 'top' },
                    datalabels: {
                        formatter: (value, ctx) => {
                            const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            if (total === 0) return '0%';
                            const percentage = (value / total * 100).toFixed(1);
                            return percentage + '%'; 
                        },
                        color: '#fff', font: { weight: 'bold' }
                    }
                }}
            });
            
            // --- Cria Gráfico 2: Local (Barra) ---
            chartLocal = new Chart(document.getElementById('chartLocal'), {
                type: 'bar', data: initialData.chartLocalData,
                options: { responsive: true, maintainAspectRatio: false, plugins: {
                    title: { display: true, text: 'Visão por Local', font: { size: 16 } },
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end', align: 'top',
                        formatter: (value) => numberFormatter.format(value),
                        font: { weight: 'bold', size: 10 }
                    }
                }}
            });
            
            // --- Cria Gráfico 3: Mês (Barra) ---
            chartMes = new Chart(document.getElementById('chartMes'), {
                type: 'bar', data: initialData.chartMesData,
                options: { responsive: true, maintainAspectRatio: false, plugins: {
                    title: { display: true, text: 'Visão por Mês', font: { size: 16 } },
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end', align: 'top',
                        formatter: (value) => numberFormatter.format(value),
                        font: { weight: 'bold', size: 10 }
                    }
                }}
            });

            // --- Cria Gráfico 4: Observações (Barra Horizontal) ---
            chartObservacoes = new Chart(document.getElementById('chartObservacoes'), {
                type: 'bar', data: initialData.chartObservacoesData,
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', 
                    plugins: {
                    title: { display: true, text: 'Visão por Observações (Total)', font: { size: 16 } },
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end', align: 'right',
                        formatter: (value) => numberFormatter.format(value),
                        font: { weight: 'bold', size: 10 }
                    }
                }}
            });
            
            // --- Cria Gráfico 5: Mês vs Empresa (Empilhado) ---
            chartMesStacked = new Chart(document.getElementById('chartMesStacked'), {
                type: 'bar', data: initialData.chartMesStackedData,
                options: { responsive: true, maintainAspectRatio: false, plugins: {
                    title: { display: true, text: 'Mês (Empilhado por Empresa)', font: { size: 16 } },
                    legend: { position: 'top' },
                    datalabels: { display: false } // Desabilitado para não poluir
                }, scales: { x: { stacked: true }, y: { stacked: true } }}
            });
            
            // --- Cria Gráfico 6: Local vs Observações (Empilhado) ---
            chartLocalStacked = new Chart(document.getElementById('chartLocalStacked'), {
                type: 'bar', data: initialData.chartLocalStackedData,
                options: { responsive: true, maintainAspectRatio: false, plugins: {
                    title: { display: true, text: 'Local (Empilhado por Observação)', font: { size: 16 } },
                    legend: { position: 'top' },
                    datalabels: {
                        color: '#fff',
                        formatter: (value) => (value < 50000) ? '' : compactFormatter.format(value),
                        font: { weight: 'bold', size: 9 }
                    }
                }, scales: { x: { stacked: true }, y: { stacked: true } }}
            });
            
            // --- *** MUDANÇA (v12): Cria Gráfico 7 (Comparativo) *** ---
            chartObservacoesComparativo = new Chart(document.getElementById('chartObservacoesComparativo'), {
                type: 'bar', data: initialData.chartObservacoesComparativoData,
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', // Horizontal
                    plugins: {
                    title: { display: true, text: 'Observações (Comparativo por Mês)', font: { size: 16 } },
                    legend: { position: 'top' },
                    datalabels: {
                        anchor: 'end',
                        align: 'right',
                        formatter: (value) => (value > 0) ? compactFormatter.format(value) : '', // Formato compacto
                        font: { weight: 'bold', size: 9 }
                    }
                }, 
                // *** Garante que não está empilhado ***
                scales: { x: { stacked: false }, y: { stacked: false } }}
            });
            
            
            // --- Cria KPIs e Tabela Iniciais ---
            updateKPIs(initialData.totalValor, initialData.aggEmpresa);
            updateTable(initialData.aggLocalObservacoes, initialData.labelsObservacoes, initialData.labelsLocais);
        }
        
        // Inicia tudo!
        init();
    </script>

</body>
</html>