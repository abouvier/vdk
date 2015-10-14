#!/usr/bin/env php
<?php
class VdkHeap extends SplHeap
{
    public function __construct(Iterator $iterator)
    {
        foreach ($iterator as $file) {
            if (!preg_match('@^[^/]+/\.\.$@', $file->getPathname())) {
                $this->insert($file->getPathname());
            }
        }
    }

    public function compare($file1, $file2)
    {
        return strcasecmp($file2, $file1);
    }
}

if ($argc < 2)
    exit(1);

$level = max(0, min($argc > 2 ? $argv[2] : 1, 9));

$table = [];
$folders = 0;

function vdk_pack($vdk, $path, $offsets)
{
    global $level, $table, $folders;

    try {
        $folder = new VdkHeap(new DirectoryIterator($path));
    } catch (Exception $e) {
        fprintf(STDERR, "%s: %s\n", $GLOBALS['argv'][0], $e->getMessage());
        return;
    }

    $offset = end($offsets);
    foreach ($folder as $file) {
        echo $file, "\n";
        $filename = basename($file);
        if (is_dir($file)) {
            $data = '';
            if (!in_array($filename, ['.', '..'])) {
                $data = vdk_pack(
                    $vdk,
                    $file,
                    array_merge($offsets, [$offset + 145])
                );
                $folders++;
            }
            $chunks[] = pack(
                'Ca128V4',
                1,
                $filename,
                0,
                0,
                $filename == '..' ? prev($offsets) : ($filename == '.' ? $offset : $offset + 145),
                $folder->count() == 1 ? 0 : $offset + 145 + strlen($data)
            ) . $data;
        } else {
            $data = gzcompress(file_get_contents($file), $level);
            $chunks[] = pack(
                'Ca128V4',
                0,
                basename($file),
                filesize($file),
                strlen($data),
                0,
                $folder->count() == 1 ? 0 : $offset + 145 + strlen($data)
            ) . $data;
            $table[$file] = $offset;
        }
        $offset += 145 + strlen($data);
    }
    return implode($chunks);
}

if (!$vdk = @fopen(basename($argv[1]) . '.yolo.vdk', 'wb')) {
    exit(1);
}

$data = vdk_pack($vdk, $argv[1], [28]);

$data = pack(
    'a8x4V4',
    'VDISK1.1',
    $count = count($table),
    $folders,
    strlen($data) - 117,
    $count * 264 + 4
) . $data . pack('V', $count);

foreach ($table as $file => $offset) {
    $data .= pack('a260V', strtoupper(substr(strstr($file, '/'), 1)), $offset);
}

file_put_contents(basename($argv[1]) . '.yolo.vdk', $data);
