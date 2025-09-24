<?php

namespace Tests\Unit\Models;

use App\Models\Backup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_has_status_constants()
    {
        $this->assertEquals('pending', Backup::STATUS_PENDING);
        $this->assertEquals('running', Backup::STATUS_RUNNING);
        $this->assertEquals('completed', Backup::STATUS_COMPLETED);
        $this->assertEquals('failed', Backup::STATUS_FAILED);
        $this->assertEquals('expired', Backup::STATUS_EXPIRED);
    }

    public function test_backup_has_type_constants()
    {
        $this->assertEquals('manual', Backup::TYPE_MANUAL);
        $this->assertEquals('scheduled', Backup::TYPE_SCHEDULED);
        $this->assertEquals('pre_restore', Backup::TYPE_PRE_RESTORE);
    }

    public function test_backup_belongs_to_creator()
    {
        $user = User::factory()->create();
        $backup = Backup::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $backup->creator);
        $this->assertEquals($user->id, $backup->creator->id);
    }

    public function test_backup_status_checks()
    {
        $completedBackup = Backup::factory()->create(['status' => Backup::STATUS_COMPLETED]);
        $runningBackup = Backup::factory()->create(['status' => Backup::STATUS_RUNNING]);
        $failedBackup = Backup::factory()->create(['status' => Backup::STATUS_FAILED]);
        $expiredBackup = Backup::factory()->create([
            'status' => Backup::STATUS_EXPIRED,
            'expires_at' => now()->subDays(1)
        ]);

        $this->assertTrue($completedBackup->isCompleted());
        $this->assertFalse($completedBackup->isRunning());
        $this->assertFalse($completedBackup->isFailed());

        $this->assertTrue($runningBackup->isRunning());
        $this->assertFalse($runningBackup->isCompleted());

        $this->assertTrue($failedBackup->isFailed());
        $this->assertFalse($failedBackup->isCompleted());

        $this->assertTrue($expiredBackup->isExpired());
    }

    public function test_backup_formats_file_size()
    {
        $backupBytes = Backup::factory()->create(['size' => 1024]);
        $backupKB = Backup::factory()->create(['size' => 1024 * 1024]);
        $backupMB = Backup::factory()->create(['size' => 1024 * 1024 * 1024]);
        $backupNoSize = Backup::factory()->create(['size' => null]);

        $this->assertEquals('1 KB', $backupBytes->getFormattedSize());
        $this->assertEquals('1 MB', $backupKB->getFormattedSize());
        $this->assertEquals('1 GB', $backupMB->getFormattedSize());
        $this->assertEquals('Unknown', $backupNoSize->getFormattedSize());
    }

    public function test_backup_calculates_duration()
    {
        $backup = Backup::factory()->create([
            'created_at' => now()->subMinutes(5),
            'completed_at' => now()
        ]);

        $incompleteBackup = Backup::factory()->create(['completed_at' => null]);

        $this->assertEquals(300, $backup->getDuration()); // 5 minutes = 300 seconds
        $this->assertNull($incompleteBackup->getDuration());
    }

    public function test_backup_formats_duration()
    {
        $shortBackup = Backup::factory()->create([
            'created_at' => now()->subSeconds(30),
            'completed_at' => now()
        ]);

        $mediumBackup = Backup::factory()->create([
            'created_at' => now()->subMinutes(5),
            'completed_at' => now()
        ]);

        $longBackup = Backup::factory()->create([
            'created_at' => now()->subHours(2),
            'completed_at' => now()
        ]);

        $incompleteBackup = Backup::factory()->create(['completed_at' => null]);

        $this->assertEquals('30s', $shortBackup->getFormattedDuration());
        $this->assertEquals('5m', $mediumBackup->getFormattedDuration());
        $this->assertEquals('2h', $longBackup->getFormattedDuration());
        $this->assertEquals('Unknown', $incompleteBackup->getFormattedDuration());
    }

    public function test_backup_status_transitions()
    {
        $backup = Backup::factory()->create(['status' => Backup::STATUS_PENDING]);

        // Mark as running
        $this->assertTrue($backup->markAsRunning());
        $backup->refresh();
        $this->assertEquals(Backup::STATUS_RUNNING, $backup->status);

        // Mark as completed
        $this->assertTrue($backup->markAsCompleted(1024, 'checksum123'));
        $backup->refresh();
        $this->assertEquals(Backup::STATUS_COMPLETED, $backup->status);
        $this->assertEquals(1024, $backup->size);
        $this->assertEquals('checksum123', $backup->checksum);
        $this->assertNotNull($backup->completed_at);

        // Test marking as failed
        $failedBackup = Backup::factory()->create(['status' => Backup::STATUS_RUNNING]);
        $this->assertTrue($failedBackup->markAsFailed('Test error'));
        $failedBackup->refresh();
        $this->assertEquals(Backup::STATUS_FAILED, $failedBackup->status);
        $this->assertEquals('Test error', $failedBackup->error_message);

        // Test marking as expired
        $expiredBackup = Backup::factory()->create(['status' => Backup::STATUS_COMPLETED]);
        $this->assertTrue($expiredBackup->markAsExpired());
        $expiredBackup->refresh();
        $this->assertEquals(Backup::STATUS_EXPIRED, $expiredBackup->status);
    }

    public function test_backup_file_operations()
    {
        Storage::fake('local');
        
        $backup = Backup::factory()->create([
            'path' => 'backups/test-backup.zip',
            'status' => Backup::STATUS_COMPLETED
        ]);

        // Initially file doesn't exist
        $this->assertFalse($backup->fileExists());
        $this->assertNull($backup->getDownloadUrl());

        // Create fake file
        Storage::put('backups/test-backup.zip', 'fake backup content');
        $this->assertTrue($backup->fileExists());
        $this->assertNotNull($backup->getDownloadUrl());

        // Delete file
        $this->assertTrue($backup->deleteFile());
        $this->assertFalse($backup->fileExists());
    }

    public function test_backup_metadata_operations()
    {
        $backup = Backup::factory()->create(['metadata' => ['key1' => 'value1']]);

        $this->assertEquals('value1', $backup->getMetadata('key1'));
        $this->assertEquals('default', $backup->getMetadata('nonexistent', 'default'));

        $this->assertTrue($backup->setMetadata('key2', 'value2'));
        $backup->refresh();
        $this->assertEquals('value2', $backup->getMetadata('key2'));
    }

    public function test_backup_scopes()
    {
        $user = User::factory()->create();

        $completedBackup = Backup::factory()->create(['status' => Backup::STATUS_COMPLETED]);
        $failedBackup = Backup::factory()->create(['status' => Backup::STATUS_FAILED]);
        $expiredBackup = Backup::factory()->create([
            'status' => Backup::STATUS_EXPIRED,
            'expires_at' => now()->subDays(1)
        ]);
        $manualBackup = Backup::factory()->create(['type' => Backup::TYPE_MANUAL]);
        $scheduledBackup = Backup::factory()->create(['type' => Backup::TYPE_SCHEDULED]);
        $recentBackup = Backup::factory()->create(['created_at' => now()->subDays(15)]);
        $oldBackup = Backup::factory()->create(['created_at' => now()->subDays(45)]);
        $userBackup = Backup::factory()->create(['created_by' => $user->id]);

        // Test completed scope
        $completedBackups = Backup::completed()->get();
        $this->assertTrue($completedBackups->contains($completedBackup));
        $this->assertFalse($completedBackups->contains($failedBackup));

        // Test failed scope
        $failedBackups = Backup::failed()->get();
        $this->assertTrue($failedBackups->contains($failedBackup));
        $this->assertFalse($failedBackups->contains($completedBackup));

        // Test expired scope
        $expiredBackups = Backup::expired()->get();
        $this->assertTrue($expiredBackups->contains($expiredBackup));
        $this->assertFalse($expiredBackups->contains($completedBackup));

        // Test manual scope
        $manualBackups = Backup::manual()->get();
        $this->assertTrue($manualBackups->contains($manualBackup));
        $this->assertFalse($manualBackups->contains($scheduledBackup));

        // Test scheduled scope
        $scheduledBackups = Backup::scheduled()->get();
        $this->assertTrue($scheduledBackups->contains($scheduledBackup));
        $this->assertFalse($scheduledBackups->contains($manualBackup));

        // Test recent scope
        $recentBackups = Backup::recent(30)->get();
        $this->assertTrue($recentBackups->contains($recentBackup));
        $this->assertFalse($recentBackups->contains($oldBackup));

        // Test created by scope
        $userBackups = Backup::createdBy($user->id)->get();
        $this->assertTrue($userBackups->contains($userBackup));
        $this->assertFalse($userBackups->contains($completedBackup));
    }

    public function test_backup_create_backup_method()
    {
        $user = User::factory()->create();
        Setting::set('backup_retention_days', 15);

        $backup = Backup::createBackup(
            'test-backup',
            Backup::TYPE_MANUAL,
            $user,
            ['source' => 'test']
        );

        $this->assertEquals('test-backup', $backup->name);
        $this->assertEquals('test-backup.zip', $backup->filename);
        $this->assertEquals(Backup::STATUS_PENDING, $backup->status);
        $this->assertEquals(Backup::TYPE_MANUAL, $backup->type);
        $this->assertEquals($user->id, $backup->created_by);
        $this->assertEquals(['source' => 'test'], $backup->metadata);
        $this->assertNotNull($backup->expires_at);
    }

    public function test_backup_casts_attributes_correctly()
    {
        $backup = Backup::factory()->create([
            'metadata' => ['key' => 'value'],
            'size' => '1024',
            'expires_at' => '2023-12-31 23:59:59',
            'completed_at' => '2023-01-01 12:00:00'
        ]);

        $this->assertIsArray($backup->metadata);
        $this->assertEquals(['key' => 'value'], $backup->metadata);
        $this->assertIsInt($backup->size);
        $this->assertEquals(1024, $backup->size);
        $this->assertInstanceOf(\Carbon\Carbon::class, $backup->expires_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $backup->completed_at);
    }
}