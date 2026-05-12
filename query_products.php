<?php

$db = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite');

foreach ($db->query('SELECT id, updated_at FROM products ORDER BY id') as $row) {
    echo $row['id'] . '|' . $row['updated_at'] . PHP_EOL;
}
