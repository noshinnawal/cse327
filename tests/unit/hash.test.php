<?php

require_once __DIR__ . '/../helpers.php';

function test_NFR01_hash_same_content_same_hash()
{
    $a = temp_upload('CERTIFICATE-CONTENT-v1');
    $b = temp_upload('CERTIFICATE-CONTENT-v1');
    assert_eq(pdf_hash($a), pdf_hash($b), 'identical file content produces identical hashes');
    unlink($a);
    unlink($b);
}

function test_NFR01_hash_different_content_different_hash()
{
    $a = temp_upload('CERTIFICATE-CONTENT-v1');
    $b = temp_upload('CERTIFICATE-CONTENT-v2');
    assert_true(pdf_hash($a) !== pdf_hash($b), 'tampered content produces a different hash');
    unlink($a);
    unlink($b);
}

function test_NFR01_hash_is_64_hex_chars()
{
    $path = temp_upload('hello');
    $document_hash = pdf_hash($path);
    assert_eq(64, strlen($document_hash), 'sha-256 output is 64 characters');
    assert_true(ctype_xdigit($document_hash), 'sha-256 output is hexadecimal');
    unlink($path);
}
