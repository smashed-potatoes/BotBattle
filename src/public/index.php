<!DOCTYPE html>
<html>
<head>
    <title>BotBattle</title>
    <script type="text/javascript" src="js/jquery-3.2.0.min.js"></script>
    <script type="text/javascript">
        var pageObject = null;

        $(function(){
            pageObject = new BotBattle(700, 700);

            $('#viewGame').click(function() {
                var gameId = $('#gameId').val();
                pageObject.viewGame(gameId);
            });
        });

        BotBattle.states = {
            WAITING: 0,
            RUNNING: 1,
            DONE: 2
        };

        BotBattle.tileTypes = {
            GROUND: 0,
            WALL: 1,
            GOLD: 2,
            HEAL: 3
        };

        /**
        * BotBattle viewer
        */
        function BotBattle(width, height) {
            this.gameId = null;
            this.state = null;
            this.running = false;
            this.width = width;
            this.height = height;
            this.turn = null;

            this.canvas = document.getElementById('canv');
            this.ctx = this.canvas.getContext('2d');
            this.ctx.fillRect(0,0, 30, 30);

            this.turnSpan = $('#turn');
            this.playersDiv = $('#players');
            this.colors = ['#FF5F45', '#105A6A', '#413939', '#1A8B7B'];
            this.playerColors = {};
            this.userTileCount = {};

            // Setup size
            $(this.canvas).prop('width', width);
            $(this.canvas).prop('height', height);
            $(this.canvas).css('width', width + "px");
            $(this.canvas).css('height', height + "px");
        }

        /**
        * View a game
        */
        BotBattle.prototype.viewGame = function(gameId) {
            this.gameId = gameId;
            this.start();
        };

        /**
        * Start polling the current game
        */
        BotBattle.prototype.start = function() {
            if (this.stepTimeout) {
                clearTimeout(this.stepTimeout);
            }

            this.running = true;
            this.getState();
        };

        /**
        * Stop polling the current game
        */
        BotBattle.prototype.stop = function() {
            this.running = false;
        };

        /**
        * Get the current game state
        */
        BotBattle.prototype.getState = function() {
            if (this.stateRequest) {
                this.stateRequest.abort();
            }

            this.stateRequest = $.get('api/games/' + this.gameId, $.proxy(this.onState, this));
        };

        /**
        * Process the recieved game state
        */
        BotBattle.prototype.onState = function(data) {
            // Game is done, request all data
            if (data.state === BotBattle.states.DONE) {
                this.getAllStates();
                return;
            }

            if (this.state === null || (this.state.turn !== data.turn)) {
                this.loadState(data);
            }

            if (this.running && this.state.state !== BotBattle.states.DONE) {
                setTimeout($.proxy(this.getState, this), 50);
            }
            else if (this.state.state === BotBattle.states.DONE) {
                this.state = null;
            }
        };

        /**
        * Get the all game states
        */
        BotBattle.prototype.getAllStates = function() {
            if (this.stateRequest) {
                this.stateRequest.abort();
            }

            this.stateRequest = $.get('api/games/' + this.gameId + '/states', $.proxy(this.onAllStates, this));
        };

        /**
        * Process the recieved game states
        */
        BotBattle.prototype.onAllStates = function(data) {
            this.allStates = data;
            this.turn = 0;
            this.stepGame();
        };

        /**
        * Render the current state and advance the game to the next turn
        */
        BotBattle.prototype.stepGame = function() {
            if (this.state === null || this.turn <= this.state.length) {
                this.loadState(this.allStates[this.turn]);
                this.turn++;

                if (this.running && this.state.state !== BotBattle.states.DONE) {
                    this.stepTimeout = setTimeout($.proxy(this.stepGame, this), 50);
                }
                else if (this.state.state === BotBattle.states.DONE) {
                    this.state = null;
                }
            }
        };


        /**
        * Load a game state into the viewer
        */
        BotBattle.prototype.loadState = function(data) {
            // TODO: Validate data
            this.state = data;
            var tiles = this.state.board.tiles;

            // Setup player colors
            this.playerColors = {};
            this.userTileCount = {};
            for (var i=0; i<this.state.players.length; i++) {
                this.playerColors[this.state.players[i].id] = this.colors[i];
                this.userTileCount[this.state.players[i].id] = 0;
            }

            // Get the user tile counts
            for (var i=0; i<tiles.length; i++) {
                if (tiles[i].player !== null) {
                    this.userTileCount[tiles[i].player.id]++;
                }
            }

            // Show the current turn
            this.turnSpan.html(this.state.turn + " / " + this.state.length);

            // List the users, in their color
            this.playersDiv.html('');
            for (var i=0; i<this.state.players.length; i++) {
                this.playersDiv.append(this.getPlayerDiv(this.state.players[i]));
            }

            this.draw();
        };

        BotBattle.prototype.getPlayerDiv = function(player) {
            var divHtml = `
                <div class="player"> 
                    <span class="playerColor" style="background-color: ` + this.playerColors[player.id] + `">
                        ` + this.userTileCount[player.id] + `
                    </span>
                    <span class="playerHealth">
                        <span class="bar" style="height: `+player.health/5+`px"></span>
                    </span>
                    <span class="playerName">
                        `+ player.points +` : `+ player.user.username + `
                    </span>
                </div>
            `;

            return $(divHtml);
        }

        /**
        * Draw the current game state to the canvas
        */
        BotBattle.prototype.draw = function() {
            this.ctx.clearRect(0,0, this.width, this.height);

            var tiles = this.state.board.tiles;
            var players = this.state.players;
            var tileWidth = this.width / this.state.board.width;
            var tileHeight = this.height / this.state.board.width;

            var tenthOfTileWidth = tileWidth/10;
            var tenthOfTileHeight = tileHeight/10;
            
            for (var i=0; i<tiles.length; i++) {
                if (tiles[i].type === BotBattle.tileTypes.WALL) {
                    this.ctx.fillStyle='#666666';
                }
                else if (tiles[i].type === BotBattle.tileTypes.GROUND) {
                    this.ctx.fillStyle='#007B0C';
                }
                else if (tiles[i].type === BotBattle.tileTypes.GOLD) {
                    this.ctx.fillStyle='#EEBB00';
                }
                else if (tiles[i].type === BotBattle.tileTypes.HEAL) {
                    this.ctx.fillStyle='#BB314F';
                }

                this.ctx.fillRect(tiles[i].x*tileWidth, tiles[i].y*tileHeight, tileWidth, tileHeight);

                // Draw ownership
                if (tiles[i].player !== null) {
                    this.ctx.fillStyle=this.playerColors[tiles[i].player.id];
                    this.ctx.fillRect(tiles[i].x*tileWidth+tenthOfTileWidth, tiles[i].y*tileHeight+tenthOfTileHeight, tenthOfTileWidth, tenthOfTileHeight);
                }
            }

            for (var i=0; i<players.length; i++) {
                var overlaps = 0;
                for (var j=0; j<players.length; j++) {
                    if (i == j) continue;
                    if (players[i].x == players[j].x && players[i].y == players[j].y) {
                        overlaps++;
                    }
                }

                var overlapPadding = overlaps > 0 ? (tileWidth-10)/(overlaps+1)*i : 0;

                this.ctx.fillStyle=this.playerColors[players[i].id];
                this.ctx.fillRect(players[i].x*tileWidth + 5 + overlapPadding, players[i].y*tileHeight + 5, tileWidth - 10 - overlapPadding, tileHeight - 10);
                this.ctx.strokeRect(players[i].x*tileWidth + 5 + overlapPadding, players[i].y*tileHeight + 5, tileWidth - 10 - overlapPadding, tileHeight - 10);
            }

            // The game has ended, show the winner
            if (this.state.state === BotBattle.states.DONE) {
                var mostPoints = 0;
                var winner = null;
                for (var i=0; i<this.state.players.length; i++) {
                    var player = this.state.players[i];
                    if (player.points > mostPoints) {
                        winner = player;
                        mostPoints = player.points;
                    }
                    else if  (player.points == mostPoints) {
                        winner = null;
                        mostPoints = player.points;
                    }
                }

                var winnerString = "";
                if (this.state.players.length == 1) {
                    winnerString = (winner !== null) ? winner.user.username + " wins!" : "GAME OVER";
                }
                else {
                    winnerString = (winner !== null) ? winner.user.username + " wins!" : "Tie game!";
                }

                this.ctx.fillStyle = 'rgba(0, 0, 0, 0.75)';
                this.ctx.fillRect(20, this.height/2 - 50, this.width - 40, 100);
                
                this.ctx.font = "50px Arial";
                this.ctx.fillStyle = '#ffffff';
                this.ctx.textAlign = "center";
                this.ctx.textBaseline = "middle"; 
                this.ctx.fillText(winnerString, this.width/2, this.height/2);
            }
        };

    </script>

    <style type="text/css">
        html, body {
            margin: 0;
            padding: 0;

            background-color: #333;

            font-family: sans-serif;
        }

        #content {
            width: 900px;
            margin: 20px auto;
            position: relative;
        }

        #controls {
            margin: 10px 0;
            color: #fff;
        }

        #canv {
            display: inline-block;
            background-color: #007B0C;
        }

        #playerContainer {
            position: absolute;
            display: inline-block;
            width: 190px;
            height: 600px;
            right: 0px;

            background-color: #eee;

            text-align: center;
        }

        .player {
            height: 25px;
            padding: 2px;

            text-align: left;
            font-size: 12px;
            font-weight: bold;
            line-height: 25px;
        }

        .playerColor {
            float: left;
            display: inline-block;
            width: 20px;
            height: 20px;
            
            color: #fff;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            line-height: 20px;
            

            border: 1px solid #333;
        }

        .playerHealth {
            position: relative;
            float: left;
            display: inline-block;
            height: 20px;
            width: 5px;

            border: 1px solid #333;
        }

        .bar {
            position: absolute;
            bottom: 0;
            display: inline-block;
            background-color: #f00;
            width: 5px;
        }

        .playerName {
            margin-left: 5px;
        }
    </style>
</head>
<body>
<div id="content">
    <div id="controls">
        Game <input id="gameId" type="text" value="1"/>
        <input id="viewGame" type="button" value="View"/>
        Turn: <span id="turn">0</span>
    </div>
    <canvas id="canv" id="app" ></canvas>
    <div id="playerContainer">
        <h1>Players</h1>
        <div id="players">
        </div>
    </div>
</div>
</body>
</html>