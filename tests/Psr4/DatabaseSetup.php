<?php
namespace Tests\Psr4;

use App\Database;
use App\Repositories\ServerRepository;
use Install\DatabaseMigration;

class DatabaseSetup
{
    /** @var Database */
    private $db;

    /** @var DatabaseMigration */
    private $databaseMigration;

    /** @var ServerRepository */
    private $serverRepository;

    public function __construct(Database $db, DatabaseMigration $databaseMigration, ServerRepository $serverRepository)
    {
        $this->db = $db;
        $this->databaseMigration = $databaseMigration;
        $this->serverRepository = $serverRepository;
    }

    public function runForTests()
    {
        $this->db->connectWithoutDb();
        $this->db->createDatabaseIfNotExists('sklep_sms_test');
        $this->db->selectDb('sklep_sms_test');
        $this->db->dropAllTables();
        $this->databaseMigration->install('abc123', 'admin', 'abc123');
    }

    public function run()
    {
        $this->db->connectWithoutDb();
        $this->db->createDatabaseIfNotExists('sklep_sms');
        $this->db->selectDb('sklep_sms');
        $this->db->dropAllTables();
        $this->databaseMigration->install('70693f66df6ed2210c9ed5c7d8c8e001b4f12f81', 'admin', 'abc123');
        $this->serverRepository->create("My server", "172.19.0.5", 27015);
    }
}
