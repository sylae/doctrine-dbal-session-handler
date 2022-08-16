This provides a single class, `\Doctrine\DBAL\DBALSessionHandler`, which allows you to toss your session onto an SQL
database with as little effort as possible.

As of now, it's only tested on mysql8, but it should work fine for anything really? The only sticking point is a REPLACE
INTO command, but that can easily be pull-requested :p

Example of use:

```php

$db = \Doctrine\DBAL\DriverManager::getConnection(/* or whatever */);

$sessionHandler = new \Doctrine\DBAL\DBALSessionHandler($db);
$sessionHandler->setSessionTable("whatever"); // defaults to "sessions" if not called
session_set_save_handler($sessionHandler, true);
```

By default, we also store the IP and user agent of these sessions alongside the actual session data. In addition, you
can provide a user ID by passing along a callable. This is to facilite allowing the user to see what sessions they have
active on their account, and revoking them through your site.

```php
$sessionHandler->setUserIDHandler(function (): ?int {
    return $this->user->id ?? null; // or wherever you keep your user ID.
});
```

Right now it doesnt do any bougie-ass table management for you, so you have to do a little bit of sql:

```mysql
/* minimum you can get away with */
CREATE TABLE `sessions`
(
    `idSession` char(64) NOT NULL,
    `data`      text,
    `ip`        binary(16)      DEFAULT NULL,
    `userAgent` varchar(255)    DEFAULT NULL,
    `idUser`    bigint unsigned DEFAULT NULL, /* change size as needed, this is just what i use */
    PRIMARY KEY (`idSession`),
);

/* what i use */
CREATE TABLE `sessions`
(
    `idSession` char(64) NOT NULL,
    `updated`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data`      text,
    `ip`        binary(16)        DEFAULT NULL,
    `userAgent` varchar(255)      DEFAULT NULL,
    `idUser`    bigint unsigned   DEFAULT NULL,
    PRIMARY KEY (`idSession`),
    KEY `sessions_ibfk_1` (`idUser`),
    CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users` (`idUser`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
```