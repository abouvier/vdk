#!/usr/bin/env php
<?php
function vdk_pack($vdk, $dirname, &$table = [], &$folders = 0, $parent = null)
{
    if (!$folder = scandir($dirname)) {
        return;
    }
    usort($folder, 'strcasecmp');
    if (is_null($parent)) {
        array_splice($folder, 1, 1);
    }
    $current = ftell($vdk);
    $last = count($folder) - 1;
    foreach ($folder as $i => $filename) {
        echo $pathname = "$dirname/$filename", "\n";
        if (is_dir($pathname)) {
            $start = ftell($vdk);
            fseek($vdk, 145, SEEK_CUR);
            if (!in_array($filename, ['.', '..'])) {
                vdk_pack($vdk, $pathname, $table, $folders, $current);
                $folders++;
            }
            $end = ftell($vdk);
            fseek($vdk, $start);
            fwrite($vdk, pack(
                'Ca128V4',
                1,
                $filename,
                0,
                0,
                $filename == '..' ? $parent : $start + (
                    $filename == '.' ? 0 : 145
                ),
                $i == $last ? 0 : $end
            ));
            fseek($vdk, $end);
        } else {
            $table[$pathname] = ftell($vdk);
            $data = gzcompress(file_get_contents($pathname), $GLOBALS['level']);
            fwrite($vdk, pack(
                'Ca128V4',
                0,
                $filename,
                filesize($pathname),
                $size = strlen($data),
                0,
                $i == $last ? 0 : $table[$pathname] + 145 + $size
            ) . $data);
        }
    }
}

if ($argc < 2) {
    fprintf(STDERR, "Usage: %s folder [level]\n", $argv[0]);
    exit(1);
}

$dirname = rtrim($argv[1], '/');
$level = max(0, min($argc > 2 ? $argv[2] : 1, 9));

$vdk = fopen(basename($dirname) . '.repack.vdk', 'wb') or exit(1);

fseek($vdk, 28);
vdk_pack($vdk, $dirname, $table, $folders);
$size = ftell($vdk) - 145;

$files = count($table);
fwrite($vdk, pack('V', $files));
foreach ($table as $pathname => $offset) {
    fwrite(
        $vdk,
        pack('a260V', strtoupper(substr(strstr($pathname, '/'), 1)), $offset)
    );
}

rewind($vdk);
fwrite(
    $vdk,
    pack('a8x4V4', 'VDISK1.1', $files, $folders, $size, $files * 264 + 4)
);
fclose($vdk);
