<?php
namespace MSM;

final class SchedulingInspector
{
    public function __construct(
        private readonly string $root,
        private readonly string $phpBinary
    ) {
    }

    public function inspectCurrentUserCrontab(array $checks): array
    {
        if (PHP_OS_FAMILY === 'Windows' || !$this->commandExists('crontab')) {
            return [
                'available' => false,
                'message' => PHP_OS_FAMILY === 'Windows'
                    ? 'Crontab non disponible sous Windows.'
                    : 'Commande crontab non disponible.',
                'user' => $this->currentUserName(),
                'checks' => [],
            ];
        }

        $output = [];
        $code = 0;
        exec('crontab -l 2>&1', $output, $code);
        $content = implode("\n", $output);
        $noCrontab = str_contains(strtolower($content), 'no crontab');

        if ($noCrontab && PHP_SAPI !== 'cli') {
            return [
                'available' => false,
                'message' => 'Le compte Web ' . $this->currentUserName()
                    . ' ne possede pas de crontab. Les checks peuvent etre planifies dans celle du compte de deploiement ; controle requis en CLI.',
                'user' => $this->currentUserName(),
                'checks' => [],
            ];
        }

        if ($code !== 0 && !$noCrontab) {
            return [
                'available' => false,
                'message' => trim($content) !== '' ? trim($content) : 'Lecture de la crontab impossible.',
                'user' => $this->currentUserName(),
                'checks' => [],
            ];
        }

        return [
            'available' => true,
            'message' => $noCrontab ? 'Aucune crontab pour ce compte.' : '',
            'user' => $this->currentUserName(),
            'checks' => $this->inspectContent($noCrontab ? '' : $content, $checks),
        ];
    }

    public function inspectContent(string $content, array $checks): array
    {
        $activeLines = array_values(array_filter(
            preg_split('/\R/', $content) ?: [],
            static function (string $line): bool {
                $line = trim($line);
                return $line !== '' && !str_starts_with($line, '#') && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $line);
            }
        ));

        $results = [];
        foreach ($checks as $check) {
            $script = (string) $check['script'];
            $matching = array_values(array_filter(
                $activeLines,
                static fn (string $line): bool => preg_match(
                    '~(?:^|[/\\\\])scripts[/\\\\]' . preg_quote($script, '~') . '(?:\s|$)~i',
                    str_replace(['"', "'"], '', $line)
                ) === 1
            ));

            $results[$script] = $this->inspectCheckLines($check, $matching);
        }

        return $results;
    }

    public function expectedCronLine(array $check): string
    {
        $root = $this->shellPath($this->root);
        $logs = $this->shellPath($this->root . DIRECTORY_SEPARATOR . 'logs');

        return sprintf(
            '%s %s %s/scripts/%s >> %s/%s 2>&1',
            $check['cron'],
            $this->shellPath($this->phpBinary),
            $root,
            $check['script'],
            $logs,
            $check['log']
        );
    }

    private function inspectCheckLines(array $check, array $lines): array
    {
        $expected = $this->expectedCronLine($check);
        if ($lines === []) {
            return $this->result('missing', 'Ligne cron absente', [], $expected);
        }

        if (count($lines) > 1) {
            return $this->result('duplicate', 'Plusieurs lignes cron ciblent ce script', $lines, $expected);
        }

        $line = trim($lines[0]);
        $parts = preg_split('/\s+/', $line, 6);
        if ($parts === false || count($parts) < 6) {
            return $this->result('invalid', 'Ligne cron illisible', $lines, $expected);
        }

        $schedule = implode(' ', array_slice($parts, 0, 5));
        if ($schedule !== $check['cron']) {
            return $this->result('schedule_mismatch', 'Frequence differente de celle recommandee', $lines, $expected);
        }

        $normalized = $this->normalizeLine($line);
        $expectedScript = $this->normalizeLine($this->root . '/scripts/' . $check['script']);
        $expectedLog = $this->normalizeLine($this->root . '/logs/' . $check['log']);

        if (!str_contains($normalized, $expectedScript)) {
            return $this->result('script_path_mismatch', 'Le chemin du script ne pointe pas vers cette installation', $lines, $expected);
        }

        if (!str_contains($normalized, $expectedLog)) {
            return $this->result('log_path_mismatch', 'Le chemin du log ne pointe pas vers cette installation', $lines, $expected);
        }

        return $this->result('ok', 'Ligne cron conforme', $lines, $expected);
    }

    private function result(string $status, string $message, array $lines, string $expected): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'configured_lines' => $lines,
            'expected_line' => $expected,
        ];
    }

    private function normalizeLine(string $value): string
    {
        return strtolower(str_replace(['\\', '"', "'"], ['/', '', ''], trim($value)));
    }

    private function shellPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function commandExists(string $command): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $code = 0;
        exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $output, $code);
        return $code === 0;
    }

    private function currentUserName(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $account = posix_getpwuid(posix_geteuid());
            if (is_array($account) && !empty($account['name'])) {
                return (string) $account['name'];
            }
        }

        return get_current_user() ?: 'inconnu';
    }
}
