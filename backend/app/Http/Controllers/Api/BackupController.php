<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'backups';

    public function index(): array
    {
        $disk = Storage::disk(self::DISK);
        $backups = collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path) => str_ends_with($path, '.sql'))
            ->map(fn (string $path) => [
                'filename' => basename($path),
                'size' => $disk->size($path),
                'created_at' => date('Y-m-d H:i:s', $disk->lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();

        return ['backups' => $backups];
    }

    public function store(): array
    {
        $filename = 'backup-'.now()->format('Ymd-His').'.sql';
        $sql = $this->generateDump();

        Storage::disk(self::DISK)->put(self::DIRECTORY.'/'.$filename, $sql);

        return [
            'backup' => [
                'filename' => $filename,
                'size' => strlen($sql),
            ],
            'message' => 'Copia de seguridad creada exitosamente',
        ];
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->validatedPath($filename);

        return response()->download(Storage::disk(self::DISK)->path($path), $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function restore(Request $request): array
    {
        $request->validate([
            'backup' => 'required|file|max:51200',
        ]);

        $file = $request->file('backup');
        if (strtolower($file->getClientOriginalExtension()) !== 'sql') {
            abort(422, 'El archivo debe tener extensión .sql');
        }

        $sql = file_get_contents($file->getRealPath());

        DB::transaction(function () use ($sql) {
            $this->executeDump($sql);
        });

        return [
            'message' => 'Base de datos restaurada exitosamente',
        ];
    }

    public function destroy(string $filename): array
    {
        Storage::disk(self::DISK)->delete($this->validatedPath($filename));

        return ['message' => 'Copia de seguridad eliminada'];
    }

    private function validatedPath(string $filename): string
    {
        if (basename($filename) !== $filename || ! str_ends_with($filename, '.sql')) {
            abort(404);
        }

        $path = self::DIRECTORY.'/'.$filename;
        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        return $path;
    }

    private function generateDump(): string
    {
        $driver = DB::getDriverName();
        $lines = ['-- POS Inventory backup '.now()->toDateTimeString(), '-- Driver: '.$driver, ''];

        foreach ($this->tables() as $table) {
            $lines[] = $this->tableSchema($table, $driver);
            $lines[] = $this->tableData($table);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->all();
        }

        $key = 'Tables_in_'.DB::getDatabaseName();

        return collect(DB::select('SHOW TABLES'))->map(fn ($row) => $row->{$key})->all();
    }

    private function tableSchema(string $table, string $driver): string
    {
        $schema = "DROP TABLE IF EXISTS `{$table}`;\n";

        if ($driver === 'sqlite') {
            $row = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
            $schema .= ($row[0]->sql ?? '').';';
        } else {
            $row = DB::select("SHOW CREATE TABLE `{$table}`");
            $schema .= $row[0]->{'Create Table'}.';';
        }

        return $schema;
    }

    private function tableData(string $table): string
    {
        $rows = DB::table($table)->get();
        if ($rows->isEmpty()) {
            return '';
        }

        $inserts = [];
        foreach ($rows as $row) {
            $values = collect((array) $row)
                ->map(fn ($value) => $value === null ? 'NULL' : DB::getPdo()->quote((string) $value))
                ->implode(', ');
            $inserts[] = "INSERT INTO `{$table}` VALUES ({$values});";
        }

        return implode("\n", $inserts);
    }

    private function executeDump(string $sql): void
    {
        $sql = preg_replace('/^--.*$/m', '', (string) $sql);

        $statements = array_filter(
            array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)),
            fn (string $statement) => $statement !== ''
        );

        foreach ($statements as $statement) {
            DB::unprepared($statement);
        }
    }
}
