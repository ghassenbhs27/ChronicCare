<?php
/**
 * Extract UP SQL from all Doctrine migration files.
 * Outputs a clean SQL file ready to run in phpMyAdmin or mysql CLI.
 */

$migDir = __DIR__ . '/migrations';
$outputFile = __DIR__ . '/migrations_up_only.sql';

$files = glob($migDir . '/Version*.php');
natsort($files);

$lines = [];
$lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$lines[] = '';

foreach ($files as $file) {
    $content = file_get_contents($file);
    $basename = basename($file);

    // Extract only the up() method body
    if (!preg_match('/function up\(Schema \$schema\): void\s*\{(.*?)^\s{4}\}/ms', $content, $upMatch)) {
        continue;
    }

    $upBody = $upMatch[1];

    // Extract all ->addSql('...') calls
    preg_match_all("/->addSql\('((?:[^'\\\\]|\\\\.|'')*)'\)/", $upBody, $sqlMatches);

    if (empty($sqlMatches[1])) {
        continue;
    }

    $lines[] = "-- *** $basename ***";
    foreach ($sqlMatches[1] as $sql) {
        $sql = str_replace("''", "'", $sql);
        $lines[] = $sql . ';';
    }
    $lines[] = '';
}

$lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';

file_put_contents($outputFile, implode("\n", $lines));
echo "Written " . count($lines) . " lines to migrations_up_only.sql\n";
echo "Preview of first 20 lines:\n";
echo implode("\n", array_slice($lines, 0, 20)) . "\n";
