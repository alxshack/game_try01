const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');
const minimapCanvas = document.getElementById('minimapCanvas');
const mctx = minimapCanvas.getContext('2d');

const TILE_W = 192;
const TILE_H = 96;

let gameScale = 1.0;

let gameState = null;
let currentPath = [];
let targetTile = null;
let camera = { x: 0, y: 0 };
let isMoving = false;
let isAnimating = false;
let heroVisualPos = { x: 0, y: 0, z: 0 };
let moveQueue = [];
let pendingGameState = null;
const MOVE_SPEED = 0.1;

let mousePos = { x: -1, y: -1 };
const EDGE_WIDTH = 50;
const SCROLL_SPEED = 10;

let cursorDataUrls = {};
let currentCursorStr = '';
let lastCursorTile = null;

function generateCursorDataUrls() {
    const size = 32;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    // 1. Green Arrow (Reachable)
    ctx.clearRect(0, 0, size, size);
    ctx.fillStyle = '#00ff00';
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(2, 2);
    ctx.lineTo(22, 12);
    ctx.lineTo(12, 12);
    ctx.lineTo(12, 22);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
    cursorDataUrls.reachable = `url(${canvas.toDataURL()}) 0 0, pointer`;

    // 2. Red Cross (Unreachable)
    ctx.clearRect(0, 0, size, size);
    ctx.beginPath();
    ctx.moveTo(5, 5);
    ctx.lineTo(25, 25);
    ctx.moveTo(25, 5);
    ctx.lineTo(5, 25);
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 6;
    ctx.stroke();
    ctx.strokeStyle = '#ff0000';
    ctx.lineWidth = 3;
    ctx.stroke();
    cursorDataUrls.unreachable = `url(${canvas.toDataURL()}) 15 15, not-allowed`;

    // 3. Crossed Swords (Enemy)
    ctx.clearRect(0, 0, size, size);
    ctx.beginPath();
    ctx.moveTo(5, 25); ctx.lineTo(25, 5);
    ctx.moveTo(5, 5); ctx.lineTo(25, 25);
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 6;
    ctx.stroke();

    ctx.strokeStyle = '#cccccc';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(5, 25); ctx.lineTo(25, 5);
    ctx.stroke();
    ctx.strokeStyle = '#884400';
    ctx.beginPath();
    ctx.moveTo(5, 25); ctx.lineTo(10, 20);
    ctx.stroke();

    ctx.strokeStyle = '#cccccc';
    ctx.beginPath();
    ctx.moveTo(5, 5); ctx.lineTo(25, 25);
    ctx.stroke();
    ctx.strokeStyle = '#884400';
    ctx.beginPath();
    ctx.moveTo(5, 5); ctx.lineTo(10, 10);
    ctx.stroke();

    cursorDataUrls.enemy = `url(${canvas.toDataURL()}) 15 15, crosshair`;
}

function updateCursor() {
    if (!gameState || !cursorDataUrls.reachable) return;

    if (mousePos.x < 0 || mousePos.y < 0) {
        setCursor('default');
        lastCursorTile = null;
        return;
    }

    const mx = mousePos.x + camera.x;
    const my = mousePos.y + camera.y;
    const iso = screenToIso(mx, my);

    if (lastCursorTile && lastCursorTile.x === iso.x && lastCursorTile.y === iso.y && lastCursorTile.z === iso.z) {
        return;
    }
    lastCursorTile = iso;

    let nextCursor = 'default';

    if (iso.x >= 0 && iso.x < gameState.map.width && iso.y >= 0 && iso.y < gameState.map.height) {
        // Check for enemy
        const hasEnemy = gameState.enemies.some(e => 
            e.position.x === iso.x && e.position.y === iso.y && e.position.z === iso.z
        );

        if (hasEnemy) {
            nextCursor = cursorDataUrls.enemy;
        } else {
            const tile = gameState.map.tiles[iso.z][iso.y][iso.x];
            if (tile && tile.walkable) {
                nextCursor = cursorDataUrls.reachable;
            } else {
                nextCursor = cursorDataUrls.unreachable;
            }
        }
    }

    setCursor(nextCursor);
}

function setCursor(cursorStr) {
    if (currentCursorStr !== cursorStr) {
        canvas.style.cursor = cursorStr;
        currentCursorStr = cursorStr;
    }
}

// Initialization
async function init() {
    generateCursorDataUrls();
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    
    await fetchState();
    centerCameraOnHero();
    requestAnimationFrame(gameLoop);
}

function resizeCanvas() {
    const container = document.getElementById('game-container');
    const width = container.clientWidth;
    const height = container.clientHeight;
    
    canvas.width = width;
    canvas.height = height;
    
    // Calculate scale: base design for ~1200px width
    // Minimum scale 0.5, maximum 1.2
    gameScale = Math.max(0.4, Math.min(1.2, width / 1200));
    
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
const apiIndicator = document.getElementById('api-indicator');

async function apiFetch(url, options = {}) {
    if (apiIndicator) apiIndicator.style.display = 'block';
    try {
        const response = await fetch(url, options);
        return response;
    } finally {
        if (apiIndicator) apiIndicator.style.display = 'none';
    }
}

async function fetchState() {
    const res = await apiFetch('api/state.php');
    if (!res.ok) {
        const data = await res.json();
        alert(data.error || 'Failed to fetch state');
        return;
    }
    gameState = await res.json();
    if (gameState && gameState.hero) {
        heroVisualPos = { ...gameState.hero.position };
    }
    updateUI();
    lastCursorTile = null;
    updateCursor();
}

async function resetGame() {
    const res = await apiFetch('api/reset.php');
    if (!res.ok) {
        const data = await res.json();
        alert(data.error || 'Failed to reset game');
        return;
    }
    gameState = await res.json();
    if (gameState && gameState.hero) {
        heroVisualPos = { ...gameState.hero.position };
    }
    currentPath = [];
    targetTile = null;
    isAnimating = false;
    moveQueue = [];
    pendingGameState = null;
    centerCameraOnHero();
    updateUI();
    lastCursorTile = null;
    updateCursor();
}

async function getPath(x, y, z) {
    const res = await apiFetch('api/path.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ x, y, z })
    });
    if (!res.ok) return;
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
    if (currentPath.length === 0 || isMoving || isAnimating) return;
    isMoving = true;
    
    const res = await apiFetch('api/move.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ path: currentPath })
    });
    if (!res.ok) {
        isMoving = false;
        return;
    }
    const data = await res.json();
    
    pendingGameState = data;
    moveQueue = data.movedPath;
    isAnimating = true;
    
    currentPath = [];
    targetTile = null;
    isMoving = false;
}

function updateAnimation() {
    if (!isAnimating || moveQueue.length === 0) {
        if (isAnimating) {
            finishMovement();
        }
        return;
    }

    const target = moveQueue[0];
    const dx = target.x - heroVisualPos.x;
    const dy = target.y - heroVisualPos.y;
    const dz = target.z - heroVisualPos.z;
    const dist = Math.sqrt(dx*dx + dy*dy + dz*dz);

    if (dist < MOVE_SPEED) {
        heroVisualPos.x = target.x;
        heroVisualPos.y = target.y;
        heroVisualPos.z = target.z;
        moveQueue.shift();
    } else {
        heroVisualPos.x += (dx / dist) * MOVE_SPEED;
        heroVisualPos.y += (dy / dist) * MOVE_SPEED;
        heroVisualPos.z += (dz / dist) * MOVE_SPEED;
    }
}

function finishMovement() {
    isAnimating = false;
    if (pendingGameState) {
        const oldLevel = gameState ? gameState.currentLevel : null;
        gameState = pendingGameState.state;
        
        if (oldLevel !== null && gameState.currentLevel !== oldLevel) {
            // Level transition! Sync visual position and camera
            if (gameState.hero) {
                heroVisualPos = { ...gameState.hero.position };
                centerCameraOnHero();
            }
        }
        
        if (pendingGameState.combatTriggered) {
            showCombatOverlay(pendingGameState.enemy);
        }
        updateUI();
        
        console.log("Finish Movement. Current Level:", gameState.currentLevel, "GameOver:", gameState.gameOver, "Victory:", gameState.victory);
        
        if (gameState.gameOver && !gameState.victory) {
            alert("GAME OVER!");
        } else if (gameState.victory) {
            alert("VICTORY!");
        }
        pendingGameState = null;
    }
    lastCursorTile = null;
    updateCursor();
}

function showCombatOverlay(enemy) {
    document.getElementById('combat-info').innerText = `Enemy: ${enemy.type} (HP: ${enemy.hp})\nYour HP: ${gameState.hero.hp}`;
    document.getElementById('combat-overlay').style.display = 'block';
}

async function resolveCombat() {
    const res = await apiFetch('api/combat.php', { method: 'POST' });
    if (!res.ok) return;
    const data = await res.json();
    
    document.getElementById('combat-overlay').style.display = 'none';

    if (data.resolution && data.resolution.result === 'victory') {
        // Анимируем перемещение на клетку врага
        moveQueue = [ { ...data.state.hero.position } ];
        pendingGameState = { state: data.state };
        isAnimating = true;
    } else {
        gameState = data.state;
        if (gameState && gameState.hero) {
            heroVisualPos = { ...gameState.hero.position };
        }
        updateUI();
        if (gameState.gameOver && !gameState.victory) {
            alert("GAME OVER!");
        }
    }
    
    lastCursorTile = null;
    updateCursor();
}

// Rendering
function isoToScreen(x, y, z) {
    return {
        x: (x - y) * (TILE_W / 2) * gameScale,
        y: ((x + y) * (TILE_H / 2) - (z * TILE_H * 0.8)) * gameScale
    };
}

function screenToIso(sx, sy) {
    if (!gameState) return { x: 0, y: 0, z: 0 };
    
    const scaledSX = sx / gameScale;
    const scaledSY = sy / gameScale;
    
    for (let z = gameState.map.levels - 1; z >= 0; z--) {
        let ty = scaledSY + (z * TILE_H * 0.8);
        let x = (scaledSX / (TILE_W / 2) + ty / (TILE_H / 2)) / 2;
        let y = (ty / (TILE_H / 2) - scaledSX / (TILE_W / 2)) / 2;
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
    
    // Update Level display if needed (e.g., in a title)
    const title = document.querySelector('#side-panel h2');
    if (title) title.innerText = `HP ACCUMULATOR - LVL ${gameState.currentLevel}`;

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
    mctx.fillRect(heroVisualPos.x * scale, heroVisualPos.y * scale, scale, scale);
}

function updateCamera() {
    // 1. Ручное управление (WASD) или мышью
    if (mousePos.x >= 0 && mousePos.y >= 0) {
        if (mousePos.x < EDGE_WIDTH) camera.x -= SCROLL_SPEED;
        if (mousePos.x > canvas.width - EDGE_WIDTH) camera.x += SCROLL_SPEED;
        if (mousePos.y < EDGE_WIDTH) camera.y -= SCROLL_SPEED;
        if (mousePos.y > canvas.height - EDGE_WIDTH) camera.y += SCROLL_SPEED;
    }
    
    // 2. Camera Trap для главного героя (только во время движения)
    if (isAnimating && gameState && gameState.hero) {
        const hPos = isoToScreen(heroVisualPos.x, heroVisualPos.y, heroVisualPos.z);
        const screenX = hPos.x - camera.x;
        const screenY = hPos.y - camera.y;

        const trapMarginW = canvas.width * 0.35; // Увеличил зону захвата
        const trapMarginH = canvas.height * 0.35;

        // Плавное следование за героем, если он вышел за пределы "ловушки"
        if (screenX < trapMarginW) camera.x -= (trapMarginW - screenX) * 0.1;
        if (screenX > canvas.width - trapMarginW) camera.x += (screenX - (canvas.width - trapMarginW)) * 0.1;
        if (screenY < trapMarginH) camera.y -= (trapMarginH - screenY) * 0.1;
        if (screenY > canvas.height - trapMarginH) camera.y += (screenY - (canvas.height - trapMarginH)) * 0.1;
    }
}

function gameLoop() {
    updateAnimation();
    updateCamera();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (gameState) {
        drawMap();
        drawPath();
        updateCursor();
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
                let drawY = pos.y - camera.y;
                
                // Высота объекта: стены высокие, пол имеет небольшую толщину или склон
                const isWall = tile.type === 'wall';
                
                // Визуальный подъем стен: сдвигаем верхнюю грань выше,
                // и увеличиваем высоту так, чтобы основание оставалось на уровне пола.
                // Уменьшено на 60% по запросу (оставлено 40% от предыдущей высоты)
                const wallLift = TILE_H * 0.1;
                if (isWall) {
                    drawY -= wallLift;
                }
                const tileHeight = (isWall ? (TILE_H * 0.8 + wallLift) : TILE_H * 0.4) * gameScale;
                
                // Цвет боковых граней с учетом Z-уровня (чем ниже, тем темнее)
                const zShade = 0.7 + (z * 0.1);
                const colorL = isWall ? [100, 100, 110] : [100, 90, 80];
                const colorR = isWall ? [70, 70, 80] : [80, 70, 60];
                
                // Левая грань
                ctx.fillStyle = `rgb(${colorL[0]*zShade},${colorL[1]*zShade},${colorL[2]*zShade})`;
                ctx.beginPath();
                ctx.moveTo(drawX - (TILE_W / 2) * gameScale, drawY + (TILE_H / 2) * gameScale);
                ctx.lineTo(drawX, drawY + TILE_H * gameScale);
                ctx.lineTo(drawX, drawY + TILE_H * gameScale + tileHeight);
                
                // Для пологих склонов холмов (не стен) на Z > 0 сдвигаем нижнюю точку наружу
                if (!isWall && z > 0) {
                    ctx.lineTo(drawX - TILE_W * 0.2 * gameScale, drawY + TILE_H * gameScale + tileHeight); // Немного расширяем основание
                } else {
                    ctx.lineTo(drawX - (TILE_W / 2) * gameScale, drawY + (TILE_H / 2) * gameScale + tileHeight);
                }
                ctx.fill();

                // Правая грань
                ctx.fillStyle = `rgb(${colorR[0]*zShade},${colorR[1]*zShade},${colorR[2]*zShade})`;
                ctx.beginPath();
                ctx.moveTo(drawX + (TILE_W / 2) * gameScale, drawY + (TILE_H / 2) * gameScale);
                ctx.lineTo(drawX, drawY + TILE_H * gameScale);
                ctx.lineTo(drawX, drawY + TILE_H * gameScale + tileHeight);
                
                if (!isWall && z > 0) {
                     ctx.lineTo(drawX + TILE_W * 0.2 * gameScale, drawY + TILE_H * gameScale + tileHeight);
                } else {
                    ctx.lineTo(drawX + (TILE_W / 2) * gameScale, drawY + (TILE_H / 2) * gameScale + tileHeight);
                }
                ctx.fill();

                // Верхняя грань
                ctx.beginPath();
                ctx.moveTo(drawX, drawY);
                ctx.lineTo(drawX + (TILE_W / 2) * gameScale, drawY + (TILE_H / 2) * gameScale);
                ctx.lineTo(drawX, drawY + TILE_H * gameScale);
                ctx.lineTo(drawX - (TILE_W / 2) * gameScale, drawY + (TILE_H / 2) * gameScale);
                ctx.closePath();
                
                let topColor;
                if (isWall) {
                    topColor = '#ccc';
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
                const isOnPath = currentPath.some(p => p.x === x && p.y === y && p.z === z) ||
                                 (isAnimating && moveQueue.some(p => p.x === x && p.y === y && p.z === z));
                if (isOnPath) {
                    ctx.fillStyle = 'rgba(255, 255, 0, 0.4)';
                    ctx.fill();
                }
                
                // Отрисовка ступенек для лестниц
                if (tile.type.startsWith('stairs')) {
                    ctx.strokeStyle = 'rgba(255,255,255,0.5)';
                    ctx.lineWidth = 3 * gameScale;
                    for (let i = 1; i < 5; i++) {
                        ctx.beginPath();
                        let offset = (i / 5);
                        ctx.moveTo(drawX - (TILE_W/2 * gameScale) + (TILE_W/2 * gameScale) * offset, drawY + (TILE_H/2 * gameScale) + (TILE_H/2 * gameScale) * offset);
                        ctx.lineTo(drawX + (TILE_W/2 * gameScale) * offset, drawY + (TILE_H/2 * gameScale) * offset);
                        ctx.stroke();
                    }
                }

                // Контуры плитки
                ctx.strokeStyle = 'rgba(0,0,0,0.2)';
                ctx.lineWidth = 1;
                ctx.stroke();

                // Отрисовка сущностей на этой плитке
                gameState.enemies.forEach(e => {
                    if (e.position.x === x && e.position.y === y && e.position.z === z) {
                        drawEntity(e, e.position.x, e.position.y, e.position.z);
                    }
                });

                // Отрисовка героя с использованием визуальной позиции для анимации
                if (Math.round(heroVisualPos.x) === x && 
                    Math.round(heroVisualPos.y) === y && 
                    Math.round(heroVisualPos.z) === z) {
                    drawEntity(gameState.hero, heroVisualPos.x, heroVisualPos.y, heroVisualPos.z);
                }
            }
        }
    }
}

function drawEntity(e, x, y, z) {
    const pos = isoToScreen(x, y, z);
    const drawX = pos.x - camera.x;
    const drawY = pos.y - camera.y;
    
    const size = TILE_W * 0.2 * gameScale;
    
    // Тело сущности
    ctx.fillStyle = (e.type === 'hero') ? '#eee' : '#f22';
    ctx.beginPath();
    ctx.ellipse(drawX, drawY + (TILE_H / 2) * gameScale, size, size * 2, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2 * gameScale;
    ctx.stroke();
    
    // Метка HP
    ctx.fillStyle = '#fff';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3 * gameScale;
    ctx.font = `bold ${Math.round(24 * gameScale)}px Arial`;
    ctx.textAlign = 'center';
    ctx.strokeText(e.hp, drawX, drawY + (TILE_H / 2) * gameScale + 8 * gameScale);
    ctx.fillText(e.hp, drawX, drawY + (TILE_H / 2) * gameScale + 8 * gameScale);
}

function drawPath() {
    let path = [];
    let isDashed = false;
    
    if (isAnimating && moveQueue.length > 0) {
        path = moveQueue;
        isDashed = true;
    } else if (currentPath.length > 0) {
        path = currentPath;
        isDashed = false;
    } else {
        return;
    }
    
    ctx.save();
    ctx.strokeStyle = 'rgba(255, 255, 0, 0.6)';
    ctx.lineWidth = 3 * gameScale;
    
    if (isDashed) {
        ctx.setLineDash([10 * gameScale, 10 * gameScale]);
    }
    
    ctx.beginPath();
    
    if (isAnimating) {
        // Начинаем от текущей визуальной позиции героя
        const hPos = isoToScreen(heroVisualPos.x, heroVisualPos.y, heroVisualPos.z);
        ctx.moveTo(hPos.x - camera.x, hPos.y - camera.y + (TILE_H / 2) * gameScale);
    }
    
    path.forEach((p, i) => {
        const pos = isoToScreen(p.x, p.y, p.z);
        const drawX = pos.x - camera.x;
        const drawY = pos.y - camera.y + (TILE_H / 2) * gameScale;
        
        if (!isAnimating && i === 0) {
            ctx.moveTo(drawX, drawY);
        } else {
            ctx.lineTo(drawX, drawY);
        }
    });
    
    ctx.stroke();
    ctx.restore();
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

canvas.addEventListener('mousemove', e => {
    const rect = canvas.getBoundingClientRect();
    mousePos.x = e.clientX - rect.left;
    mousePos.y = e.clientY - rect.top;
    updateCursor();
});

canvas.addEventListener('mouseleave', () => {
    mousePos.x = -1;
    mousePos.y = -1;
});

window.addEventListener('keydown', e => {
    const speed = 100;
    if (e.key === 'w') camera.y -= speed;
    if (e.key === 's') camera.y += speed;
    if (e.key === 'a') camera.x -= speed;
    if (e.key === 'd') camera.x += speed;
    if (e.code === 'Space') {
        e.preventDefault();
        centerCameraOnHero();
    }
});

init();
