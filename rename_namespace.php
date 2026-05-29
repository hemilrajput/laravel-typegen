<?php

$dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator('.'));
$count = 0;

foreach ($dir as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'md'])) {
        $path = $file->getRealPath();
        
        // Skip ignored directories
        if (strpos($path, 'vendor') !== false || strpos($path, 'node_modules') !== false || strpos($path, '.git') !== false) {
            continue;
        }

        $content = file_get_contents($path);
        $newContent = str_replace('hemilrajput\\TypeGen', 'Hemilrajput\\TypeGen', $content);
        
        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "Updated: " . $file->getFilename() . "\n";
            $count++;
        }
    }
}

echo "Total files updated: $count\n";
