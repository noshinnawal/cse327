<?php
$files = [
    'tests/integration/ledger.test.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace function name
    $content = str_replace('ledger_find_by_hash', 'ledger_find_by_document_hash', $content);
    
    // Replace variable names
    $content = str_replace('$hash', '$document_hash', $content);
    $content = str_replace('$tampered_hash', '$tampered_document_hash', $content);
    $content = str_replace('$bad_hash', '$bad_document_hash', $content);
    
    // Replace array keys and JSON properties
    $content = str_replace("'hash'", "'document_hash'", $content);
    $content = str_replace('"hash"', '"document_hash"', $content);
    
    // UNIQUE(hash)
    $content = str_replace('UNIQUE(hash)', 'UNIQUE(document_hash)', $content);
    
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
