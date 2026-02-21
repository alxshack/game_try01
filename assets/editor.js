const canvas = document.getElementById('editorCanvas');
const ctx = canvas.getContext('2d');

const TILE_SIZE = 64;
let gameScale = 1.0;

let mapData = null;
let currentZ = 0;
let currentTool = 'floor';
let camera = { x: 0, y: 0 };
let mousePos = { x: -1, y: -1 };
let editingElement = null; // Store reference to element being edited

// Инициализация
async function init() {
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    
    await fetchMapList();
    
    requestAnimationFrame(editorLoop);
}

function resizeCanvas() {
    const container = document.getElementById('editor-container');
    canvas.width = container.clientWidth;
    canvas.height = container.clientHeight;
    
    if (mapData) {
        calculateScale();
    }
}

async function fetchMapList() {
    const res = await fetch('api/editor_list.php');
    const data = await res.json();
    const select = document.getElementById('map-select');
    
    data.maps.forEach(map => {
        const opt = document.createElement('option');
        opt.value = map;
        opt.textContent = map;
        select.appendChild(opt);
    });
}

async function loadMap() {
    const filename = document.getElementById('map-select').value;
    if (!filename) return;
    
    const res = await fetch(`api/editor_load.php?file=${filename}`);
    if (!res.ok) {
        alert("Failed to load map");
        return;
    }
    mapData = await res.json();
    
    // Преобразуем плоский список плиток в 3D массив [z][y][x]
    const tiles3D = [];
    for (let z = 0; z < mapData.levels; z++) {
        tiles3D[z] = [];
        for (let y = 0; y < mapData.height; y++) {
            tiles3D[z][y] = [];
            for (let x = 0; x < mapData.width; x++) {
                tiles3D[z][y][x] = null;
            }
        }
    }
    
    mapData.tiles.forEach(tile => {
        if (tile.z < mapData.levels && tile.y < mapData.height && tile.x < mapData.width) {
            tiles3D[tile.z][tile.y][tile.x] = tile;
        }
    });
    mapData.tiles = tiles3D;
    
    document.getElementById('info-width').textContent = mapData.width;
    document.getElementById('info-height').textContent = mapData.height;
    document.getElementById('info-levels').textContent = mapData.levels;
    document.getElementById('z-level').max = mapData.levels - 1;
    
    // Рассчитываем масштаб и центрируем
    calculateScale();
}

function calculateScale() {
    if (!mapData) return;
    
    const margin = 40;
    const availableW = canvas.width - margin;
    const availableH = canvas.height - margin;
    
    const mapW = mapData.width * TILE_SIZE;
    const mapH = mapData.height * TILE_SIZE;
    
    gameScale = Math.min(availableW / mapW, availableH / mapH, 1.0);
    
    camera.x = (canvas.width - mapW * gameScale) / 2;
    camera.y = (canvas.height - mapH * gameScale) / 2;
}

async function saveMap() {
    if (!mapData) return;
    const filename = document.getElementById('map-select').value;
    if (!filename) {
        alert("Load a map first or specify a filename");
        return;
    }
    
    // Пересобираем массив tiles из 3D в плоский список для JSON
    const flatTiles = [];
    for (let z = 0; z < mapData.levels; z++) {
        for (let y = 0; y < mapData.height; y++) {
            for (let x = 0; x < mapData.width; x++) {
                if (mapData.tiles[z][y][x]) {
                    flatTiles.push(mapData.tiles[z][y][x]);
                }
            }
        }
    }
    
    const saveData = {
        filename: filename,
        map: {
            ...mapData,
            tiles: flatTiles
        }
    };
    
    const res = await fetch('api/editor_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(saveData)
    });
    
    if (res.ok) {
        alert("Map saved successfully!");
    } else {
        const err = await res.json();
        alert("Error saving map: " + (err.error || "Unknown error"));
    }
}

function updateZLevel(val) {
    currentZ = parseInt(val);
}

function setTool(tool) {
    currentTool = tool;
    document.querySelectorAll('.tool-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tool === tool);
    });
}

// Rendering Logic (2D Top-down)
function worldToScreen(x, y) {
    return {
        x: camera.x + x * TILE_SIZE * gameScale,
        y: camera.y + y * TILE_SIZE * gameScale
    };
}

function screenToWorld(sx, sy) {
    if (!mapData) return { x: 0, y: 0, z: 0 };
    
    let x = (sx - camera.x) / (TILE_SIZE * gameScale);
    let y = (sy - camera.y) / (TILE_SIZE * gameScale);
    
    return { x: Math.floor(x), y: Math.floor(y), z: currentZ };
}

function editorLoop() {
    updateCamera();
    render();
    requestAnimationFrame(editorLoop);
}

function updateCamera() {
    // В режиме "вся карта сразу" свободное перемещение камеры можно ограничить или убрать
    // Но оставим для возможности тонкой настройки, если карта очень большая
    const speed = 10;
    if (keys['w']) camera.y -= speed;
    if (keys['s']) camera.y += speed;
    if (keys['a']) camera.x -= speed;
    if (keys['d']) camera.x += speed;
}

const keys = {};
window.addEventListener('keydown', e => keys[e.key.toLowerCase()] = true);
window.addEventListener('keyup', e => keys[e.key.toLowerCase()] = false);

function render() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (!mapData) return;
    
    // Сетка/Фон
    drawGrid();
    
    // Отрисовка карты (упрощенная версия drawMap из game.js)
    for (let y = 0; y < mapData.height; y++) {
        for (let x = 0; x < mapData.width; x++) {
            for (let z = 0; z < mapData.levels; z++) {
                const tile = mapData.tiles[z][y][x];
                if (!tile) continue;
                
                drawTile(tile, x, y, z);
            }
        }
    }
    
    // Отрисовка сущностей
    mapData.entities.forEach(e => {
        // Assign color based on type if not present (for compatibility with loaded data)
        if (!e.color) {
            if (e.type === 'hero') e.color = '#fff';
            else if (e.type === 'guard') e.color = '#3498db';
            else if (e.type === 'monster') e.color = '#e74c3c';
            else if (e.type === 'boss') e.color = '#9b59b6';
            else e.color = '#f00';
        }
        drawEntity(e);
    });
    
    // Подсветка курсора
    if (mousePos.x >= 0) {
        const world = screenToWorld(mousePos.x, mousePos.y);
        if (world.x >= 0 && world.x < mapData.width && world.y >= 0 && world.y < mapData.height) {
            const pos = worldToScreen(world.x, world.y);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
            ctx.lineWidth = 2;
            ctx.strokeRect(pos.x, pos.y, TILE_SIZE * gameScale, TILE_SIZE * gameScale);
            
            document.getElementById('status-bar').textContent = `Pos: ${world.x}, ${world.y}, ${world.z} | Tool: ${currentTool}`;
        }
    }
}

function drawGrid() {
    ctx.strokeStyle = '#222';
    ctx.lineWidth = 1;
    for (let y = 0; y < mapData.height; y++) {
        for (let x = 0; x < mapData.width; x++) {
            const pos = worldToScreen(x, y);
            ctx.strokeRect(pos.x, pos.y, TILE_SIZE * gameScale, TILE_SIZE * gameScale);
        }
    }
}

function drawTile(tile, x, y, z) {
    const pos = worldToScreen(x, y);
    
    let color = '#777';
    if (tile.type === 'wall') color = '#aaa';
    else if (tile.type === 'exit') color = '#4a4';
    else if (tile.type.startsWith('stairs')) color = '#44a';
    
    if (tile.texture === 'wood_floor') color = '#863';
    if (tile.texture === 'grass_floor') color = '#472';
    
    // В 2D виде мы рисуем только текущий слой или ниже (с прозрачностью)
    if (z > currentZ) return; // Не рисуем слои выше текущего, чтобы не перекрывать
    
    if (z < currentZ) ctx.globalAlpha = 0.3;
    else ctx.globalAlpha = 1.0;
    
    ctx.fillStyle = color;
    ctx.fillRect(pos.x, pos.y, TILE_SIZE * gameScale, TILE_SIZE * gameScale);
    
    ctx.strokeStyle = 'rgba(0,0,0,0.3)';
    ctx.strokeRect(pos.x, pos.y, TILE_SIZE * gameScale, TILE_SIZE * gameScale);
    
    ctx.globalAlpha = 1.0;
}

function drawEntity(e) {
    const pos = worldToScreen(e.position.x, e.position.y);
    
    if (e.position.z !== currentZ) {
        if (e.position.z < currentZ) ctx.globalAlpha = 0.5;
        else return; // Не рисуем сущности выше текущего уровня
    }
    
    ctx.fillStyle = e.color || ((e.type === 'hero') ? '#fff' : '#f00');
    ctx.beginPath();
    ctx.arc(pos.x + (TILE_SIZE/2)*gameScale, pos.y + (TILE_SIZE/2)*gameScale, 15*gameScale, 0, Math.PI*2);
    ctx.fill();
    
    ctx.globalAlpha = 1.0;
}

// Interaction
canvas.addEventListener('mousedown', handleMouseDown);
canvas.addEventListener('contextmenu', e => e.preventDefault());
canvas.addEventListener('mousemove', e => {
    const rect = canvas.getBoundingClientRect();
    mousePos.x = e.clientX - rect.left;
    mousePos.y = e.clientY - rect.top;
});

function handleMouseDown(e) {
    if (!mapData) return;
    if (editingElement) {
        // Close modal if clicked outside
        const modal = document.getElementById('property-modal');
        if (!modal.contains(e.target)) {
            closeProperties();
        }
        return;
    }

    const rect = canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left;
    const my = e.clientY - rect.top;
    const world = screenToWorld(mx, my);
    
    if (world.x < 0 || world.x >= mapData.width || world.y < 0 || world.y >= mapData.height) return;

    if (e.button === 0) { // Left Click - Inspect
        inspectAt(world);
    } else if (e.button === 2) { // Right Click - Place
        applyTool(world);
    }
}

function inspectAt(iso) {
    // Priority: Entities then Tiles
    const enemy = mapData.entities.find(e => 
        e.position.x === iso.x && e.position.y === iso.y && e.position.z === iso.z
    );

    if (enemy) {
        showProperties('enemy', enemy);
        return;
    }

    const tile = mapData.tiles[iso.z][iso.y][iso.x];
    if (tile) {
        showProperties('tile', tile);
    }
}

function showProperties(type, element) {
    editingElement = { type, data: element };
    const modal = document.getElementById('property-modal');
    const title = document.getElementById('prop-title');
    const enemyFields = document.getElementById('enemy-fields');
    const tileFields = document.getElementById('tile-fields');
    const tileText = document.getElementById('tile-info-text');

    modal.style.display = 'block';

    if (type === 'enemy') {
        title.textContent = `Edit Enemy at ${element.position.x}, ${element.position.y}, ${element.position.z}`;
        enemyFields.style.display = 'block';
        tileFields.style.display = 'none';
        document.getElementById('edit-enemy-type').value = element.type;
        document.getElementById('edit-enemy-hp').value = element.hp;
    } else {
        title.textContent = `Tile Info at ${element.x}, ${element.y}, ${element.z}`;
        enemyFields.style.display = 'none';
        tileFields.style.display = 'block';
        tileText.style.whiteSpace = 'pre-line';
        tileText.textContent = `Type: ${element.type}\nTexture: ${element.texture}\nWalkable: ${element.walkable}`;
    }
}

function saveProperties() {
    if (!editingElement) return;

    if (editingElement.type === 'enemy') {
        const newHp = parseInt(document.getElementById('edit-enemy-hp').value);
        const newType = document.getElementById('edit-enemy-type').value;
        
        // Update the actual object in mapData.entities
        editingElement.data.hp = newHp;
        editingElement.data.type = newType;
        
        // Update color based on new type
        if (newType === 'guard') editingElement.data.color = '#3498db';
        else if (newType === 'monster') editingElement.data.color = '#e74c3c';
        else if (newType === 'boss') editingElement.data.color = '#9b59b6';
    }

    closeProperties();
}

function closeProperties() {
    document.getElementById('property-modal').style.display = 'none';
    editingElement = null;
}

function applyTool(iso) {
    if (currentTool === 'delete') {
        deleteAt(iso);
        return;
    }

    if (currentTool === 'hero' || currentTool === 'enemy') {
        // Удаляем старые сущности на этой клетке
        mapData.entities = mapData.entities.filter(e => 
            !(e.position.x === iso.x && e.position.y === iso.y && e.position.z === iso.z)
        );
        
        if (currentTool === 'hero') {
            // Герой может быть только один
            mapData.entities = mapData.entities.filter(e => e.type !== 'hero');
        }
        
        const hp = (currentTool === 'hero') ? 100 : parseInt(document.getElementById('enemy-hp').value);
        let color = '#f00';
        if (currentTool === 'hero') color = '#fff';
        else if (currentTool === 'guard') color = '#3498db';
        else if (currentTool === 'monster') color = '#e74c3c';
        else if (currentTool === 'boss') color = '#9b59b6';

        mapData.entities.push({
            type: currentTool,
            position: { ...iso },
            hp: hp,
            color: color
        });
    } else {
        // Плитки
        const texture = document.getElementById('texture-select').value;
        const walkable = (currentTool !== 'wall');
        
        mapData.tiles[iso.z][iso.y][iso.x] = {
            x: iso.x, y: iso.y, z: iso.z,
            type: currentTool,
            texture: (currentTool === 'floor') ? texture : 'stone_floor',
            walkable: walkable
        };
    }
}

function deleteAt(iso) {
    // Удаляем сущность если есть
    mapData.entities = mapData.entities.filter(e => 
        !(e.position.x === iso.x && e.position.y === iso.y && e.position.z === iso.z)
    );
    // Удаляем плитку
    mapData.tiles[iso.z][iso.y][iso.x] = null;
}

init();
