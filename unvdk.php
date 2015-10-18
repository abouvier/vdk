#!/usr/bin/env php
<?php
/*
    unvdk.php - VDK file unpacker
    Copyright (C) 2015  abouvier

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

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
