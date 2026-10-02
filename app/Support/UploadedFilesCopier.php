<?php

namespace App\Support;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Copies the uploaded-files tree into FILES_ROOT (see config/filesystems.php)
 * for public/migrate.php. COPY ONLY -- originals are never touched -- and
 * idempotent: a file already at the destination with the same size is
 * skipped, so it is safe to run on every deploy.
 */
class UploadedFilesCopier
{
    /** The in-project folders each disk used before FILES_ROOT. */
    public static function sources(): array
    {
        return ['private' => storage_path('app/private'), 'public' => public_path('storage')];
    }

    /**
     * Files the app reads from the PRIVATE disk but that older code wrote to
     * the public one (X-rays, consent signatures, expense attachments --
     * this copier is what moves them now). Plain DB reads, no model scopes.
     *
     * @return list<string>
     */
    public static function privateRecordPaths(): array
    {
        return collect([
            DB::table('xray_images')->whereNotNull('image_path')->pluck('image_path'),
            DB::table('client_consents')->whereNotNull('signature_path')->pluck('signature_path'),
            DB::table('expenses')->whereNotNull('attachment_path')->pluck('attachment_path'),
        ])->flatten()->filter()->unique()->values()->all();
    }

    /**
     * After the app runs on FILES_ROOT: makes sure every private-record file
     * is in FILES_ROOT/private (rescuing it from any old/public copy) and no
     * longer in FILES_ROOT/public, then deletes each file of the old
     * in-project folders ONLY once an identical-size copy exists in the new
     * location. With $apply = false nothing is changed; the counts preview
     * what would happen.
     *
     * @return array<string, int>
     */
    public static function retireOldFolders(string $root, bool $apply, ?array $sources = null): array
    {
        $root = rtrim($root, '/\\');
        $newPrivate = $root.'/private';
        $newPublic = $root.'/public';
        ['private' => $oldPrivate, 'public' => $oldPublic] = $sources ?? static::sources();
        $stats = ['rescued_to_private' => 0, 'removed_from_public' => 0, 'missing_everywhere' => 0, 'old_files_deleted' => 0, 'old_files_kept' => 0];

        // 1. Patient files that ended up on the public disk.
        foreach (static::privateRecordPaths() as $path) {
            $target = $newPrivate.'/'.$path;
            if (! is_file($target)) {
                $source = collect([$newPublic, $oldPublic, $oldPrivate])->map(fn ($dir) => $dir.'/'.$path)->first(fn ($file) => is_file($file));
                if (! $source) {
                    $stats['missing_everywhere']++;

                    continue;
                }
                if ($apply) {
                    is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
                    copy($source, $target);
                }
                $stats['rescued_to_private']++;
            }
            if (is_file($newPublic.'/'.$path)) {
                if ($apply && is_file($target)) {
                    unlink($newPublic.'/'.$path);
                }
                $stats['removed_from_public']++;
            }
        }

        // 2. Old in-project folders: delete a file only when a same-size copy
        //    exists in the new place (public files that were patient files
        //    now live in private, so either new folder counts).
        foreach ([$oldPrivate, $oldPublic] as $old) {
            if (! is_dir($old)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $item) {
                if ($item->isDir()) {
                    if ($apply) {
                        @rmdir($item->getPathname()); // only succeeds when empty
                    }

                    continue;
                }
                if ($item->getFilename() === '.gitignore') {
                    continue;
                }
                $relative = substr($item->getPathname(), strlen($old) + 1);
                $copied = collect([$newPrivate, $newPublic])->contains(
                    fn ($dir) => is_file($dir.'/'.$relative) && filesize($dir.'/'.$relative) === $item->getSize(),
                );
                if ($copied) {
                    if ($apply) {
                        unlink($item->getPathname());
                    }
                    $stats['old_files_deleted']++;
                } else {
                    $stats['old_files_kept']++;
                }
            }
        }

        return $stats;
    }

    /**
     * @return array{copied: int, skipped: int, failed: int}
     */
    public static function copy(string $from, string $to): array
    {
        $result = ['copied' => 0, 'skipped' => 0, 'failed' => 0];

        if (! is_dir($to) && ! @mkdir($to, 0755, true) && ! is_dir($to)) {
            throw new RuntimeException("Could not create {$to} -- check that PHP may write there.");
        }

        if (! is_dir($from)) {
            return $result;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            $target = $to.DIRECTORY_SEPARATOR.substr($item->getPathname(), strlen(rtrim($from, '/\\')) + 1);

            if ($item->isDir()) {
                is_dir($target) || @mkdir($target, 0755, true);

                continue;
            }

            if (is_file($target) && filesize($target) === $item->getSize()) {
                $result['skipped']++;

                continue;
            }

            @copy($item->getPathname(), $target) ? $result['copied']++ : $result['failed']++;
        }

        return $result;
    }
}
