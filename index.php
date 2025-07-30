<?php
/**
 * MergeAssets — объединяет CSS или JS файлы, сохраняет их под заданным именем и добавляет версию
 * Поддерживает список файлов и маски через glob().
 *
 * Параметры:
 *   &type      - тип файлов: 'css' или 'js' (по умолчанию 'css')
 *   &files     - список файлов или масок через запятую, например:
 *                /assets/css/reset.css,/assets/css/blocks/*.css
 *   &filename  - имя итогового файла (по умолчанию 'styles.min.css' для css и 'bundle.min.js' для js)
 *   &path      - путь относительно корня сайта, куда сохранять объединённый файл (по умолчанию 'assets/templates')
 *
 * Пример вызова:
 * [[MergeAssets?
 *   &type=`css`
 *   &files=`/assets/css/reset.css,/assets/css/blocks/*.css`
 *   &filename=`styles.min.css`
 *   &path=`assets/templates`
 * ]]
 */

$type     = isset($type) ? strtolower(trim($type)) : 'css'; // css или js
$files    = isset($files) ? trim($files) : '';
$path     = isset($path) ? trim($path, '/') : 'assets/templates';
$filename = isset($filename) ? trim($filename) : ($type === 'js' ? 'bundle.min.js' : 'styles.min.css');

if (empty($files)) return '';

$baseDir     = MODX_BASE_PATH . $path . '/';
$baseUrl     = MODX_SITE_URL . $path . '/';
$outputFile  = $baseDir . $filename;
$outputUrl   = $baseUrl . $filename;
$needRebuild = !file_exists($outputFile);

// создаём директорию, если не существует
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

// собираем список файлов с сохранением порядка
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

// удаляем дубликаты, сохраняя порядок
$fileList = array_values(array_unique($fileList));

// проверяем, нужно ли пересобирать файл
if (!$needRebuild && !empty($fileList)) {
    $cacheTime = filemtime($outputFile);
    foreach ($fileList as $filePath) {
        if (file_exists($filePath) && filemtime($filePath) > $cacheTime) {
            $needRebuild = true;
            break;
        }
    }
}

// собираем и сохраняем файл (удаляем перед записью)
if ($needRebuild && !empty($fileList)) {
    $content = '';
    foreach ($fileList as $filePath) {
        if (file_exists($filePath)) {
            $relative = str_replace(MODX_BASE_PATH, '/', $filePath);
            $fileContent = file_get_contents($filePath);
            $content .= "\n/* --- {$relative} --- */\n" . $fileContent;
        }
    }

    // Удаляем старый файл перед записью
    if (file_exists($outputFile)) {
        unlink($outputFile);
    }

    // Записываем новый файл
    file_put_contents($outputFile, $content, LOCK_EX);
}

// версия — всегда текущее время
$version = time();
$versionedUrl = $outputUrl . '?v=' . $version;

// возвращаем HTML-тег
if ($type === 'js') {
    return '<script src="' . $versionedUrl . '"></script>';
} else {
    return '<link rel="stylesheet" href="' . $versionedUrl . '" />';
}
