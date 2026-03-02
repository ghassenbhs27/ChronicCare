<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Symfony\Component\Dotenv\Dotenv;
use Throwable;

$envFile = __DIR__ . '/../.env.local';
if (file_exists($envFile)) {
    (new Dotenv())->load($envFile);
}

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// get the default connection service name used earlier
$conn = $container->get('doctrine.dbal.default_connection');
$schemaManager = $conn->createSchemaManager();
$platform = $conn->getDatabasePlatform();

$tableName = 'doctrine_migration_versions';

$expected = new Table($tableName);
$expected->addColumn('version', 'string', ['notnull' => true, 'length' => 191]);
$expected->addColumn('executed_at', 'datetime', ['notnull' => false]);
$expected->addColumn('execution_time', 'integer', ['notnull' => false]);
$expected->setPrimaryKey(['version']);

echo "Inspecting table: $tableName\n\n";

try {
    if (! method_exists($schemaManager, 'introspectTableByUnquotedName')) {
        $current = $schemaManager->introspectTable($tableName);
    } else {
        $current = $schemaManager->introspectTableByUnquotedName($tableName);
    }
} catch (Throwable $e) {
    echo "Could not introspect table: " . $e->getMessage() . "\n";
    exit(1);
}

$comparator = $schemaManager->createComparator();
$diff = $comparator->compareTables($current, $expected);

if ($diff->isEmpty()) {
    echo "No differences detected.\n";
    exit(0);
}

echo "Differences detected. Detailed diff structure:\n\n";
// print detailed diff structure (DBAL TableDiff properties) via reflection
$refl = new ReflectionClass($diff);
$props = ['changedColumns', 'addedColumns', 'removedColumns', 'changedIndexes', 'addedIndexes', 'removedIndexes'];
foreach ($props as $p) {
    echo strtoupper($p) . "\n";
    if ($refl->hasProperty($p)) {
        $prop = $refl->getProperty($p);
        $prop->setAccessible(true);
        var_export($prop->getValue($diff));
    } else {
        echo "(property not present)\n";
    }
    echo "\n\n";
}


