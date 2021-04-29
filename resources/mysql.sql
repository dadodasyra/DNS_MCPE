-- #! mysql
-- # { init
        CREATE TABLE IF NOT EXISTS players(
            player VARCHAR(50), `server` VARCHAR(50), ip VARCHAR(50), port MEDIUMINT(5));
-- # }
