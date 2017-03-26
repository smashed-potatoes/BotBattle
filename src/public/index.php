<!DOCTYPE html>
<html>
<head>
    <title>BotBattle</title>
    <script type="text/javascript" src="js/jquery-3.2.0.min.js"></script>
    <script type="text/javascript">
        var pageObject = null;

        $(function(){
            pageObject = new BotBattle(800, 800);

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

        /**
        * BotBattle viewer
        */
        function BotBattle(width, height) {
            this.gameId = null;
            this.running = false;
            this.width = width;
            this.height = height;

            this.canvas = document.getElementById('canv');
            this.ctx = this.canvas.getContext('2d');
            this.ctx.fillRect(0,0, 30, 30);

            this.turnSpan = $('#turn');
            this.playersSpan = $('#players');
            this.colors = ['#FF0000', '#0FF000', '#00FF00', '#000FF0'];
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
            this.turnSpan.html(this.state.turn);

            // List the users, in their color
            var players = [];
            for (var i=0; i<this.state.players.length; i++) {
                var player = this.state.players[i];
                players.push('<span style="color:'+this.playerColors[player.id]+'">' + player.user.username + ':' + this.userTileCount[player.id] + '</span>');
            }
            this.playersSpan.html(players.join(', '));

            this.draw();

            if (this.running && this.state.state !== BotBattle.states.DONE) {
                setTimeout($.proxy(this.getState, this), 50);
            }
        };

        /**
        * Draw the current game state to the canvas
        */
        BotBattle.prototype.draw = function() {
            this.ctx.clearRect(0,0, this.width, this.height);

            var tiles = this.state.board.tiles;
            var players = this.state.players;
            var tileWidth = this.width / this.state.board.width;
            var tileHeight = this.height / this.state.board.width;
            
            for (var i=0; i<tiles.length; i++) {
                if (tiles[i].player !== null) {
                    this.ctx.fillStyle=this.playerColors[tiles[i].player.id];
                    this.ctx.fillRect(tiles[i].x*tileWidth, tiles[i].y*tileHeight, tileWidth, tileHeight);
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

            // Draw the grid
            for (var i=0; i<tiles.length; i++) {
                this.ctx.strokeRect(tiles[i].x*tileWidth, tiles[i].y*tileHeight, tileWidth, tileHeight);
            }

            // The game has ended, show the winner
            if (this.state.state === BotBattle.states.DONE) {
                var mostPoints = 0;
                var winner = null;
                for (var i=0; i<this.state.players.length; i++) {
                    var player = this.state.players[i];
                    if (this.userTileCount[player.id] > mostPoints) {
                        winner = player;
                        mostPoints = this.userTileCount[player.id];
                    }
                }

                var winnerString = (winner !== null) ? winner.user.username + " wins!" : "Tie game!"

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

        #controls {
            width: 800px;
            margin: 20px auto;

            color: #fff;
        }

        #canv {
            display: block;
            margin: 20px auto;

            background-color: #fff;
        }
    </style>
</head>
<body>
<div id="controls">
    Game <input id="gameId" type="text" value="1"/>
    <input id="viewGame" type="button" value="View"/>
    Turn: <span id="turn">0</span>
    Players: <span id="players"></span>
</div>
<canvas id="canv" id="app">

</div>
</body>
</html>