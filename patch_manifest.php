<?php
$targetDir = __DIR__;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'html'])) continue;
    
    $filepath = $file->getPathname();
    $content = file_get_contents($filepath);
    if ($content === false) continue;
    $originalContent = $content;
    
    $content = str_replace('<link rel="manifest" href="./manifest.json" crossorigin="use-credentials">', '<link rel="manifest" href="./manifest.json" crossorigin="use-credentials">', $content);
    
    if ($content !== $originalContent) {
        file_put_contents($filepath, $content);
        $count++;
    }
}
echo "Updated $count files.";
?>
