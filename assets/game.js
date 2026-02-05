const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');
const minimapCanvas = document.getElementById('minimapCanvas');
const mctx = minimapCanvas.getContext('2d');

const TILE_W = 192;
const TILE_H = 96;

let gameState = null;
let currentPath = [];
let targetTile = null;
let camera = { x: 0, y: 0 };
let isMoving = false;

// Initialization
async function init() {
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    
    await fetchState();
    centerCameraOnHero();
    requestAnimationFrame(gameLoop);
}

function resizeCanvas() {
    const container = document.getElementById('game-container');
    canvas.width = container.clientWidth;
    canvas.height = container.clientHeight;
    
    minimapCanvas.width = document.getElementById('minimap-container').clientWidth;
    minimapCanvas.height = document.getElementById('minimap-container').clientHeight;
}

function centerCameraOnHero() {
    if (!gameState || !gameState.hero) return;
    const pos = isoToScreen(gameState.hero.position.x, gameState.hero.position.y, gameState.hero.position.z);
    camera.x = pos.x - canvas.width / 2;
    camera.y = pos.y - canvas.height / 2;
}

// API Calls
async function fetchState() {
    const res = await fetch('api/state.php');
    gameState = await res.json();
    updateUI();
}

async function resetGame() {
    const res = await fetch('api/reset.php');
    gameState = await res.json();
    currentPath = [];
    targetTile = null;
    centerCameraOnHero();
    updateUI();
}

async function getPath(x, y, z) {
    const res = await fetch('api/path.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ x, y, z })
    });
    const data = await res.json();
    if (data.path) {
        currentPath = data.path;
        targetTile = { x, y, z };
    } else {
        currentPath = [];
        targetTile = null;
    }
}

async function executeMove() {
    if (currentPath.length === 0 || isMoving) return;
    isMoving = true;
    
    const res = await fetch('api/move.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ path: currentPath })
    });
    const data = await res.json();
    
    // We could animate movement here, but for MVP we'll just update state
    // Actually the requirement says "Movement halts at combat encounters"
    gameState = data.state;
    currentPath = [];
    targetTile = null;
    isMoving = false;
    
    if (data.combatTriggered) {
        showCombatOverlay(data.enemy);
    }
    
    updateUI();
    centerCameraOnHero();
}

function showCombatOverlay(enemy) {
    document.getElementById('combat-info').innerText = `Enemy: ${enemy.type} (HP: ${enemy.hp})\nYour HP: ${gameState.hero.hp}`;
    document.getElementById('combat-overlay').style.display = 'block';
}

async function resolveCombat() {
    const res = await fetch('api/combat.php', { method: 'POST' });
    const data = await res.json();
    gameState = data.state;
    document.getElementById('combat-overlay').style.display = 'none';
    updateUI();
    if (gameState.gameOver && !gameState.victory) {
        alert("GAME OVER!");
    } else if (gameState.victory) {
        alert("VICTORY!");
    }
}

// Rendering
function isoToScreen(x, y, z) {
    return {
        x: (x - y) * (TILE_W / 2),
        y: (x + y) * (TILE_H / 2) - (z * TILE_H * 0.8)
    };
}

function screenToIso(sx, sy) {
    if (!gameState) return { x: 0, y: 0, z: 0 };
    for (let z = gameState.map.levels - 1; z >= 0; z--) {
        let ty = sy + (z * TILE_H * 0.8);
        let x = (sx / (TILE_W / 2) + ty / (TILE_H / 2)) / 2;
        let y = (ty / (TILE_H / 2) - sx / (TILE_W / 2)) / 2;
        let ix = Math.round(x);
        let iy = Math.round(y);
        
        if (ix >= 0 && ix < gameState.map.width && iy >= 0 && iy < gameState.map.height) {
            const tile = gameState.map.tiles[z][iy][ix];
            if (tile) return { x: ix, y: iy, z: z };
        }
    }
    return { x: 0, y: 0, z: 0 };
}

function updateUI() {
    if (!gameState) return;
    document.getElementById('hp-val').innerText = gameState.hero.hp;
    const logPanel = document.getElementById('log-panel');
    logPanel.innerHTML = '';
    gameState.log.forEach(msg => {
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        entry.innerText = msg;
        logPanel.appendChild(entry);
    });
    drawMinimap();
}

function drawMinimap() {
    if (!gameState) return;
    const scale = Math.min(minimapCanvas.width / gameState.map.width, minimapCanvas.height / gameState.map.height);
    mctx.clearRect(0, 0, minimapCanvas.width, minimapCanvas.height);
    
    // Draw tiles projection from all levels to show relief
    for(let y=0; y<gameState.map.height; y++) {
        for(let x=0; x<gameState.map.width; x++) {
            // Find the highest tile at this (x, y)
            let highestTile = null;
            for (let z = gameState.map.levels - 1; z >= 0; z--) {
                const tile = gameState.map.tiles[z][y][x];
                if (tile) {
                    highestTile = tile;
                    break;
                }
            }
            
            if (!highestTile) continue;
            
            // Base color based on type and texture
            let color;
            if (highestTile.type === 'exit') color = '#0f0';
            else if (highestTile.type.startsWith('stairs')) color = '#00f';
            else if (highestTile.type === 'wall') color = '#555';
            else color = highestTile.walkable ? '#444' : '#222';
            
            // Add relief shade: higher is lighter
            if (highestTile.z > 0) {
                // simple brightening for higher tiles
                mctx.fillStyle = color; // We'll use a simplified brightness logic
                // For simplicity, let's just use different shades of gray/brown/green
            }

            mctx.fillStyle = color;
            
            // Brighten based on height
            if (highestTile.type === 'floor' || highestTile.type === 'wall') {
                if (highestTile.z === 1) mctx.fillStyle = '#666';
                if (highestTile.z === 2) mctx.fillStyle = '#888';
            }
            
            mctx.fillRect(x * scale, y * scale, scale, scale);
        }
    }
    
    // Draw enemies
    gameState.enemies.forEach(e => {
        mctx.fillStyle = '#f00';
        mctx.fillRect(e.position.x * scale, e.position.y * scale, scale, scale);
    });
    
    // Draw hero
    mctx.fillStyle = '#fff';
    mctx.fillRect(gameState.hero.position.x * scale, gameState.hero.position.y * scale, scale, scale);
}

function gameLoop() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (gameState) {
        drawMap();
        drawPath();
    }
    requestAnimationFrame(gameLoop);
}

function drawMap() {
    if (!gameState) return;
    
    // Отрисовка по слоям Y -> X -> Z для правильного наложения
    for (let y = 0; y < gameState.map.height; y++) {
        for (let x = 0; x < gameState.map.width; x++) {
            for (let z = 0; z < gameState.map.levels; z++) {
                const tile = gameState.map.tiles[z][y][x];
                if (!tile) continue;
                
                const pos = isoToScreen(x, y, z);
                const drawX = pos.x - camera.x;
                const drawY = pos.y - camera.y;
                
                // Высота объекта: стены высокие, пол имеет небольшую толщину или склон
                const isWall = tile.type === 'wall';
                const tileHeight = isWall ? TILE_H * 2 : TILE_H * 0.4;
                
                // Цвет боковых граней с учетом Z-уровня (чем ниже, тем темнее)
                const zShade = 0.7 + (z * 0.1);
                const colorL = isWall ? [120, 120, 125] : [100, 90, 80];
                const colorR = isWall ? [90, 90, 95] : [80, 70, 60];
                
                // Левая грань
                ctx.fillStyle = `rgb(${colorL[0]*zShade},${colorL[1]*zShade},${colorL[2]*zShade})`;
                ctx.beginPath();
                ctx.moveTo(drawX - TILE_W / 2, drawY + TILE_H / 2);
                ctx.lineTo(drawX, drawY + TILE_H);
                ctx.lineTo(drawX, drawY + TILE_H + tileHeight);
                
                // Для пологих склонов холмов (не стен) на Z > 0 сдвигаем нижнюю точку наружу
                if (!isWall && z > 0) {
                    ctx.lineTo(drawX - TILE_W * 0.2, drawY + TILE_H + tileHeight); // Немного расширяем основание
                } else {
                    ctx.lineTo(drawX - TILE_W / 2, drawY + TILE_H / 2 + tileHeight);
                }
                ctx.fill();

                // Правая грань
                ctx.fillStyle = `rgb(${colorR[0]*zShade},${colorR[1]*zShade},${colorR[2]*zShade})`;
                ctx.beginPath();
                ctx.moveTo(drawX + TILE_W / 2, drawY + TILE_H / 2);
                ctx.lineTo(drawX, drawY + TILE_H);
                ctx.lineTo(drawX, drawY + TILE_H + tileHeight);
                
                if (!isWall && z > 0) {
                     ctx.lineTo(drawX + TILE_W * 0.2, drawY + TILE_H + tileHeight);
                } else {
                    ctx.lineTo(drawX + TILE_W / 2, drawY + TILE_H / 2 + tileHeight);
                }
                ctx.fill();

                // Верхняя грань
                ctx.beginPath();
                ctx.moveTo(drawX, drawY);
                ctx.lineTo(drawX + TILE_W / 2, drawY + TILE_H / 2);
                ctx.lineTo(drawX, drawY + TILE_H);
                ctx.lineTo(drawX - TILE_W / 2, drawY + TILE_H / 2);
                ctx.closePath();
                
                let topColor;
                if (isWall) {
                    topColor = '#aaa';
                } else if (tile.type === 'exit') {
                    topColor = '#44aa44';
                } else if (tile.type.startsWith('stairs')) {
                    topColor = '#5555bb';
                } else {
                    switch(tile.texture) {
                        case 'stone_floor': topColor = '#666'; break;
                        case 'wood_floor': topColor = '#8b4513'; break;
                        case 'grass_floor': topColor = '#4a7023'; break;
                        default: topColor = '#777';
                    }
                }
                
                // Подсветка в зависимости от высоты (Fallout style: выше - светлее)
                if (!isWall) {
                    ctx.fillStyle = topColor;
                    ctx.fill();
                    // Наложение градиента освещенности
                    ctx.fillStyle = `rgba(255,255,255,${z * 0.1})`;
                    ctx.fill();
                } else {
                    ctx.fillStyle = topColor;
                    ctx.fill();
                }
                
                // Подсветка пути
                if (currentPath.some(p => p.x === x && p.y === y && p.z === z)) {
                    ctx.fillStyle = 'rgba(255, 255, 0, 0.4)';
                    ctx.fill();
                }
                
                // Отрисовка ступенек для лестниц
                if (tile.type.startsWith('stairs')) {
                    ctx.strokeStyle = 'rgba(255,255,255,0.5)';
                    ctx.lineWidth = 3;
                    for (let i = 1; i < 5; i++) {
                        ctx.beginPath();
                        let offset = (i / 5);
                        ctx.moveTo(drawX - TILE_W/2 + TILE_W/2 * offset, drawY + TILE_H/2 + TILE_H/2 * offset);
                        ctx.lineTo(drawX + TILE_W/2 * offset, drawY + TILE_H/2 * offset);
                        ctx.stroke();
                    }
                }

                // Контуры плитки
                ctx.strokeStyle = 'rgba(0,0,0,0.2)';
                ctx.lineWidth = 1;
                ctx.stroke();

                // Отрисовка сущностей на этой плитке
                gameState.enemies.forEach(e => {
                    if (e.position.x === x && e.position.y === y && e.position.z === z) drawEntity(e);
                });
                if (gameState.hero.position.x === x && gameState.hero.position.y === y && gameState.hero.position.z === z) {
                    drawEntity(gameState.hero);
                }
            }
        }
    }
}

function drawEntity(e) {
    const pos = isoToScreen(e.position.x, e.position.y, e.position.z);
    const drawX = pos.x - camera.x;
    const drawY = pos.y - camera.y;
    
    const size = TILE_W * 0.2;
    
    // Тело сущности
    ctx.fillStyle = (e.type === 'hero') ? '#eee' : '#f22';
    ctx.beginPath();
    ctx.ellipse(drawX, drawY + TILE_H / 2, size, size * 2, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.stroke();
    
    // Метка HP
    ctx.fillStyle = '#fff';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    ctx.font = 'bold 24px Arial';
    ctx.textAlign = 'center';
    ctx.strokeText(e.hp, drawX, drawY + TILE_H / 2 + 8);
    ctx.fillText(e.hp, drawX, drawY + TILE_H / 2 + 8);
}

function drawPath() {
    if (currentPath.length === 0) return;
    
    ctx.strokeStyle = 'rgba(255, 255, 0, 0.5)';
    ctx.lineWidth = 3;
    ctx.beginPath();
    
    currentPath.forEach((p, i) => {
        const pos = isoToScreen(p.x, p.y, p.z);
        const drawX = pos.x - camera.x;
        const drawY = pos.y - camera.y + TILE_H / 2;
        if (i === 0) ctx.moveTo(drawX, drawY);
        else ctx.lineTo(drawX, drawY);
    });
    ctx.stroke();
}

// Controls
canvas.addEventListener('mousedown', e => {
    const rect = canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left + camera.x;
    const my = e.clientY - rect.top + camera.y;
    
    const iso = screenToIso(mx, my);
    
    if (targetTile && iso.x === targetTile.x && iso.y === targetTile.y && iso.z === targetTile.z) {
        executeMove();
    } else {
        getPath(iso.x, iso.y, iso.z);
    }
});

window.addEventListener('keydown', e => {
    const speed = 100;
    if (e.key === 'w') camera.y -= speed;
    if (e.key === 's') camera.y += speed;
    if (e.key === 'a') camera.x -= speed;
    if (e.key === 'd') camera.x += speed;
});

init();
