<?php

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\FinanceTransaction;
use App\Services\ExportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

test('export service escapes dangerous formula prefix characters and control characters', function () {
    $service = app(ExportService::class);

    // Dangerous formula prefixes should be prepended with a single quote
    expect($service->escapeFormula('=1+1'))->toBe("'=1+1")
        ->and($service->escapeFormula('=HYPERLINK("https://evil.com")'))->toBe("'=HYPERLINK(\"https://evil.com\")")
        ->and($service->escapeFormula("+cmd|' /C calc'!A0"))->toBe("'+cmd|' /C calc'!A0")
        ->and($service->escapeFormula('@SUM(1,2)'))->toBe("'@SUM(1,2)")
        ->and($service->escapeFormula('-cmd'))->toBe("'-cmd")
        ->and($service->escapeFormula('|calc'))->toBe("'|calc")
        ->and($service->escapeFormula('%0a'))->toBe("'%0a")
        ->and($service->escapeFormula('   =2+2'))->toBe("'   =2+2")
        ->and($service->escapeFormula("\t+calc"))->toBe("'\t+calc")
        ->and($service->escapeFormula("\r-calc"))->toBe("'\r-calc");

    // Legitimate numbers and negative numeric amounts must NOT be escaped
    expect($service->escapeFormula(100))->toBe(100)
        ->and($service->escapeFormula(-50.25))->toBe(-50.25)
        ->and($service->escapeFormula('-50.25'))->toBe('-50.25')
        ->and($service->escapeFormula('100.00'))->toBe('100.00')
        ->and($service->escapeFormula('+100'))->toBe('+100');

    // Safe plain text and null should remain unchanged
    expect($service->escapeFormula('Standard description text'))->toBe('Standard description text')
        ->and($service->escapeFormula(null))->toBeNull()
        ->and($service->escapeFormula(''))->toBe('');
});

test('audit log csv export escapes formula payloads in text columns', function () {
    AuditLog::create([
        'action' => '=cmd|calc',
        'description' => '@SUM(A1:B10)',
        'actor_type' => '+App\\Models\\Admin',
        'actor_id' => $this->superAdmin->id,
        'subject_type' => '-EvilPayload',
        'subject_id' => '1',
        'ip_address' => '127.0.0.1',
    ]);

    AuditLog::create([
        'action' => 'user.login',
        'description' => 'Regular admin login without formulas',
        'actor_type' => 'App\\Models\\Admin',
        'actor_id' => $this->superAdmin->id,
        'subject_type' => 'App\\Models\\User',
        'subject_id' => '2',
        'ip_address' => '192.168.1.1',
    ]);

    $response = $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.audit-logs.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('attachment; filename="audit-logs-');

    $content = $response->streamedContent();

    // Dangerous fields must be prepended with a single quote
    expect($content)
        ->toContain("'=cmd|calc")
        ->toContain("'@SUM(A1:B10)")
        ->toContain("'+App\\Models\\Admin")
        ->toContain("'-EvilPayload")
        // Normal text must be preserved without single quotes
        ->toContain('user.login')
        ->toContain('Regular admin login without formulas')
        ->toContain('127.0.0.1');
});

test('finance csv export escapes formula payloads while keeping negative amounts numeric', function () {
    FinanceTransaction::create([
        'type' => 'expense',
        'amount' => 45.50,
        'category_key' => 'office',
        'category_name' => '@OfficeSupplies',
        'description' => '=cmd|calc!A0',
        'reference' => '+REF-10029',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'card',
        'admin_id' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.finance.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    // Formula payloads in text fields must be escaped
    expect($content)
        ->toContain("'=cmd|calc!A0")
        ->toContain("'+REF-10029")
        ->toContain("'@OfficeSupplies")
        // Legitimate negative numeric amount for expense (-45.5) must remain a numeric value without quotes
        ->toContain('-45.5');
});

test('admins without export permissions are forbidden from exporting csv', function () {
    $regularAdmin = Admin::factory()->create();
    $role = Role::firstOrCreate(['name' => 'View Only', 'guard_name' => 'admin']);
    $role->syncPermissions(['audit.view', 'finance.view']);
    $regularAdmin->assignRole($role);

    $this->actingAs($regularAdmin, 'admin')
        ->get(route('admin.audit-logs.export'))
        ->assertForbidden();

    $this->actingAs($regularAdmin, 'admin')
        ->get(route('admin.finance.export'))
        ->assertForbidden();
});
