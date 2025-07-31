<?php
/**
 * MergeAssets — объединяет CSS или JS файлы, пересоздавая итоговый файл при каждом вызове.
 *
 * Поддерживает:
 * - список файлов;
 * - маски (через glob);
 * - исключение самого результирующего файла из сборки;
 * - добавление версии через ?v=TIMESTAMP.
 *
 * Параметры:
 *   &type      - 'css' или 'js' (по умолчанию 'css')
 *   &files     - список файлов или масок через запятую
 *   &filename  - имя объединённого файла (по умолчанию 'styles.min.css' или 'bundle.min.js')
 *   &path      - папка для сохранения (относительно корня сайта, по умолчанию 'assets/templates')
 *
 * Пример:
 * [[MergeAssets?
 *   &type=`js`
 *   &files=`/assets/templates/js/lib/jquery.js,/assets/templates/js/*.js`
 *   &filename=`bundle.min.js`
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

// исключаем сам файл сборки (если попал по маске)
$fileList = array_filter(
    array_unique($fileList),
    fn($file) => realpath($file) !== realpath($outputFile)
);
$fileList = array_values($fileList);

// собираем контент
$content = '';
foreach ($fileList as $filePath) {
    if (file_exists($filePath)) {
        $relative = str_replace(MODX_BASE_PATH, '/', $filePath);
        $fileContent = file_get_contents($filePath);
        $content .= "\n/* --- {$relative} --- */\n" . $fileContent;
    }
}

// удаляем старый файл и записываем новый
if (file_exists($outputFile)) {
    unlink($outputFile);
}
file_put_contents($outputFile, $content, LOCK_EX);

// версия — всегда текущее время
$version = time();
$versionedUrl = $outputUrl . '?v=' . $version;

// выводим HTML-тег
if ($type === 'js') {
    return '<script src="' . $versionedUrl . '"></script>';
} else {
    return '<link rel="stylesheet" href="' . $versionedUrl . '" />';
}
