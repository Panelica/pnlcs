<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * English base strings for the client file manager (phase 3). Idempotent,
 * DB-backed like the rest of the client translations.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.files.title' => 'File Manager',
            'hosting.files.subtitle' => 'Browse and manage the files in your hosting account.',
            'hosting.files.name' => 'Name',
            'hosting.files.size' => 'Size',
            'hosting.files.modified' => 'Modified',
            'hosting.files.permissions' => 'Perms',
            'hosting.files.new_folder' => 'New Folder',
            'hosting.files.new_file' => 'New File',
            'hosting.files.folder_name' => 'Folder name',
            'hosting.files.file_name' => 'File name',
            'hosting.files.create' => 'Create',
            'hosting.files.download' => 'Download',
            'hosting.files.edit' => 'Edit',
            'hosting.files.rename' => 'Rename',
            'hosting.files.new_name' => 'New name',
            'hosting.files.delete' => 'Delete',
            'hosting.files.delete_confirm' => 'Delete this item? It will be moved to trash.',
            'hosting.files.empty' => 'This folder is empty.',
            'hosting.files.load_failed' => 'Could not load this folder.',
            'hosting.files.download_failed' => 'Could not download that file.',
            'hosting.files.save' => 'Save',
            'hosting.files.cancel' => 'Cancel',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            $exists = DB::table('dynamic_translations')
                ->where('language', 'en')->where('group', 'client')->where('key', $key)
                ->exists();
            if (! $exists) {
                DB::table('dynamic_translations')->insert([
                    'language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value,
                    'is_auto_translated' => false, 'is_reviewed' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('language', 'en')->where('group', 'client')
            ->whereIn('key', array_keys($this->rows()))
            ->delete();
    }
};
