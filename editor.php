<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Editor - HP Accumulator</title>
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
        #editor-container {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #000;
            overflow: hidden;
        }
        #side-panel {
            width: 350px;
            background-color: #2c2c2c;
            border-left: 2px solid #444;
            display: flex;
            flex-direction: column;
            padding: 15px;
            box-sizing: border-box;
            overflow-y: auto;
        }
        canvas {
            image-rendering: auto;
            display: block;
            cursor: crosshair;
        }
        h2 { margin-top: 0; color: #ffcc00; font-size: 1.2em; }
        .control-group {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #444;
            border-radius: 5px;
        }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }
        select, input, button {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            background-color: #444;
            color: white;
            border: 1px solid #666;
            box-sizing: border-box;
        }
        button {
            cursor: pointer;
            font-weight: bold;
            background-color: #555;
            transition: background 0.2s;
        }
        button:hover { background-color: #777; }
        button.active {
            background-color: #ffcc00;
            color: #000;
        }
        .tool-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
        }
        .info {
            font-size: 0.8em;
            color: #aaa;
            margin-top: 10px;
        }
        #status-bar {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            padding: 5px 10px;
            font-size: 12px;
            pointer-events: none;
        }
        #property-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.9);
            padding: 20px;
            border: 2px solid #ffcc00;
            display: none;
            z-index: 1000;
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div id="editor-container">
        <canvas id="editorCanvas"></canvas>
        <div id="status-bar">Pos: - | Tile: -</div>
        <div id="property-modal">
            <h3 id="prop-title" style="margin-top:0;">Edit Element</h3>
            <div id="prop-fields">
                <div id="enemy-fields" style="display:none;">
                    <label>Type:</label>
                    <select id="edit-enemy-type">
                        <option value="guard">Guard</option>
                        <option value="monster">Monster</option>
                        <option value="boss">Boss</option>
                    </select>
                    <label>HP:</label>
                    <input type="number" id="edit-enemy-hp" min="1">
                </div>
                <div id="tile-fields">
                    <p id="tile-info-text"></p>
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <button onclick="saveProperties()" style="background-color:#28a745; margin-bottom:0;">OK</button>
                <button onclick="closeProperties()" style="background-color:#dc3545; margin-bottom:0;">CANCEL</button>
            </div>
        </div>
    </div>
    <div id="side-panel">
        <h2>MAP EDITOR</h2>
        
        <div class="control-group">
            <label for="map-select">Load Map:</label>
            <select id="map-select">
                <option value="">-- Select Map --</option>
            </select>
            <button onclick="loadMap()">LOAD</button>
        </div>

        <div class="control-group">
            <label>Map Properties:</label>
            <div id="map-info">
                Width: <span id="info-width">-</span>, 
                Height: <span id="info-height">-</span>, 
                Levels: <span id="info-levels">-</span>
            </div>
            <button onclick="saveMap()" style="margin-top:10px; background-color: #28a745;">SAVE MAP</button>
        </div>

        <div class="control-group">
            <label>Current Z-Level:</label>
            <input type="number" id="z-level" value="0" min="0" max="2" onchange="updateZLevel(this.value)">
        </div>

        <div class="control-group">
            <label>Tools:</label>
            <div class="tool-grid">
                <button class="tool-btn active" data-tool="floor" onclick="setTool('floor')">Floor</button>
                <button class="tool-btn" data-tool="wall" onclick="setTool('wall')">Wall</button>
                <button class="tool-btn" data-tool="stairs_up" onclick="setTool('stairs_up')">Stairs Up</button>
                <button class="tool-btn" data-tool="stairs_down" onclick="setTool('stairs_down')">Stairs Down</button>
                <button class="tool-btn" data-tool="exit" onclick="setTool('exit')">Exit</button>
                <button class="tool-btn" data-tool="enemy" onclick="setTool('enemy')">Enemy</button>
                <button class="tool-btn" data-tool="hero" onclick="setTool('hero')">Hero</button>
                <button class="tool-btn" data-tool="delete" onclick="setTool('delete')" style="background-color: #dc3545;">Delete</button>
            </div>
        </div>

        <div class="control-group">
            <label for="texture-select">Floor Texture:</label>
            <select id="texture-select">
                <option value="stone_floor">Stone</option>
                <option value="wood_floor">Wood</option>
                <option value="grass_floor">Grass</option>
            </select>
        </div>

        <div class="control-group">
            <label for="enemy-hp">Enemy HP:</label>
            <input type="number" id="enemy-hp" value="5" min="1">
        </div>

        <div class="info">
            <b>Controls:</b><br>
            Left Click: Inspect element<br>
            Right Click: Add/Set selected element<br>
            WASD / Arrows: Move camera<br>
            Mouse Wheel: Zoom (not impl.)
        </div>
        
        <button onclick="location.href='index.php'" style="margin-top: auto;">BACK TO GAME</button>
    </div>

    <script src="assets/editor.js"></script>
</body>
</html>
