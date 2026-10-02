<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Support\UploadedFilesCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesRootTest extends TestCase
{
    use RefreshDatabase;

    protected string $sandbox;

    protected string $originalFilesRoot = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalFilesRoot = (string) env('FILES_ROOT', '');
        $this->sandbox = storage_path('framework/testing/files-root-'.uniqid());
        File::ensureDirectoryExists($this->sandbox);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);
        $this->setFilesRoot($this->originalFilesRoot);
        parent::tearDown();
    }

    /** env() reads $_ENV / $_SERVER / getenv -- set all three (the local .env may define FILES_ROOT). */
    protected function setFilesRoot(string $value): void
    {
        putenv("FILES_ROOT={$value}");
        $_ENV['FILES_ROOT'] = $value;
        $_SERVER['FILES_ROOT'] = $value;
    }

    public function test_copier_copies_nested_files_keeps_originals_and_is_idempotent(): void
    {
        $from = $this->sandbox.'/old';
        $to = $this->sandbox.'/new/private';
        File::ensureDirectoryExists($from.'/xray-images/2026');
        File::put($from.'/xray-images/2026/a.png', 'image-bytes');
        File::put($from.'/signature.png', 'sig');

        $first = UploadedFilesCopier::copy($from, $to);

        $this->assertSame(['copied' => 2, 'skipped' => 0, 'failed' => 0], $first);
        $this->assertSame('image-bytes', File::get($to.'/xray-images/2026/a.png'));
        $this->assertFileExists($from.'/xray-images/2026/a.png');

        // Second run: nothing new to copy.
        $this->assertSame(['copied' => 0, 'skipped' => 2, 'failed' => 0], UploadedFilesCopier::copy($from, $to));

        // A file uploaded to the old place later gets picked up on the next run.
        File::put($from.'/late.pdf', 'pdf');
        $this->assertSame(['copied' => 1, 'skipped' => 2, 'failed' => 0], UploadedFilesCopier::copy($from, $to));
    }

    public function test_retiring_old_folders_rescues_patient_files_and_only_deletes_verified_copies(): void
    {
        $company = Company::factory()->create();
        DB::table('xray_images')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'image_path' => 'xray-images/scan.png', 'created_at' => now(), 'updated_at' => now()]);

        $old = ['private' => $this->sandbox.'/old-private', 'public' => $this->sandbox.'/old-public'];
        $root = $this->sandbox.'/files';
        // Old layout: an X-ray only on the PUBLIC disk (pre-KVKK), a message
        // attachment on public, a signature on private, plus one file that
        // was never copied to the new root.
        File::ensureDirectoryExists($old['public'].'/xray-images');
        File::ensureDirectoryExists($old['public'].'/message-attachments');
        File::ensureDirectoryExists($old['private'].'/consent-signatures');
        File::put($old['public'].'/xray-images/scan.png', 'xray');
        File::put($old['public'].'/message-attachments/diet.pdf', 'pdf');
        File::put($old['private'].'/consent-signatures/sig.png', 'sig');
        File::put($old['private'].'/.gitignore', '*');
        UploadedFilesCopier::copy($old['private'], $root.'/private');
        UploadedFilesCopier::copy($old['public'], $root.'/public');
        File::put($old['private'].'/late-upload.png', 'not copied yet');

        // Preview changes nothing.
        $preview = UploadedFilesCopier::retireOldFolders($root, false, $old);
        $this->assertSame(1, $preview['rescued_to_private']);
        $this->assertSame(1, $preview['removed_from_public']);
        $this->assertSame(3, $preview['old_files_deleted']);
        $this->assertSame(1, $preview['old_files_kept']);
        $this->assertFileExists($old['public'].'/xray-images/scan.png');
        $this->assertFileDoesNotExist($root.'/private/xray-images/scan.png');

        $stats = UploadedFilesCopier::retireOldFolders($root, true, $old);

        // The X-ray now lives only in the new PRIVATE folder.
        $this->assertSame('xray', File::get($root.'/private/xray-images/scan.png'));
        $this->assertFileDoesNotExist($root.'/public/xray-images/scan.png');
        // Verified old copies are gone, the uncopied one and .gitignore stay.
        $this->assertFileDoesNotExist($old['public'].'/xray-images/scan.png');
        $this->assertFileDoesNotExist($old['public'].'/message-attachments/diet.pdf');
        $this->assertFileDoesNotExist($old['private'].'/consent-signatures/sig.png');
        $this->assertFileExists($old['private'].'/late-upload.png');
        $this->assertFileExists($old['private'].'/.gitignore');
        $this->assertDirectoryDoesNotExist($old['public'].'/xray-images');
        $this->assertSame(1, $stats['old_files_kept']);
        // New public folder still serves the message attachment.
        $this->assertFileExists($root.'/public/message-attachments/diet.pdf');
    }

    public function test_copier_handles_a_missing_source_folder(): void
    {
        $result = UploadedFilesCopier::copy($this->sandbox.'/does-not-exist', $this->sandbox.'/new/public');

        $this->assertSame(['copied' => 0, 'skipped' => 0, 'failed' => 0], $result);
        $this->assertDirectoryExists($this->sandbox.'/new/public');
    }

    public function test_files_root_moves_both_disks_and_the_public_url(): void
    {
        $this->setFilesRoot('/home/technova/files');
        $config = require config_path('filesystems.php');

        $this->assertSame('/home/technova/files/private', $config['disks']['local']['root']);
        $this->assertSame('/home/technova/files/public', $config['disks']['public']['root']);
        $this->assertStringEndsWith('/files', $config['disks']['public']['url']);

        $this->setFilesRoot('');
        $default = require config_path('filesystems.php');
        $this->assertSame(storage_path('app/private'), $default['disks']['local']['root']);
        $this->assertSame(public_path('storage'), $default['disks']['public']['root']);
    }

    public function test_public_files_route_serves_public_disk_files_only(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('message-attachments/diet.pdf', '%PDF-1.4 test');

        $this->get('/files/message-attachments/diet.pdf')->assertOk();
        $this->get('/files/message-attachments/missing.pdf')->assertNotFound();
        $this->get('/files/../.env')->assertNotFound();
        $this->get('/files/message-attachments/..%2F..%2F.env')->assertNotFound();
    }
}
