<?php


$filename = $_FILES['csv']['tmp_name'] ?? '';
if (!$filename) {
    die('File not uploaded');
}
if (!is_uploaded_file($filename)) {
    die('File upload error');
}
$inputFile = fopen($filename, 'rt');
if (!$inputFile) {
    die('Can not open file');
}

function iterate($inputFile): Generator
{
    while (!feof($inputFile)) {
        $columns = fgetcsv($inputFile);
        if (!$columns) {
            fclose($inputFile);
            return;
        }
        if (count($columns) != 2) {
            fclose($inputFile);
            die('Wrong file format');
        }
        [$id, $name] = $columns;
        $err = '';
        if (preg_match('#([^0-9a-zа-я.\-])#ui', $name, $matches)) {
            $err = sprintf('Недопустимый символ "%s" в поле Название', $matches[1]);
        }

        yield [$id, $name, $err];
    }
    fclose($inputFile);
}

try {
    $db = new PDO(
        'mysql:host=database;dbname=codes;charset=utf8mb4',
        'root',
        'toor',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_AUTOCOMMIT => 0
        ]
    );
} catch (PDOException $e) {
    die('Can not create db connection');
}

try {
    $responseFile = tmpfile();
    if (!$responseFile) {
        die('Can not create response file');
    }
    $db->beginTransaction();
    // На дубле - ничего не делаем(id = id).
    $insertStmt = $db->prepare('
        INSERT INTO codes(id, name) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE id = id;
    ');
    foreach (iterate($inputFile) as $values) {
        if (!fputcsv($responseFile, $values)) {
            $db->rollBack();
            die('Can not write in file');
        }
        // Если произошла ошибка.
        if ($values[2]) {
            continue;
        }

        $insertStmt->execute([$values[0], $values[1]]);
    }
    $db->commit();
} catch (Error $e) {
    $db->rollBack();
    die('Can not process row: ' . $e->getMessage());
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=result.csv');

fseek($responseFile, 0);
while (!feof($responseFile)) {
    $chunk = fread($responseFile, 4096);
    if ($chunk) {
        echo $chunk;
    }
}
fclose($responseFile);
