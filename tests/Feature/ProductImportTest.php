<?php

namespace Tests\Feature;

use App\Services\Importing\Support\PathHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ProductImportTest extends TestCase
{
    use DatabaseTransactions;

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = storage_path('app/imports/test_'.uniqid());
        File::ensureDirectoryExists($this->tmpDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpDir);
        parent::tearDown();
    }

    // ─── PathHelper Tests ────────────────────────────────────────────

    public function test_path_normalize_replaces_backslashes(): void
    {
        $this->assertEquals('DRG-SCHB-001/Black/1.jpg', PathHelper::normalize('DRG-SCHB-001\\Black\\1.jpg'));
    }

    public function test_path_normalize_collapses_duplicate_separators(): void
    {
        $this->assertEquals('images/sku/file.jpg', PathHelper::normalize('images//sku///file.jpg'));
    }

    public function test_path_normalize_removes_trailing_slash(): void
    {
        $this->assertEquals('/var/www/html', PathHelper::normalize('/var/www/html/'));
    }

    public function test_path_join_combines_segments(): void
    {
        $this->assertEquals('/base/folder/file.jpg', PathHelper::join('/base', 'folder', 'file.jpg'));
    }

    public function test_path_join_prevents_traversal(): void
    {
        $result = PathHelper::join('/base', '../etc/passwd');
        $this->assertStringNotContainsString('..', $result);
    }

    public function test_resolve_secure_prevents_escape(): void
    {
        $base = $this->tmpDir;
        File::ensureDirectoryExists($base);

        $this->assertNull(PathHelper::resolveSecure($base, '../../etc/passwd'));
    }

    public function test_resolve_secure_allows_valid_relative(): void
    {
        $base = $this->tmpDir;
        File::ensureDirectoryExists("{$base}/images");

        $result = PathHelper::resolveSecure($base, 'images');
        $this->assertNotNull($result);
        $this->assertStringStartsWith(PathHelper::normalize($base), $result);
    }

    // ─── Image Extension Tests ───────────────────────────────────────

    public function test_is_image_file_case_insensitive(): void
    {
        $this->assertTrue(PathHelper::isImageFile('IMG_001.JPG'));
        $this->assertTrue(PathHelper::isImageFile('img_001.jpg'));
        $this->assertTrue(PathHelper::isImageFile('Image 1.PNG'));
        $this->assertTrue(PathHelper::isImageFile('photo.WebP'));
        $this->assertTrue(PathHelper::isImageFile('شنطة.png'));
    }

    public function test_non_image_files_rejected(): void
    {
        $this->assertFalse(PathHelper::isImageFile('readme.txt'));
        $this->assertFalse(PathHelper::isImageFile('data.csv'));
        $this->assertFalse(PathHelper::isImageFile('.gitignore'));
    }

    // ─── Filename Sanitization ───────────────────────────────────────

    public function test_sanitize_preserves_unicode(): void
    {
        $this->assertEquals('شنطة.png', PathHelper::sanitizeFilename('شنطة.png'));
    }

    public function test_sanitize_preserves_spaces(): void
    {
        $this->assertEquals('Image 1.PNG', PathHelper::sanitizeFilename('Image 1.PNG'));
    }

    public function test_sanitize_removes_traversal(): void
    {
        $this->assertEquals('file.jpg', PathHelper::sanitizeFilename('../file.jpg'));
        $this->assertEquals('file.jpg', PathHelper::sanitizeFilename('..\\file.jpg'));
    }

    public function test_sanitize_removes_null_bytes(): void
    {
        $this->assertEquals('file.jpg', PathHelper::sanitizeFilename("file\0.jpg"));
    }

    // ─── Mixed Path Separator Tests ──────────────────────────────────

    public function test_windows_paths_normalized_to_forward_slashes(): void
    {
        $input = 'D:\\Bagisto\\storage\\app\\imports\\images\\SKU-001\\Black';
        $result = PathHelper::normalize($input);

        $this->assertStringNotContainsString('\\', $result);
        $this->assertStringContainsString('SKU-001/Black', $result);
    }

    public function test_mixed_separators_normalized(): void
    {
        $input = 'images/SKU-001\\Color/1.jpg';
        $result = PathHelper::normalize($input);

        $this->assertEquals('images/SKU-001/Color/1.jpg', $result);
    }

    // ─── Upload Validation Tests ─────────────────────────────────────

    public function test_rar_upload_rejected_with_message(): void
    {
        $this->actingAsAdmin();

        $file = UploadedFile::fake()->create('products.rar', 100);

        $response = $this->postJson(route('admin.catalog.products.import.upload'), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'RAR archives are not supported. Please compress your import package as a ZIP archive.',
        ]);
    }

    public function test_invalid_file_type_rejected(): void
    {
        $this->actingAsAdmin();

        $file = UploadedFile::fake()->create('products.tar', 100);

        $response = $this->postJson(route('admin.catalog.products.import.upload'), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Invalid file type: .tar. Only .zip files are accepted.']);
    }

    public function test_zip_without_csv_rejected(): void
    {
        $this->actingAsAdmin();

        // Create a ZIP without products.csv
        $zipPath = $this->tmpDir.'/test.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'no csv here');
        $zip->close();

        $file = new UploadedFile($zipPath, 'test.zip', 'application/zip', null, true);

        $response = $this->postJson(route('admin.catalog.products.import.upload'), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_zip_without_images_dir_rejected(): void
    {
        $this->actingAsAdmin();

        $zipPath = $this->tmpDir.'/test.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('products.csv', "parent_sku,sku,name_en\nTEST,TEST-1,Test");
        $zip->close();

        $file = new UploadedFile($zipPath, 'test.zip', 'application/zip', null, true);

        $response = $this->postJson(route('admin.catalog.products.import.upload'), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_valid_zip_accepted(): void
    {
        $this->actingAsAdmin();
        \Illuminate\Support\Facades\Queue::fake();

        $zipPath = $this->tmpDir.'/valid.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('products.csv', "parent_sku,sku,name_en,name_ar,color,category,qty,price,price_before_discount,description_en_short,description_ar_short,description_en_long,description_ar_long\nTEST-PARENT,TEST-V1,Test Product,منتج تجريبي,Black,,,100,120,short,قصير,long,طويل");
        $zip->addEmptyDir('images');
        $zip->addEmptyDir('images/TEST-PARENT');
        $zip->close();

        $file = new UploadedFile($zipPath, 'valid.zip', 'application/zip', null, true);

        $response = $this->postJson(route('admin.catalog.products.import.upload'), [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['batch_id', 'message']);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessProductImport::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function actingAsAdmin(): void
    {
        // Ensure role exists
        $role = \Webkul\User\Models\Role::firstOrCreate(
            ['id' => 1],
            [
                'name'            => 'Administrator',
                'description'     => 'Administrator',
                'permission_type' => 'all',
                'permissions'     => null,
            ]
        );

        $admin = \Webkul\User\Models\Admin::first();

        if (! $admin) {
            $admin = \Webkul\User\Models\Admin::factory()->create([
                'status'  => 1,
                'role_id' => $role->id,
            ]);
        }

        $this->actingAs($admin, 'admin');
    }

    private function createValidZip(string $suffix): string
    {
        $zipPath = $this->tmpDir."/{$suffix}.zip";
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('products.csv', implode("\n", [
            'parent_sku,sku,name_en,name_ar,color,category,qty,price,price_before_discount,description_en_short,description_ar_short,description_en_long,description_ar_long',
            'IDEM-001,IDEM-001-BLK,Idempotent Test,اختبار,Black,,10,100,120,short,قصير,long,طويل',
        ]));
        $zip->addEmptyDir('images');
        $zip->addEmptyDir('images/IDEM-001');
        $zip->addEmptyDir('images/IDEM-001/Black');
        $zip->close();

        return $zipPath;
    }
}
