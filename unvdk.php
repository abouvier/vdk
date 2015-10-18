#!/usr/bin/env php
<?php
function vdk_unpack($vdk, $dirname)
{
    do {
        $file = unpack('Cis_dir/Z128name/V2size/x4/Voffset', fread($vdk, 145));
        echo $pathname = $dirname . '/' . $file['name'], "\n";
        if ($file['is_dir']) {
            if (!file_exists($dirname)) {
                mkdir($dirname);
            }
            if (!in_array($file['name'], ['.', '..'])) {
                vdk_unpack($vdk, $pathname);
            }
        } else {
            file_put_contents(
                $pathname,
                gzuncompress(fread($vdk, $file['size2']), $file['size1'])
            );
        }
    } while ($file['offset']);
}

if ($argc < 2) {
    fprintf(STDERR, "Usage: %s vdkfile\n", $argv[0]);
    exit(1);
}

$vdk = fopen($argv[1], 'rb') or exit(1);

$header = unpack('Z8version/Vmagic/Vfiles/Vfolders/Vsize', fread($vdk, 24));

if (!($header['version'] == 'VDISK1.0' and $header['magic'] == 4294967040) and
    !($header['version'] == 'VDISK1.1' and
        unpack('V', fread($vdk, 4))[1] == $header['files'] * 264 + 4
    )
) {
    fprintf(STDERR, "%s: %s: invalid vdkfile\n", $argv[0], $argv[1]);
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
