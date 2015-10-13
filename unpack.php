#!/usr/bin/env php
<?php
function vdk_error($message = null)
{
    if (is_null($message)) {
        $last = error_get_last();
        $message = isset($last['message']) ? $last['message'] : 'unknown error';
    } else {
        $message = vsprintf($message, array_slice(func_get_args(), 1));
    }
    fprintf(STDERR, "%s: %s\n", $GLOBALS['argv'][0], $message);
}

function vdk_unpack($vdk, $path = '.')
{
    do {
        $file = unpack('Cis_dir/Z128name/V2size/x4/Voffset', fread($vdk, 145));
        if ($file['is_dir']) {
            if (!file_exists($path)) {
                echo $path, "\n";
                if (!@mkdir($path)) {
                    vdk_error();
                    exit(1);
                }
            }
            if (!in_array($file['name'], ['.', '..'])) {
                vdk_unpack($vdk, $path . '/' . $file['name']);
            }
        } else {
            echo $tmp = $path . '/' . $file['name'], "\n";
            $data = @gzuncompress(fread($vdk, $file['size2']), $file['size1']);
            if (
                $data === false
                or @file_put_contents($tmp, $data) !== $file['size1']
            ) {
                vdk_error();
            }
        }
    } while ($file['offset']);
}

if ($argc < 2) {
    fprintf(STDERR, "Usage: %s vdkfile\n", $argv[0]);
    exit(1);
}

if (!$vdk = @fopen($argv[1], 'rb')) {
    vdk_error();
    exit(1);
}

$header = unpack('Z8version/x4/Vfiles/Vfolders/Vsize', fread($vdk, 24));
switch ($header['version']) {
    case 'VDISK1.0':
        break;
    case 'VDISK1.1':
        if (unpack('V', fread($vdk, 4))[1] == $header['files'] * 264 + 4) {
            break;
        }
    default:
        vdk_error('%s is not a valid VDK file', $argv[1]);
        exit(1);
}

printf(
    "File: %s\nVersion: %s\nFiles: %u\nFolders: %u\nSize: %u\n\n",
    $argv[1],
    $header['version'],
    $header['files'],
    $header['folders'],
    $header['size']
);

vdk_unpack($vdk, pathinfo($argv[1], PATHINFO_FILENAME));
