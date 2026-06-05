CREATE TABLE IF NOT EXISTS llxrw_notes (
    rowid int(11) NOT NULL AUTO_INCREMENT,
    libelle varchar(255) NOT NULL,
    note text,
    datec datetime NOT NULL,
    user int(11) NOT NULL,
    PRIMARY KEY (rowid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;