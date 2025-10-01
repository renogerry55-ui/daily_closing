<?php
// /daily_closing/includes/upload_helpers.php

// Config
const RECEIPTS_MAX_BYTES = 20 * 1024 * 1024; // 20 MB
const RECEIPTS_ALLOWED = ['application/pdf','image/jpeg','image/png'];
const RECEIPTS_EXT     = ['pdf','jpg','jpeg','png'];

function receipts_target_dir(DateTime $dt): string {
    $rel = '/uploads/receipts/'.$dt->format('Y').'/'.$dt->format('m');
    $abs = $_SERVER['DOCUMENT_ROOT'] . '/daily_closing' . $rel;
    if (!is_dir($abs)) { mkdir($abs, 0775, true); }
    return $abs;
}

function random_name(string $ext): string {
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

function ext_from_name(string $name): string {
    $p = pathinfo($name, PATHINFO_EXTENSION);
    return strtolower($p ?? '');
}

function sanitize_basename(string $name): string {
    return preg_replace('/[^A-Za-z0-9._-]/','_', $name);
}

function validate_file(array $f, array &$err): bool {
    if ($f['error'] !== UPLOAD_ERR_OK) { $err[] = 'Upload error code '.$f['error']; return false; }
    if ($f['size'] <= 0 || $f['size'] > RECEIPTS_MAX_BYTES) { $err[] = 'File too large (max 20MB)'; return false; }
    $ext = ext_from_name($f['name']);
    if (!in_array($ext, RECEIPTS_EXT, true)) { $err[] = 'Unsupported file type (pdf/jpg/png only)'; return false; }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $f['tmp_name']) ?: '';
    finfo_close($finfo);
    if (!in_array($mime, RECEIPTS_ALLOWED, true)) { $err[] = 'Bad MIME type'; return false; }
    return true;
}

// Save multiple files, return array of [file_path, original_name, mime, size_bytes]
function save_receipts(array $files, DateTime $dt, array &$err): array {
    $saved = [];
    $absdir = receipts_target_dir($dt);
    $reldir = '/uploads/receipts/'.$dt->format('Y').'/'.$dt->format('m');

    // Normalize $_FILES multiple input
    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i=0; $i<$count; $i++) {
        if (($files['error'][$i] ?? null) === UPLOAD_ERR_NO_FILE) { continue; }
        $f = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        if (!validate_file($f, $err)) continue;

        $ext  = ext_from_name($f['name']);
        $rand = random_name($ext);
        $abs  = $absdir . '/' . $rand;
        if (!move_uploaded_file($f['tmp_name'], $abs)) {
            $err[] = 'Failed to save file: '.sanitize_basename($f['name']);
            continue;
        }
        $saved[] = [
            'file_path'     => $reldir . '/' . $rand,     // relative path to store in DB
            'original_name' => sanitize_basename($f['name']),
            'mime'          => mime_content_type($abs) ?: $f['type'],
            'size_bytes'    => filesize($abs) ?: $f['size'],
        ];
    }
    return $saved;
}


// ... keep your existing code above ...

/** Save HQ attachments (e.g., bank-in slip) under /uploads/hq/YYYY/MM/ */
function save_hq_files(array $files, DateTime $dt, array &$err): array {
    $saved = [];
    $year = $dt->format('Y'); $mon = $dt->format('m');
    $relBase = '/uploads/hq/'.$year.'/'.$mon;
    $absBase = $_SERVER['DOCUMENT_ROOT'] . '/daily_closing' . $relBase;
    if (!is_dir($absBase)) { mkdir($absBase, 0775, true); }

    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i=0; $i<$count; $i++) {
        if (($files['error'][$i] ?? null) === UPLOAD_ERR_NO_FILE) { continue; }
        $f = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        if (!validate_file($f, $err)) continue;

        $ext  = ext_from_name($f['name']);
        $rand = random_name($ext);
        $abs  = $absBase . '/' . $rand;
        if (!move_uploaded_file($f['tmp_name'], $abs)) {
            $err[] = 'Failed to save HQ file: '.sanitize_basename($f['name']);
            continue;
        }
        $saved[] = [
            'file_path'     => $relBase . '/' . $rand,
            'original_name' => sanitize_basename($f['name']),
            'mime'          => mime_content_type($abs) ?: $f['type'],
            'size_bytes'    => filesize($abs) ?: $f['size'],
        ];
    }
    return $saved;
}