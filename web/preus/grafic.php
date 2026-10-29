<?php
require ("../scale/login.php");
if (!$login) die;
$conn = $link;
$id = $_REQUEST['ID'];
$date_limit = date('Y-m-d H:i:s', strtotime("-{$_REQUEST['years']} years"));
$sql = "SELECT data_hora, pvp, cost_price, pv1 FROM tarifes WHERE ID = $id AND data_hora > '$date_limit' ORDER BY data_hora";
$result = $conn->query($sql);

// Preparar les dades per a la gràfica
$dates = [];
$data_pvp = [];
$data_cost_price = [];
$data_pv1 = [];

$anormal_prices = [];

if ($result->num_rows > 0) {
    $previous_pvp = 0;
    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['data_hora'];

        // Comprovar si el preu és anormal
        if ($previous_pvp != 0 && abs($row['pvp'] - $previous_pvp) / $previous_pvp > 1) {
            $anormal_prices[] = ['date' => $row['data_hora'], 'price' => $row['pvp']];
            $data_pvp[] = $previous_pvp;
        } else {
            $data_pvp[] = $row['pvp'];
            $previous_pvp = $row['pvp'];
        }

        $data_cost_price[] = $row['cost_price'];
        $data_pv1[] = $row['pv1'];
    }
} else {
    echo "0 results";
}

// Codificar les dades en format JSON
$datesJson = json_encode($dates);
$data_pvpJson = json_encode($data_pvp);
$data_cost_priceJson = json_encode($data_cost_price);
$data_pv1Json = json_encode($data_pv1);

// Tancar la connexió a la base de dades
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grafica 3D</title>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <style>
        html, body {
            height: 100%;
        }
    </style>
</head>
<body>
<h1> <?php echo $sql;?></h1>
<div id="3dPlot" style="width:100%; height:100%;"></div>

<h2>Preus anormals:</h2>
<ul>
    <?php foreach ($anormal_prices as $price): ?>
        <li><?php echo $price['date'] . ' - ' . $price['price']; ?></li>
    <?php endforeach; ?>
</ul>

<script>
    // Dades del gràfic
    const dates = <?php echo $datesJson ?>;
    const data_pvp = <?php echo $data_pvpJson ?>;
    const data_cost_price = <?php echo $data_cost_priceJson ?>;
    const data_pv1 = <?php echo $data_pv1Json ?>;
    // Crear gràfica 3D personalitzada
    const data = [
        {
            x: dates,
            y: new Array(dates.length).fill(0),
            z: data_cost_price,
            mode: 'markers',
            marker: {
                size: 6,
                color: 'rgb(0, 0, 255)',
                symbol: 'circle'
            },
            name: 'Cost Price',
            type: 'scatter3d',
        },
        {
            x: dates,
            y: new Array(dates.length).fill(1),
            z: data_pv1,
            mode: 'markers',
            marker: {
                size: 6,
                color: 'rgb(0, 255, 0)',
                symbol: 'circle'
            },
            name: 'PV1',
            type: 'scatter3d',
        },
        {
            x: dates,
            y: new Array(dates.length).fill(2),
            z: data_pvp,
            mode: 'markers',
            marker: {
                size: 6,
                color: 'rgb(255, 0, 0)',
                symbol: 'circle'
            },
            name: 'PVP',
            type: 'scatter3d',
        },
    ];

    const layout = {
        title: 'Gràfica 3D de Preus',
        scene: {
            xaxis: {
                title: 'Data i Hora',
                type: 'category'
            },
            yaxis: {
                title: 'Tipus de Preu',
                type: 'category',
                tickvals: [0, 1, 2],
                ticktext: ['Cost Price', 'PV1', 'PVP']
            },
            zaxis: {
                title: 'Preu',
            },
        },
        width: 1920,
        height: 1080,
        autosize: false,
    };

    Plotly.newPlot('3dPlot', data, layout);</script>
</body>
</html>

