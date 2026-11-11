<?php
namespace Richie314\SimpleMvc\Utils;

class File
{
    public const DEFAULT_MIME_TYPE = 'application/octet-stream';

    /**
     * Detect a file MIME type using the fileinfo extension.
     *
     * @param string $filename Path to a readable file.
     * @return string Detected MIME type, or DEFAULT_MIME_TYPE when detection returns false.
     */
    public static function GetMimeType(string $filename) : string
    {
        $mime = mime_content_type(filename: $filename);
        return 
            ($mime === false) ? 
            self::DEFAULT_MIME_TYPE : 
            $mime;
    }

    /**
     * Check whether a path refers to a regular file.
     *
     * @param string $file_path File path to inspect.
     * @return bool True for a regular file, including a symlink to a regular file.
     */
    public static function Exists(string $file_path): bool
    {
        return is_file(filename: $file_path);
    }
    /**
     * Delete an existing filesystem path with unlink().
     *
     * @param string $file_path File or symlink path to delete; directories cannot be removed.
     * @return bool False if absent or unlink fails; true on successful deletion.
     */
    public static function Delete(string $file_path): bool
    {
        return file_exists(filename: $file_path) && unlink(filename: $file_path);
    }

    /**
     * Format a file size using whole binary-scaled units.
     *
     * Values are truncated at each division by 1024, preserving the existing format.
     *
     * @param string $file_path Path to a regular file.
     * @return string Size in B, KB, MB, or GB; empty if absent; 0 B for an empty file or failed size lookup.
     */
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

    /**
     * Recursively list directory entries, omitting the dot and parent entries.
     *
     * Directory symlinks are followed; callers must avoid cyclic directory trees.
     *
     * @param string $dir Readable directory path.
     * @return array<array-key, string|array> Entries keyed by name; files contain their names and directories contain nested arrays.
     */
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

    /**
     * Normalize a single upload or flat multiple-upload field into a list.
     *
     * Handles single files and numeric multiple-file fields such as attachments[].
     * Records are returned even for failed uploads; inspect each error code before use.
     *
     * @param string $form_name Field name in $_FILES.
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int, full_path?: string}> Upload records, or an empty list if the field is absent.
     */
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
    /**
     * Transpose PHP multiple-upload arrays into individual file records.
     *
     * @param array<string, array<int, mixed>> $files Parallel upload arrays with matching numeric indexes.
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int, full_path?: string}> Individual upload records.
     */
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