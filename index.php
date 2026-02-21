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
            width: 100vw;
            overflow: hidden;
        }
        #game-container {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #000;
            overflow: hidden;
        }
        #side-panel {
            width: 320px;
            background-color: #2c2c2c;
            border-left: 2px solid #444;
            display: flex;
            flex-direction: column;
            padding: 15px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        /* Mobile adjustments */
        @media (max-width: 900px) {
            #side-panel {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: transparent;
                border: none;
                pointer-events: none;
                padding: 10px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                z-index: 100; /* Ensure UI is above canvas */
            }
            #side-panel > * {
                pointer-events: auto;
            }
            #side-panel h2, .minimap, .log-panel, .controls-hint {
                display: none;
            }
            .hp-display {
                position: absolute;
                top: 10px;
                right: 10px;
                margin: 0;
                padding: 5px 15px;
                font-size: 1.5em;
                background: rgba(51, 17, 17, 0.8);
            }
            .mobile-controls {
                position: absolute;
                bottom: 10px;
                right: 10px;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            button {
                padding: 15px;
                font-size: 1.1em;
                background-color: rgba(68, 68, 68, 0.8);
            }
        }

        /* Portrait mode warning */
        #orientation-warning {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #1a1a1a;
            color: #ffcc00;
            z-index: 9999;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
        }
        @media (max-width: 900px) and (orientation: portrait) {
            #orientation-warning {
                display: flex;
            }
        }
        canvas {
            image-rendering: auto;
            display: block;
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
        button:active { background-color: #888; }
        #combat-overlay, #combat-result-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.9);
            padding: 30px;
            border: 2px solid #f00;
            display: none;
            text-align: center;
            z-index: 1000;
            min-width: 250px;
            box-shadow: 0 0 20px rgba(255,0,0,0.5);
        }
        #combat-result-overlay {
            border-color: #ffcc00;
            box-shadow: 0 0 20px rgba(255,204,0,0.5);
        }
        .result-win { color: #00ff00; }
        .result-lost { color: #ff4444; }
        .result-hp { font-size: 1.5em; margin: 15px 0; }
        #api-indicator {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 24px;
            color: #ffcc00;
            display: none;
            z-index: 20;
            text-shadow: 0 0 5px #000;
        }
    </style>
</head>
<body>
    <div id="game-container">
        <div id="orientation-warning">
            <h2>Please Rotate Your Device</h2>
            <p>This game is best played in landscape mode.</p>
        </div>
        <div id="api-indicator">⌛</div>
        <canvas id="gameCanvas"></canvas>
        <div id="combat-overlay">
            <h2 id="combat-title">COMBAT!</h2>
            <p id="combat-info"></p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="resolveCombat()" style="background-color:#c0392b;">FIGHT!</button>
                <button onclick="cancelCombat()" style="background-color:#7f8c8d;">CANCEL</button>
            </div>
        </div>
        <div id="combat-result-overlay">
            <h2 id="result-title"></h2>
            <p id="result-hp-change" class="result-hp"></p>
            <button id="result-ok-button" onclick="closeCombatResult()">OK</button>
            <button id="result-restart-button" onclick="resetGame(); closeCombatResult()" style="display: none;">RESTART GAME</button>
        </div>
    </div>
    <div id="side-panel">
        <h2 style="font-size: 1.2em; margin-bottom: 10px;">HP ACCUMULATOR</h2>
        <div class="hp-display" id="hp-val">2</div>
        <div class="minimap" id="minimap-container">
            <canvas id="minimapCanvas"></canvas>
        </div>
        <div class="log-panel" id="log-panel"></div>
        <div class="controls-hint">
            1st Click: Select Path<br>
            2nd Click: Move<br>
            WASD: Pan Camera<br>
            Space: Center Camera
        </div>
        <div class="mobile-controls">
            <button onclick="centerCameraOnHero()">CENTER CAMERA</button>
            <button onclick="resetGame()">RESTART GAME</button>
            <button onclick="location.href='editor.php'">MAP EDITOR</button>
        </div>
    </div>

    <script src="assets/game.js"></script>
</body>
</html>
