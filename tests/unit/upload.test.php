<?php

require_once __DIR__ . '/../helpers.php';

function fake_upload($content, $size = null)
{
    $path = tempnam(sys_get_temp_dir(), 'up_');
    file_put_contents($path, $content);
    return [
        'name'     => 'certificate.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => $size ?? strlen($content),
    ];
}

function test_FR08_valid_pdf_upload_accepted()
{
    $upload = fake_upload('%PDF-1.4 fake certificate body');
    assert_eq(null, validate_upload($upload), 'a %PDF-prefixed upload passes validation');
    unlink($upload['tmp_name']);
}

function test_FR08_non_pdf_magic_bytes_rejected()
{
    $upload = fake_upload('plain text pretending to be a pdf');
    assert_true(validate_upload($upload) !== null, 'a file without %PDF magic bytes is rejected');
    unlink($upload['tmp_name']);
}

function test_FR08_empty_upload_rejected()
{
    $upload = fake_upload('');
    assert_true(validate_upload($upload) !== null, 'a zero-byte upload is rejected');
    unlink($upload['tmp_name']);
}

function test_FR08_oversized_upload_rejected()
{
    $upload = fake_upload('%PDF-1.4 padded', 11);
    assert_true(validate_upload($upload, 10) !== null, 'an upload over the byte limit is rejected');
    unlink($upload['tmp_name']);
}

function test_FR08_upload_at_exact_limit_accepted()
{
    $upload = fake_upload('%PDF-1.4 padded', 10);
    assert_eq(null, validate_upload($upload, 10), 'an upload exactly at the limit is accepted');
    unlink($upload['tmp_name']);
}

function test_FR08_missing_upload_rejected()
{
    assert_true(validate_upload(null) !== null, 'a missing upload entry is rejected');
}

function test_FR08_upload_error_flag_rejected()
{
    $upload = fake_upload('%PDF-1.4');
    $upload['error'] = UPLOAD_ERR_PARTIAL;
    assert_true(validate_upload($upload) !== null, 'an upload with a PHP error flag is rejected');
    unlink($upload['tmp_name']);
}
