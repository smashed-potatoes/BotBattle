<!DOCTYPE html>
<html>
<head>
    <title>BotBattle</title>
    <script type="text/javascript" src="js/jquery-3.2.0.min.js"></script>
    <script type="text/javascript">
        var pageObject = null;

        $(function(){
            pageObject = new BotBattle(800, 800);
        });

        function BotBattle(width, height) {
            this.running = false;
            this.width = width;
            this.height = height;

            this.canvas = document.getElementById('canv');
            this.ctx = this.canvas.getContext('2d');
            this.ctx.fillRect(0,0, 30, 30);

            // Setup size
            $(this.canvas).prop('width', width);
            $(this.canvas).prop('height', height);
            $(this.canvas).css('width', width + "px");
            $(this.canvas).css('height', height + "px");
        }

        BotBattle.prototype.start = function() {
            this.running = true;
            this.getState();
        };

        BotBattle.prototype.stop = function() {
            this.running = false;
        };

        BotBattle.prototype.getState = function() {
            $.get('api/', $.proxy(this.onState, this));
        };

        BotBattle.prototype.onState = function(data) {
            // TODO: Validate data
            this.state = data;
            this.draw();

            if (this.running) {
                setTimeout($.proxy(this.getState, this), 50);
            }
        };

        BotBattle.prototype.draw = function() {
            this.ctx.clearRect(0,0, this.width, this.height);

            var tiles = this.state.board.tiles;
            var tileWidth = this.width / tiles.length;
            var tileHeight = this.height / tiles[0].length;
            
            for (var x=0; x<tiles.length; x++) {
                for (var y=0; y<tiles[x].length; y++) {
                    if (tiles[x][y].owner == 0) {
                        this.ctx.fillStyle="#FF0000";
                    }
                    else {
                        this.ctx.fillStyle="#0000FF";
                    }
                    this.ctx.fillRect(x*tileWidth, y*tileHeight, tileWidth, tileHeight);
                }
            }

            // Draw the grid
            for (var x=0; x<tiles.length; x++) {
                for (var y=0; y<tiles[x].length; y++) {
                    this.ctx.strokeRect(x*tileWidth, y*tileHeight, tileWidth, tileHeight);
                }
            }
        };

    </script>

    <style type="text/css">
        html, body {
            margin: 0;
            padding: 0;

            background-color: #333;
        }

        #canv {
            display: block;
            margin: 20px auto;

            background-color: #fff;
        }
    </style>
</head>
<body>
<canvas id="canv" id="app">

</div>
</body>
</html>