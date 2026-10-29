<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\ActivityLog;
use App\Services\DatabaseMigrationService;

class DatabaseController
{
    private DatabaseMigrationService $service;

    public function __construct()
    {
        $this->service = new DatabaseMigrationService();
    }

    /**
     * Display Database & Migration Manager dashboard.
     */
    public function index(): void
    {
        $info = $this->service->getDatabaseInfo();
        $files = $this->service->getMigrationFiles();

        Response::view('database.index', [
            'info'  => $info,
            'files' => $files,
            'csrf'  => Csrf::token(),
        ]);
    }

    /**
     * Run all pending migrations in database/migrations.
     */
    public function migrate(): void
    {
        $result = $this->service->runPendingMigrations();

        ActivityLog::log(
            'database_migrated',
            Session::getAdminId(),
            'database',
            null,
            "Executed {$result['executed_files']} migrations with {$result['total_queries']} queries"
        );

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message'] ?? 'Migration execution failed.');
        }

        Response::redirect('/database');
    }

    /**
     * Run a single specific migration file.
     */
    public function migrateOne(): void
    {
        $migration = trim((string) ($_POST['migration'] ?? ''));
        if ($migration === '') {
            Session::flash('error', 'মাইগ্রেশন ফাইল নির্বাচন করুন।');
            Response::redirect('/database');
        }

        $result = $this->service->runSingleMigration($migration);

        ActivityLog::log(
            'database_migration_single',
            Session::getAdminId(),
            'database',
            null,
            "Ran single migration {$migration}"
        );

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            $errDetail = !empty($result['errors']) ? ': ' . json_encode($result['errors'], JSON_UNESCAPED_UNICODE) : '';
            Session::flash('error', $result['message'] . $errDetail);
        }

        Response::redirect('/database');
    }

    /**
     * Upload and execute a custom .sql file directly from the browser.
     */
    public function upload(): void
    {
        if (empty($_FILES['sql_file'])) {
            Session::flash('error', 'কোনো .sql ফাইল নির্বাচন করা হয়নি।');
            Response::redirect('/database');
        }

        $result = $this->service->uploadAndExecute($_FILES['sql_file']);

        ActivityLog::log(
            'database_sql_uploaded',
            Session::getAdminId(),
            'database',
            null,
            "Uploaded and executed SQL file: " . ($_FILES['sql_file']['name'] ?? '')
        );

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            $errMsg = $result['message'] ?? 'SQL ফাইল এক্সিকিউট করতে সমস্যা হয়েছে।';
            if (!empty($result['errors'])) {
                $firstErr = is_array($result['errors'][0]) ? ($result['errors'][0]['message'] ?? '') : $result['errors'][0];
                $errMsg .= ' (' . $firstErr . ')';
            }
            Session::flash('error', $errMsg);
        }

        Response::redirect('/database');
    }

    /**
     * Execute arbitrary raw SQL commands directly.
     */
    public function runQuery(): void
    {
        $sql = trim((string) ($_POST['sql_query'] ?? ''));
        if ($sql === '') {
            Session::flash('error', 'SQL কোড লিখুন।');
            Response::redirect('/database');
        }

        $result = $this->service->executeRawSql($sql);

        ActivityLog::log(
            'database_raw_query_executed',
            Session::getAdminId(),
            'database',
            null,
            "Executed {$result['executed_queries']} custom SQL statements"
        );

        if ($result['success']) {
            Session::flash('success', sprintf('মোট %d টি SQL স্টেটমেন্ট সফলভাবে সম্পন্ন হয়েছে!', $result['executed_queries']));
        } else {
            $errText = '';
            foreach ($result['errors'] as $err) {
                $errText .= ($err['message'] ?? 'Error') . ' ';
            }
            Session::flash('error', "কুয়েরি এক্সিকিউট করতে সমস্যা: {$errText}");
        }

        Response::redirect('/database');
    }
}
