SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

DROP DATABASE IF EXISTS `bot_battle`;
CREATE DATABASE IF NOT EXISTS `bot_battle` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `bot_battle`;

/* ======================= Users =============================*/
--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(254) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;


/* ======================= Boards =============================*/

--
-- Table structure for table `boards`
--

CREATE TABLE `boards` (
  `id` int(11) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for table `boards`
--
ALTER TABLE `boards`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for table `boards`
--
ALTER TABLE `boards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;


/* ======================= Games =============================*/

--
-- Table structure for table `Games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `boards_id` int(11) NOT NULL,
  `difficulty` int(11) NOT NULL,
  `state` int(11) NOT NULL,
  `turn` int(11) NOT NULL,
  `length` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `games` MODIFY COLUMN `boards_id` int(11) NOT NULL,
  ADD CONSTRAINT games_boards_id_fk
  FOREIGN KEY(`boards_id`)
  REFERENCES `boards`(`id`);


/* ======================= Players =============================*/

--
-- Table structure for table `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `games_id` int(11) NOT NULL,
  `users_id` int(11),
  `x` int(11) NOT NULL,
  `y` int(11) NOT NULL,
  `health` int(11) NOT NULL,
  `points` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for table `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `players` MODIFY COLUMN `games_id` int(11) NOT NULL,
  ADD CONSTRAINT players_games_id_fk
  FOREIGN KEY(`games_id`)
  REFERENCES `games`(`id`);

ALTER TABLE `players` MODIFY COLUMN `users_id` int(11),
  ADD CONSTRAINT players_users_id_fk
  FOREIGN KEY(`users_id`)
  REFERENCES `users`(`id`);

/* ======================= Tiles =============================*/

--
-- Table structure for table `tiles`
--

CREATE TABLE `tiles` (
  `id` int(11) NOT NULL,
  `boards_id` int(11) NOT NULL,
  `players_id` int(11),
  `x` int(11),
  `y` int(11),
  `type` int(11)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for table `tiles`
--
ALTER TABLE `tiles`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for table `tiles`
--
ALTER TABLE `tiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `tiles` MODIFY COLUMN `boards_id` int(11) NOT NULL,
  ADD CONSTRAINT tiles_boards_id_fk
  FOREIGN KEY(`boards_id`)
  REFERENCES `boards`(`id`);

ALTER TABLE `tiles` MODIFY COLUMN `players_id` int(11),
  ADD CONSTRAINT tiles_players_id_fk
  FOREIGN KEY(`players_id`)
  REFERENCES `players`(`id`);



/* ======================= Moves =============================*/

--
-- Table structure for table `moves`
--

CREATE TABLE `moves` (
  `id` int(11) NOT NULL,
  `games_id` int(11) NOT NULL,
  `players_id` int(11) NOT NULL,
  `turn` int(11) NOT NULL,
  `action` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for table `moves`
--
ALTER TABLE `moves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE `games_id_users_id_turn` (`games_id`, `players_id`, `turn`);

--
-- AUTO_INCREMENT for table `moves`
--
ALTER TABLE `moves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `moves` MODIFY COLUMN `games_id` int(11) NOT NULL,
  ADD CONSTRAINT moves_games_id_fk
  FOREIGN KEY(`games_id`)
  REFERENCES `games`(`id`);

ALTER TABLE `moves` MODIFY COLUMN `players_id` int(11) NOT NULL,
  ADD CONSTRAINT moves_players_id_fk
  FOREIGN KEY(`players_id`)
  REFERENCES `players`(`id`);


COMMIT;