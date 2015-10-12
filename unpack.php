<?php
function pathify(array $path)
{
    return implode('/', $path);
}

if ($argc < 2 or !$vdk = @fopen($argv[1], 'rb')) {
    exit(1);
}

$header = unpack('Z8version/x4/Vfiles/Vfolders/Vsize', fread($vdk, 24));
switch ($header['version']) {
    case 'VDISK1.0':
        break;
    case 'VDISK1.1':
        fseek($vdk, 4, SEEK_CUR);
        break;
    default:
        exit(1);
}

$path = [pathinfo($argv[1], PATHINFO_FILENAME)];

for ($size = $header['size'] + 145; $size >= 145; $size -= 145) {
    $file = unpack('Cis_dir/Z128name/V2size/x4/Voffset', fread($vdk, 145));
    if (in_array($file['name'], ['.', '..'])) {
        continue;
    }
    if (!file_exists(pathify($path))) {
        mkdir(pathify($path));
    }
    $path[] = $file['name'];
    if (!$file['is_dir']) {
        file_put_contents(
            pathify($path),
            gzuncompress(fread($vdk, $file['size2']), $file['size1'])
        );
        array_splice($path, $file['offset'] ? -1 : -2);
        $size -= $file['size2'];
    }
}
