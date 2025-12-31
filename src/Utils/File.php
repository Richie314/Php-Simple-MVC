<?php
namespace Richie314\SimpleMvc\Utils;

class File
{
    public const DEFAULT_MIME_TYPE = 'application/octet-stream';

    public static function GetMimeType(string $filename) : string
    {
        $mime = mime_content_type(filename: $filename);
        return 
            ($mime === false) ? 
            self::DEFAULT_MIME_TYPE : 
            $mime;
    }

    public static function Exists(string $file_path): bool
    {
        return is_file(filename: $file_path);
    }
    public static function Delete(string $file_path): bool
    {
        return file_exists(filename: $file_path) && unlink(filename: $file_path);
    }

    public static function Size(string $file_path): string
    {
        if (!self::Exists(file_path: $file_path))
        {
            return '';
        }
        $size = filesize(filename: $file_path);
        if (!$size) 
            return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($size >= 1024 && $i < count(value: $units) - 1) {
            $size = (int)($size / 1024);
            $i++;
        }
        return "$size " . $units[$i];
    }

    public static function ListDirectory(string $dir): array {
        $result = [];
        $files = scandir(directory: $dir);
    
        foreach ($files as $file)
        {
            if ($file === '.' || $file === '..') 
                continue;
    
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir(filename: $path)) {
                $result[$file] = self::ListDirectory(dir: $path);
            } else {
                $result[$file] = $file;
            }
        }
    
        return $result;
    }

    public static function UploadingFiles(string $form_name): array
    {
        if (!array_key_exists(key: $form_name, array: $_FILES))
        {
            return [];
        }

        if (is_array(value: $_FILES[$form_name]['name'])) {
            // Handling multiple files
            return self::UploadingFilesParse(files: $_FILES[$form_name]);
        }

        // Handling only one file
        return [ $_FILES[$form_name] ];
    }
    private static function UploadingFilesParse(array $files): array
    {
        $result = [];
        $num_files = count(value: $files['name']);
        $keys = array_keys(array: $files);
        for ($i = 0; $i < $num_files; $i++)
        {
            $file = [];
            foreach ($keys as $key)
            {
                $file[$key] = $files[$key][$i];
            }
            $result[] = $file;
        }
        return $result;
    }
}