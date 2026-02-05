# HP Accumulator - Isometric Rogue-Like

A browser-based turn-based rogue-like game where you accumulate HP by defeating enemies.

## Technical Specifications
- **Backend:** PHP 8.4
- **Frontend:** Vanilla JavaScript (ES6+), Canvas 2D API
- **Map:** 25x25 tiles, 3 levels, Isometric projection

## Key Mechanics
- **Movement:** Turn-based, grid-based. Click once to select path, click again to move.
- **Combat:** Automatic when entering an enemy tile.
    - Hero HP > Enemy HP: Win (Hero HP += Enemy HP)
    - Hero HP = Enemy HP: 50/50 chance
    - Hero HP < Enemy HP: Game Over
- **Goal:** Reach the exit tile (E) on the top level.

## Deployment
1. Ensure you have a web server with PHP 8.4+ support (Apache/Nginx).
2. Place all files in the web root or a subdirectory.
3. Access `index.php` in your browser.
4. The game uses PHP sessions for state management, so ensure cookies/sessions are enabled.

## Project Structure
- `api/`: RESTful API endpoints for game logic.
- `classes/`: PHP classes for game state, pathfinding, and combat.
- `data/maps/`: JSON map definitions.
- `assets/`: Frontend JavaScript and styles (and future assets).
- `index.php`: Main entry point.
# game_try01
