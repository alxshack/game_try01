<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HP Accumulator - Rogue-Like</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
            color: #eee;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        #game-container {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #000;
        }
        #side-panel {
            width: 300px;
            background-color: #2c2c2c;
            border-left: 2px solid #444;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-sizing: border-box;
        }
        canvas {
            cursor: pointer;
            image-rendering: pixelated;
        }
        h2 { margin-top: 0; color: #ffcc00; }
        .hp-display {
            font-size: 2em;
            font-weight: bold;
            color: #ff4444;
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            border: 2px solid #ff4444;
            border-radius: 10px;
            background-color: #331111;
        }
        .log-panel {
            flex: 1;
            background-color: #111;
            border: 1px solid #444;
            padding: 10px;
            overflow-y: auto;
            font-size: 0.9em;
            margin-bottom: 20px;
        }
        .log-entry { margin-bottom: 5px; border-bottom: 1px solid #222; padding-bottom: 2px; }
        .minimap {
            height: 150px;
            background-color: #000;
            border: 1px solid #666;
            margin-bottom: 20px;
        }
        .controls-hint { font-size: 0.8em; color: #888; margin-bottom: 20px; }
        button {
            padding: 10px;
            background-color: #444;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 10px;
        }
        button:hover { background-color: #666; }
        #combat-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            padding: 20px;
            border: 2px solid #f00;
            display: none;
            text-align: center;
        }
    </style>
</head>
<body>
    <div id="game-container">
        <canvas id="gameCanvas"></canvas>
        <div id="combat-overlay">
            <h2 id="combat-title">COMBAT!</h2>
            <p id="combat-info"></p>
            <button onclick="resolveCombat()">FIGHT!</button>
        </div>
    </div>
    <div id="side-panel">
        <h2>HP ACCUMULATOR</h2>
        <div class="hp-display" id="hp-val">2</div>
        <div class="log-panel" id="log-panel"></div>
        <div class="minimap" id="minimap-container">
            <canvas id="minimapCanvas"></canvas>
        </div>
        <div class="controls-hint">
            1st Click: Select Path<br>
            2nd Click: Move<br>
            WASD: Pan Camera<br>
            Space: Center Camera
        </div>
        <button onclick="centerCameraOnHero()">CENTER CAMERA</button>
        <button onclick="resetGame()">RESTART GAME</button>
    </div>

    <script src="assets/game.js"></script>
</body>
</html>
