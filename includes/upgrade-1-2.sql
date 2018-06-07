CREATE TABLE groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR UNIQUE NOT NULL,
    desc VARCHAR);

CREATE TABLE groupmembers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    "group" INTEGER NOT NULL,
    user INTEGER NOT NULL,
    UNIQUE("group",user),
    FOREIGN KEY("group") REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY(user) REFERENCES users(id) ON DELETE CASCADE);

CREATE TABLE permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    zone INTEGER NOT NULL,
    user INTEGER,
    "group" INTEGER,
    permissions INTEGER,
    UNIQUE(zone,user,"group"),
    FOREIGN KEY(zone) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY(user) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY("group") REFERENCES groups(id) ON DELETE CASCADE);

CREATE TABLE metadata (
    name VARCHAR PRIMARY KEY,
    value VARCHAR NOT NULL);

INSERT INTO metadata (name, value) VALUES ("version","2");
