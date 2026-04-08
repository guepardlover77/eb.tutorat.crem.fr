<?php

// tests/Feature/TutoringImportControllerTest.php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TutoringMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

class TutoringImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createExcelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_import_').'.xls';
        (new Xls($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xls', 'application/vnd.ms-excel', null, true);
    }

    public function test_unauthenticated_user_cannot_access_import_page(): void
    {
        $response = $this->get('/admin/tutoring-import');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_import_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/tutoring-import');

        $response->assertStatus(200);
        $response->assertSee('Import');
    }

    public function test_upload_valid_excel_inserts_members(): void
    {
        $user = User::factory()->create();
        $file = $this->createExcelFile([
            ['Numéro CREM', 'Prénom', 'Nom'],
            ['10001', 'Alice', 'Martin'],
            ['90002', 'Bob', 'Durand'],
        ]);

        $response = $this->actingAs($user)->post('/admin/tutoring-import', ['excel' => $file]);

        $response->assertRedirect('/admin/tutoring-import');
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('tutoring_members', 2);
        $this->assertDatabaseHas('tutoring_members', ['crem_number' => '10001', 'first_name' => 'Alice']);
        $this->assertDatabaseHas('tutoring_members', ['crem_number' => '90002', 'first_name' => 'Bob']);
    }

    public function test_upload_truncates_previous_data(): void
    {
        TutoringMember::create(['crem_number' => '11111']);
        $user = User::factory()->create();
        $file = $this->createExcelFile([
            ['Numéro CREM'],
            ['99999'],
        ]);

        $this->actingAs($user)->post('/admin/tutoring-import', ['excel' => $file]);

        $this->assertDatabaseCount('tutoring_members', 1);
        $this->assertDatabaseHas('tutoring_members', ['crem_number' => '99999']);
        $this->assertDatabaseMissing('tutoring_members', ['crem_number' => '11111']);
    }

    public function test_upload_without_crem_column_returns_error(): void
    {
        $user = User::factory()->create();
        $file = $this->createExcelFile([
            ['Prénom', 'Nom'],
            ['Alice', 'Martin'],
        ]);

        $response = $this->actingAs($user)->post('/admin/tutoring-import', ['excel' => $file]);

        $response->assertRedirect('/admin/tutoring-import');
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('tutoring_members', 0);
    }

    public function test_import_without_file_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/tutoring-import', []);

        $response->assertSessionHasErrors('excel');
    }

    public function test_corrupt_excel_file_returns_error(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // A plain text file disguised as xlsx — passes mimes validation, fails parsing
        $fakeExcel = UploadedFile::fake()->createWithContent(
            'corrupt.xlsx',
            'this is not valid excel content'
        );

        $response = $this->post(route('admin.tutoring-import.store'), [
            'excel' => $fakeExcel,
        ]);

        $response->assertRedirect(route('admin.tutoring-import'));
        $response->assertSessionHas('error');
    }
}
