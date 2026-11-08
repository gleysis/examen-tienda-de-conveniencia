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

    private const HEADER = '-- POS Inventory backup';

    private const SIGNATURE_PREFIX = '-- Signature: ';

    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'personal_access_tokens',
        'sessions',
    ];

    public function index(): array
    {
        $disk = Storage::disk(self::DISK);
        $backups = collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path) => str_ends_with($path, '.sql'))
            ->map(fn (string $path) => [
                'filename' => basename($path),
                'size' => $disk->size($path),
                'created_at' => now()->setTimestamp($disk->lastModified($path))->toDateTimeString(),
            ])
            ->sortByDesc('created_at')
            ->values();

        return ['backups' => $backups];
    }

    public function store(): array
    {
        $filename = $this->saveBackup('backup');

        return [
            'backup' => [
                'filename' => $filename,
                'size' => Storage::disk(self::DISK)->size(self::DIRECTORY.'/'.$filename),
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
        ], [
            'backup.required' => 'Debe seleccionar un archivo de respaldo',
            'backup.file' => 'No se pudo subir el archivo. Verifique que no exceda el límite de tamaño del servidor',
            'backup.uploaded' => 'No se pudo subir el archivo. Verifique que no exceda el límite de tamaño del servidor',
            'backup.max' => 'El archivo no debe superar los 50 MB',
        ]);

        $file = $request->file('backup');
        if (strtolower($file->getClientOriginalExtension()) !== 'sql') {
            abort(422, 'El archivo debe tener extensión .sql');
        }

        $sql = (string) file_get_contents($file->getRealPath());
        $this->ensureValidDump($sql);
        $this->ensureSignedDump($sql);

        return $this->runRestore($sql);
    }

    public function restoreFile(string $filename): array
    {
        $sql = (string) Storage::disk(self::DISK)->get($this->validatedPath($filename));
        $this->ensureValidDump($sql);

        return $this->runRestore($sql);
    }

    public function destroy(string $filename): array
    {
        Storage::disk(self::DISK)->delete($this->validatedPath($filename));

        return ['message' => 'Copia de seguridad eliminada'];
    }

    /**
     * Validate the dump, save a safety copy of the current data, and execute the dump.
     *
     * MySQL implicitly commits on DDL, so the restore only runs inside a transaction on SQLite;
     * the safety backup is the recovery path when a MySQL restore fails midway.
     *
     * @return array{message: string, safety_backup: string}
     */
    private function runRestore(string $sql): array
    {
        $safetyBackup = $this->saveBackup('auto-before-restore');

        if (DB::getDriverName() === 'sqlite') {
            DB::transaction(fn () => $this->executeDump($sql));
        } else {
            $this->executeDump($sql);
        }

        return [
            'message' => 'Base de datos restaurada exitosamente',
            'safety_backup' => $safetyBackup,
        ];
    }

    private function ensureValidDump(string $sql): void
    {
        if (! str_starts_with(ltrim($sql), self::HEADER)) {
            abort(422, 'El archivo no es un respaldo válido generado por el sistema');
        }

        if (preg_match('/^-- Driver: (\w+)/m', $sql, $matches) && $matches[1] !== DB::getDriverName()) {
            abort(422, 'El respaldo fue generado para otro motor de base de datos ('.$matches[1].')');
        }
    }

    /**
     * Uploaded files are executed as raw SQL, so only dumps signed by this
     * installation (HMAC with the application key) are accepted.
     */
    private function ensureSignedDump(string $sql): void
    {
        $isSigned = preg_match('/\n'.preg_quote(self::SIGNATURE_PREFIX, '/').'([a-f0-9]{64})\s*$/', $sql, $matches, PREG_OFFSET_CAPTURE);

        if (! $isSigned || ! hash_equals($this->signature(substr($sql, 0, $matches[0][1])), $matches[1][0])) {
            abort(422, 'El respaldo no tiene una firma válida de este sistema o fue modificado');
        }
    }

    private function signature(string $dump): string
    {
        return hash_hmac('sha256', $dump, (string) config('app.key'));
    }

    private function saveBackup(string $prefix): string
    {
        $filename = $prefix.'-'.now()->format('Ymd-His-u').'.sql';
        $dump = $this->generateDump();

        Storage::disk(self::DISK)->put(self::DIRECTORY.'/'.$filename, $dump."\n".self::SIGNATURE_PREFIX.$this->signature($dump));

        return $filename;
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
        $lines = [self::HEADER.' '.now()->toDateTimeString(), '-- Driver: '.$driver, ''];

        if ($driver !== 'sqlite') {
            $lines[] = 'SET NAMES utf8mb4;';
            $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
            $lines[] = '';
        }

        foreach ($this->tables() as $table) {
            $lines[] = $this->tableSchema($table, $driver);
            $lines[] = $this->tableData($table);
            $lines[] = '';
        }

        if ($driver !== 'sqlite') {
            $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        $tables = DB::getDriverName() === 'sqlite'
            ? collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name')
            : collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))->map(fn ($row) => array_values((array) $row)[0]);

        return $tables
            ->reject(fn (string $table) => in_array($table, self::EXCLUDED_TABLES, true))
            ->values()
            ->all();
    }

    private function tableSchema(string $table, string $driver): string
    {
        $quotedTable = $this->quoteIdentifier($table);
        $schema = "DROP TABLE IF EXISTS {$quotedTable};\n";

        if ($driver === 'sqlite') {
            $row = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
            $schema .= ($row[0]->sql ?? '').';';
        } else {
            $row = DB::select("SHOW CREATE TABLE {$quotedTable}");
            $schema .= $row[0]->{'Create Table'}.';';
        }

        return $schema;
    }

    private function tableData(string $table): string
    {
        $quotedTable = $this->quoteIdentifier($table);
        $pdo = DB::getPdo();
        $columnList = null;
        $inserts = [];

        foreach (DB::table($table)->cursor() as $row) {
            $row = (array) $row;
            $columnList ??= collect(array_keys($row))->map(fn (string $column) => $this->quoteIdentifier($column))->implode(', ');

            $values = collect($row)
                ->map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value))
                ->implode(', ');

            $inserts[] = "INSERT INTO {$quotedTable} ({$columnList}) VALUES ({$values});";
        }

        return implode("\n", $inserts);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function executeDump(string $sql): void
    {
        $sql = preg_replace('/^--.*$/m', '', $sql);

        $statements = array_filter(
            array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)),
            fn (string $statement) => $statement !== ''
        );

        $driver = DB::getDriverName();

        try {
            if ($driver === 'sqlite') {
                DB::unprepared('PRAGMA defer_foreign_keys = ON');
            } else {
                DB::unprepared('SET FOREIGN_KEY_CHECKS = 0');
            }

            foreach ($statements as $statement) {
                DB::unprepared($statement);
            }
        } finally {
            if ($driver === 'sqlite') {
                DB::unprepared('PRAGMA defer_foreign_keys = OFF');
            } else {
                DB::unprepared('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }
}
