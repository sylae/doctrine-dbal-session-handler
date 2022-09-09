<?php
/*
 * Copyright (c) 2022 Keira Dueck <sylae@calref.net>
 * Use of this source code is governed by the MIT license, which
 * can be found in the LICENSE file.
 */

namespace Doctrine\DBAL;

use Doctrine\DBAL\Types\Types;

class DBALSessionHandler implements
    \SessionUpdateTimestampHandlerInterface, \SessionHandlerInterface, \SessionIdInterface
{

    /**
     * @var callable returns ?int or the type given to setUserIDType()
     */
    protected $userIDHandler = null;

    protected string $sessionTable = "sessions";

    protected string $userIDType = Types::INTEGER;

    public function __construct(protected Connection $db)
    {
    }

    public function validateId($id): bool
    {
        $qb = $this->db->createQueryBuilder();
        $qb->select("*")
            ->from($this->sessionTable)
            ->where('idSession = ?')
            ->setParameter(0, $id, 'string');
        return (bool)$qb->fetchAssociative();
    }

    public function updateTimestamp($id, $data): bool
    {
        return $this->write($id, $data);
    }

    public function write($id, $data): bool
    {
        return (bool)$this->db->executeStatement(
            "replace into {$this->sessionTable} (idSession, data, ip, userAgent, idUser) VALUES (?, ?, ?, ?, ?)",
            [$id, $data, $this->getIP(), $this->getUserAgent(), $this->getUserID()],
            ['string', 'string', 'string', 'string', $this->userIDType]
        ); // todo: use querybuilder for this
    }

    protected function getIP(): ?string
    {
        return inet_pton((PHP_SAPI == "cli") ? "::1" : $_SERVER['REMOTE_ADDR']);
    }

    protected function getUserAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    protected function getUserID()
    {
        return call_user_func($this->userIDHandler) ?? null;
    }

    public function close(): bool
    {
        $this->db->commit();
        return true;
    }

    public function destroy($id): bool
    {
        $qb = $this->db->createQueryBuilder();
        $qb->delete($this->sessionTable)
            ->where('idSession = ?')
            ->setParameter(0, $id, 'string');
        return (bool)$qb->executeStatement();
    }

    public function gc($max_lifetime): int
    {
        return 0; // todo: actually do GC, for now keep everything.
    }

    public function open($path, $name): bool
    {
        $this->db->beginTransaction();
        return true;

        // ???????????????
        // idfk
        // it whole dead ass just deletes the entire fukken row every time anyway i guess? like ok dude
        return (bool)$this->db->executeStatement(
            "select * from {$this->sessionTable} where idSession = ? for update",
            [$name],
            ['string']
        );
    }

    public function read($id): string
    {
        $qb = $this->db->createQueryBuilder();
        $qb->select("data")
            ->from($this->sessionTable)
            ->where('idSession = ?')
            ->setParameter(0, $id, 'string');
        return $qb->executeQuery()->fetchOne();
    }

    public function create_sid(): string
    {
        return base_convert(bin2hex(random_bytes(16)), 16, 36);
    }

    /**
     * @param callable $userIDHandler a callable that returns an integer or null. can change int to something else with setUserIDType()
     */
    public function setUserIDHandler(callable $userIDHandler)
    {
        $this->userIDHandler = $userIDHandler;
    }

    public function setSessionTable(string $sessionTable): void
    {
        $this->sessionTable = $sessionTable;
    }

    /**
     * set what your user ID is (defaults to integer). should be one of those in Doctrine\DBAL\Types\Types
     * @param string $userIDType
     */
    public function setUserIDType(string $userIDType)
    {
        $this->userIDType = $userIDType;
    }
}