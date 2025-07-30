<?php
/**
 * MergeAssets — объединяет CSS или JS файлы, всегда пересоздавая результат.
 *
 * Параметры:
 *   &type      - 'css' или 'js' (по умолчанию 'css')
 *   &files     - список файлов или масок через запятую
 *   &filename  - имя итогового файла (по умолчанию 'styles.min.css' или 'bundle.min.js')
 *   &path      - папка для сохранения (относительно корня, по умолчанию 'assets/templates')
 *
 * Пример:
 * [[MergeAssets?
 *   &type=`js`
 *   &files=`/assets/js/lib/jquery.js,/assets/js/app/*.js`
 *   &filename=`bundle.js`
 *   &path=`assets/templates/js`
 * ]]
 */

$type     = isset($type) ? strtolower(trim($type)) : 'css';
$files    = isset($files) ? trim($files) : '';
$path     = isset($path) ? trim($path, '/') : 'assets/templates';
$filename = isset($filename) ? trim($filename) : ($type === 'js' ? 'bundle.min.js' : 'styles.min.css');

if (empty($files)) return '';

$baseDir     = MODX_BASE_PATH . $path . '/';
$baseUrl     = MODX_SITE_URL . $path . '/';
$outputFile  = $baseDir . $filename;
$outputUrl   = $baseUrl . $filename;

// создаём директорию, если не существует
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

// собираем список файлов
$fileList = [];
foreach (explode(',', $files) as $entry) {
    $entry = trim($entry);
    $realPath = MODX_BASE_PATH . ltrim($entry, '/');

    if (strpos($entry, '*') !== false || strpos($entry, '?') !== false) {
        $matches = glob($realPath);
        if ($matches) {
            $fileList = array_merge($fileList, $matches);
        }
    } elseif (file_exists($realPath)) {
        $fileList[] = $realPath;
    }
}

$fileList = array_values(array_unique($fileList));

// собираем контент
$content = '';
foreach ($fileList as $filePath) {
    if (file_exists($filePath)) {
        $relative = str_replace(MODX_BASE_PATH, '/', $filePath);
        $fileContent = file_get_contents($filePath);
        $content .= "\n/* --- {$relative} --- */\n" . $fileContent;
    }
}

// удаляем старый файл и пишем новый
if (file_exists($outputFile)) {
    unlink($outputFile);
}
file_put_contents($outputFile, $content, LOCK_EX);

// версия — всегда текущее время
$version = time();
$versionedUrl = $outputUrl . '?v=' . $version;

// возвращаем тег
if ($type === 'js') {
    return '<script src="' . $versionedUrl . '"></script>';
} else {
    return '<link rel="stylesheet" href="' . $versionedUrl . '" />';
}
